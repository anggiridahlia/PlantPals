<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['id']; // Get the buyer's ID from session

require_once 'config.php'; // Include database connection

// Inisialisasi variabel
$checkout_items = [];
$total_checkout_amount_form = 0;
$validation_errors = [];

// Ambil data POST dari order_form.php
if (isset($_POST['action']) && $_POST['action'] === 'checkout_cart' && isset($_POST['checkout_items'])) {
    $checkout_items_raw = $_POST['checkout_items'];
    $total_checkout_amount_form = floatval($_POST['total_checkout_amount'] ?? 0); // Total yang dikirim dari form

    $full_name = isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '';
    $address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';
    $phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : 'Tidak ada catatan';

    // Validasi input form
    if (empty($full_name)) { $validation_errors[] = "Nama Lengkap tidak boleh kosong."; }
    if (empty($address)) { $validation_errors[] = "Alamat Pengiriman tidak boleh kosong."; }
    if (empty($phone_number)) { $validation_errors[] = "Nomor Telepon tidak boleh kosong."; }
    // Email opsional

    if (!empty($validation_errors)) {
        echo "<script>alert('" . implode("\\n", $validation_errors) . "'); window.history.back();</script>";
        exit;
    }

    // --- Validasi Stok dan Harga (PENTING untuk keamanan & integritas) ---
    $calculated_total_amount = 0;
    $products_to_process = [];

    foreach ($checkout_items_raw as $id => $item_data) {
        $product_id = intval($item_data['id']);
        $product_name = htmlspecialchars($item_data['name']);
        $unit_price_form = floatval($item_data['price']);
        $quantity_requested = intval($item_data['quantity']);
        $store_id_string = htmlspecialchars($item_data['store_id_string']);
        $store_name = htmlspecialchars($item_data['store_name']);

        if ($quantity_requested <= 0) {
            $validation_errors[] = "Kuantitas untuk " . $product_name . " tidak valid.";
            continue;
        }

        // Ambil stok dan harga terbaru dari database
        $sql_product_info = "SELECT price, stock FROM products WHERE id = ?";
        $stmt_product_info = mysqli_prepare($conn, $sql_product_info);
        if ($stmt_product_info) {
            mysqli_stmt_bind_param($stmt_product_info, "i", $product_id);
            mysqli_stmt_execute($stmt_product_info);
            mysqli_stmt_bind_result($stmt_product_info, $db_price, $db_stock);
            if (mysqli_stmt_fetch($stmt_product_info)) {
                mysqli_stmt_close($stmt_product_info);

                // Periksa stok
                if ($quantity_requested > $db_stock) {
                    $validation_errors[] = "Stok untuk '" . $product_name . "' tidak mencukupi. Tersedia: " . $db_stock . ".";
                }
                // Periksa harga (opsional tapi disarankan untuk mencegah tampering)
                if (abs($unit_price_form - $db_price) > 0.01) { // Toleransi perbedaan kecil
                    // $validation_errors[] = "Harga produk '" . $product_name . "' tidak sesuai. Silakan coba lagi.";
                    // Jika harga tidak sesuai, gunakan harga dari database
                    $unit_price_to_use = $db_price;
                } else {
                    $unit_price_to_use = $unit_price_form;
                }

                $products_to_process[$product_id] = [
                    'id' => $product_id,
                    'name' => $product_name,
                    'unit_price' => $unit_price_to_use,
                    'quantity' => $quantity_requested,
                    'current_stock' => $db_stock,
                    'store_id_string' => $store_id_string,
                    'store_name' => $store_name
                ];
                $calculated_total_amount += ($unit_price_to_use * $quantity_requested);

            } else {
                mysqli_stmt_close($stmt_product_info);
                $validation_errors[] = "Produk '" . $product_name . "' tidak ditemukan di database.";
            }
        } else {
            error_log("Error preparing product info statement in process_order.php: " . mysqli_error($conn));
            $validation_errors[] = "Terjadi kesalahan sistem saat memverifikasi produk.";
        }
    }

    if (!empty($validation_errors)) {
        echo "<script>alert('Beberapa masalah terdeteksi:\\n" . implode("\\n", $validation_errors) . "'); window.history.back();</script>";
        exit;
    }

    // Verifikasi total jumlah (opsional, tapi bagus untuk keamanan)
    if (abs($calculated_total_amount - $total_checkout_amount_form) > 0.01) {
        error_log("Total amount mismatch detected for user " . $user_id . ". Form total: " . $total_checkout_amount_form . ", Calculated total: " . $calculated_total_amount);
        // Bisa jadi serangan tampering atau bug, kita bisa membatalkan atau menggunakan calculated_total_amount
        $total_amount_for_order = $calculated_total_amount; // Gunakan yang dihitung server
        // $validation_errors[] = "Terdeteksi ketidaksesuaian jumlah pembayaran. Silakan coba lagi.";
        // echo "<script>alert('Terdeteksi ketidaksesuaian jumlah pembayaran. Silakan coba lagi.'); window.history.back();</script>";
        // exit;
    } else {
        $total_amount_for_order = $total_checkout_amount_form;
    }

    // --- Mulai Transaksi (untuk atomicity) ---
    mysqli_autocommit($conn, FALSE);
    $success = true;

    // 1. Insert into orders table
    $order_id = null;
    $sql_order = "INSERT INTO orders (user_id, total_amount, delivery_address, customer_name, customer_phone, customer_email, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt_order, "idsssss", $user_id, $total_amount_for_order, $address, $full_name, $phone_number, $email, $notes);
        if (mysqli_stmt_execute($stmt_order)) {
            $order_id = mysqli_insert_id($conn);
        } else {
            $success = false;
            error_log("Error inserting into orders table: " . mysqli_stmt_error($stmt_order));
        }
        mysqli_stmt_close($stmt_order);
    } else {
        $success = false;
        error_log("Error preparing order insert statement: " . mysqli_error($conn));
    }

    // 2. Insert into order_items table and Update product stock for EACH item
    if ($success && $order_id) {
        foreach ($products_to_process as $item) {
            $product_id_db = $item['id'];
            $product_name_db = $item['name'];
            $unit_price_db = $item['unit_price'];
            $quantity_db = $item['quantity'];
            $current_stock_db = $item['current_stock'];
            $store_id_string_db = $item['store_id_string'];
            $store_name_db = $item['store_name'];

            // Insert into order_items
            $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, store_id, store_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_item = mysqli_prepare($conn, $sql_item)) {
                mysqli_stmt_bind_param($stmt_item, "iisddss", $order_id, $product_id_db, $product_name_db, $unit_price_db, $quantity_db, $store_id_string_db, $store_name_db);
                if (!mysqli_stmt_execute($stmt_item)) {
                    $success = false;
                    error_log("Error inserting into order_items table for product " . $product_id_db . ": " . mysqli_stmt_error($stmt_item));
                    break; // Keluar dari loop jika ada kesalahan
                }
                mysqli_stmt_close($stmt_item);
            } else {
                $success = false;
                error_log("Error preparing order_items insert statement: " . mysqli_error($conn));
                break;
            }

            // Update product stock
            $new_stock = $current_stock_db - $quantity_db;
            $sql_update_stock = "UPDATE products SET stock = ? WHERE id = ?";
            if ($stmt_update_stock = mysqli_prepare($conn, $sql_update_stock)) {
                mysqli_stmt_bind_param($stmt_update_stock, "ii", $new_stock, $product_id_db);
                if (!mysqli_stmt_execute($stmt_update_stock)) {
                    $success = false;
                    error_log("Error updating stock for product " . $product_id_db . ": " . mysqli_stmt_error($stmt_update_stock));
                    break;
                }
                mysqli_stmt_close($stmt_update_stock);
            } else {
                $success = false;
                error_log("Error preparing stock update statement: " . mysqli_error($conn));
                break;
            }
        }
    }

    // --- Commit or Rollback Transaction ---
    if ($success) {
        mysqli_commit($conn);
        $confirmation_message = "Pesanan Anda berhasil dikonfirmasi! ID Pesanan: #" . $order_id . ".";
        $confirmation_status_class = "success";
        $icon_class = "fas fa-check-circle"; // Icon for success
        
        // Hapus item dari sesi keranjang setelah checkout berhasil
        foreach ($products_to_process as $item) {
            if (isset($_SESSION['cart'][$item['id']])) {
                unset($_SESSION['cart'][$item['id']]);
            }
        }

    } else {
        mysqli_rollback($conn);
        $confirmation_message = "Terjadi kesalahan saat memproses pesanan Anda. Mohon coba lagi.";
        $confirmation_status_class = "error";
        $icon_class = "fas fa-times-circle"; // Icon for error
        // Error logging is already done above.
    }

    mysqli_autocommit($conn, TRUE); // Re-enable autocommit

} else {
    // Jika akses langsung ke process_order.php tanpa data POST yang valid
    $confirmation_message = "Akses tidak sah atau data pesanan tidak lengkap.";
    $confirmation_status_class = "error";
    $icon_class = "fas fa-exclamation-triangle";
}

mysqli_close($conn); // Close connection after all operations
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/order_form_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <div class="main-content-wrapper-form">
        <div class="confirmation-container">
            <h2 class="<?php echo $confirmation_status_class; ?>">
                <i class="<?php echo $icon_class; ?>"></i> <?php echo ($confirmation_status_class == 'success') ? 'Pesanan Terkonfirmasi!' : 'Gagal Memproses Pesanan'; ?>
            </h2>
            <p><?php echo $confirmation_message; ?></p>

            <?php if ($success): // Only show order details if successful ?>
            <div class="order-details-summary">
                <h3>Ringkasan Pesanan Anda:</h3>
                <?php foreach ($products_to_process as $item): ?>
                    <p><strong><?php echo htmlspecialchars($item['name']); ?>:</strong> <span><?php echo htmlspecialchars($item['quantity']); ?>x @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> (dari <?php echo htmlspecialchars($item['store_name']); ?>)</span></p>
                <?php endforeach; ?>
                <h3 style="margin-top: 25px;">Total Pembayaran: Rp <?php echo number_format($total_amount_for_order, 0, ',', '.'); ?></h3>

                <h3 style="margin-top: 25px;">Detail Pengiriman:</h3>
                <p><strong>Nama Lengkap:</strong> <span><?php echo htmlspecialchars($full_name); ?></span></p>
                <p><strong>Alamat:</strong> <span><?php echo htmlspecialchars($address); ?></span></p>
                <p><strong>Telepon:</strong> <span><?php echo htmlspecialchars($phone_number); ?></span></p>
                <p><strong>Email:</strong> <span><?php echo htmlspecialchars($email); ?></span></p>
                <p><strong>Catatan:</strong> <span><?php echo htmlspecialchars($notes); ?></span></p>
            </div>
            <p>Tim kami akan segera menghubungi Anda untuk proses pengiriman.</p>
            <?php endif; ?>
        </div>
        <a href="/PlantPals/dashboard.php?page=home" class="back-to-dashboard-btn"><i class="fas fa-arrow-left"></i> Kembali ke Home</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>
</body>
</html>