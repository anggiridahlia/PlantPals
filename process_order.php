<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Inisialisasi variabel untuk status konfirmasi
$confirmation_message = "Terjadi kesalahan yang tidak terduga dalam proses pemesanan.";
$confirmation_status_class = "error";
$icon_class = "fas fa-exclamation-triangle"; // Default icon for error

// Inisialisasi variabel untuk data yang akan ditampilkan (penting agar tidak undefined)
$full_name = 'N/A';
$address = 'N/A';
$phone_number = 'N/A';
$email = 'N/A';
$notes = 'Tidak ada catatan';
$total_amount_for_order = 0;
$products_to_process = [];
$payment_method = 'N/A'; // Default value

// Default success state (akan diubah jika proses berhasil)
$success = false; // Inisialisasi $success di awal

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['id'];

// Koneksi ke database
require_once 'config.php';
if ($conn === false) { // Cek jika koneksi gagal
    $confirmation_message = "Koneksi database gagal: " . mysqli_connect_error();
    $confirmation_status_class = "error";
    $icon_class = "fas fa-database";
    goto end_process_html; // Lompat ke bagian akhir HTML
}

// Ambil data POST dari order_form.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout_cart' && isset($_POST['checkout_items'])) {
    
    $checkout_items_raw = $_POST['checkout_items'];
    $total_checkout_amount_form = floatval($_POST['total_checkout_amount'] ?? 0);

    // Ambil data detail pengiriman dari POST
    $full_name = isset($_POST['full_name']) ? htmlspecialchars(trim($_POST['full_name'])) : '';
    $address = isset($_POST['address']) ? htmlspecialchars(trim($_POST['address'])) : '';
    $phone_number = isset($_POST['phone_number']) ? htmlspecialchars(trim($_POST['phone_number'])) : '';
    $email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
    $notes = isset($_POST['notes']) ? htmlspecialchars(trim($_POST['notes'])) : 'Tidak ada catatan';
    $payment_method = isset($_POST['payment_method']) ? htmlspecialchars(trim($_POST['payment_method'])) : '';

    // Validasi input form dasar
    $validation_errors = [];
    if (empty($full_name)) { $validation_errors[] = "Nama Lengkap tidak boleh kosong."; }
    if (empty($address)) { $validation_errors[] = "Alamat Pengiriman tidak boleh kosong."; }
    if (empty($phone_number)) { $validation_errors[] = "Nomor Telepon tidak boleh kosong."; }
    if (empty($payment_method) || ($payment_method != 'bank_transfer' && $payment_method != 'cod')) {
        $validation_errors[] = "Metode Pembayaran tidak valid atau belum dipilih.";
    }
    if (empty($checkout_items_raw)) {
        $validation_errors[] = "Tidak ada item produk di keranjang untuk diproses.";
    }

    if (!empty($validation_errors)) {
        $confirmation_message = "Validasi Gagal:\\n" . implode("\\n", $validation_errors);
        $confirmation_status_class = "error";
        $icon_class = "fas fa-exclamation-triangle";
    } else {
        // --- Validasi Stok dan Harga (PENTING untuk keamanan & integritas) ---
        $calculated_total_amount = 0;
        
        foreach ($checkout_items_raw as $id => $item_data) {
            $product_id = intval($item_data['id'] ?? 0);
            $product_name_from_form = htmlspecialchars($item_data['name'] ?? 'Unknown Product');
            $unit_price_form = floatval($item_data['price'] ?? 0);
            $quantity_requested = intval($item_data['quantity'] ?? 0);
            $store_id_string = htmlspecialchars($item_data['store_id_string'] ?? 'N/A');
            $store_name = htmlspecialchars($item_data['store_name'] ?? 'N/A');

            if ($product_id <= 0 || $quantity_requested <= 0) {
                $validation_errors[] = "Item '" . $product_name_from_form . "' memiliki ID atau kuantitas yang tidak valid.";
                continue;
            }

            // Ambil stok dan harga terbaru dari database
            $db_price = 0;
            $db_stock = 0;
            $stmt_product_info = mysqli_prepare($conn, "SELECT price, stock FROM products WHERE id = ?");
            if ($stmt_product_info) {
                mysqli_stmt_bind_param($stmt_product_info, "i", $product_id);
                mysqli_stmt_execute($stmt_product_info);
                mysqli_stmt_bind_result($stmt_product_info, $db_price, $db_stock);
                if (mysqli_stmt_fetch($stmt_product_info)) {
                    mysqli_stmt_close($stmt_product_info);

                    // Periksa stok
                    if ($quantity_requested > $db_stock) {
                        $validation_errors[] = "Stok untuk '" . $product_name_from_form . "' tidak mencukupi. Tersedia: " . $db_stock . ".";
                    }
                    // Periksa harga (gunakan harga DB jika ada tampering atau perbedaan)
                    $unit_price_to_use = (abs($unit_price_form - $db_price) > 0.01) ? $db_price : $unit_price_form;

                    $products_to_process[$product_id] = [
                        'id' => $product_id,
                        'name' => $product_name_from_form,
                        'unit_price' => $unit_price_to_use,
                        'quantity' => $quantity_requested,
                        'current_stock' => $db_stock,
                        'store_id_string' => $store_id_string,
                        'store_name' => $store_name
                    ];
                    $calculated_total_amount += ($unit_price_to_use * $quantity_requested);

                } else {
                    mysqli_stmt_close($stmt_product_info);
                    $validation_errors[] = "Produk '" . $product_name_from_form . "' tidak ditemukan di database.";
                }
            } else {
                $validation_errors[] = "Terjadi kesalahan sistem saat memverifikasi produk.";
            }
        } // End foreach checkout_items_raw

        if (!empty($validation_errors)) {
            $confirmation_message = "Validasi Produk Gagal:\\n" . implode("\\n", $validation_errors);
            $confirmation_status_class = "error";
            $icon_class = "fas fa-exclamation-triangle";
        } else {
            // Verifikasi total jumlah yang dihitung dengan yang dari form
            if (abs($calculated_total_amount - $total_checkout_amount_form) > 0.01) {
                $total_amount_for_order = $calculated_total_amount; // Gunakan yang dihitung server
                // Ini mungkin hanya warning, tidak harus error fatal, tergantung kebijakan
            } else {
                $total_amount_for_order = $total_checkout_amount_form;
            }

            // --- Mulai Transaksi Database ---
            mysqli_autocommit($conn, FALSE); // Matikan autocommit
            $success = true; // Set $success true di sini jika semua validasi lolos

            // 1. Insert into orders table
            $order_id = null;
            $order_status = 'pending'; // Status awal
            $sql_order = "INSERT INTO orders (user_id, total_amount, order_status, payment_method, delivery_address, customer_name, customer_phone, customer_email, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
                mysqli_stmt_bind_param($stmt_order, "idsssssss", $user_id, $total_amount_for_order, $order_status, $payment_method, $address, $full_name, $phone_number, $email, $notes);
                if (!mysqli_stmt_execute($stmt_order)) {
                    $success = false;
                    $confirmation_message = "Gagal menyimpan pesanan utama. " . mysqli_stmt_error($stmt_order);
                } else {
                    $order_id = mysqli_insert_id($conn);
                }
                mysqli_stmt_close($stmt_order);
            } else {
                $success = false;
                $confirmation_message = "Kesalahan internal: Tidak dapat menyiapkan query pesanan utama.";
            }

            // 2. Insert into order_items table and Update product stock for EACH item
            if ($success && $order_id) {
                foreach ($products_to_process as $item) {
                    // Update stock
                    $new_stock = $item['current_stock'] - $item['quantity'];
                    $sql_update_stock = "UPDATE products SET stock = ? WHERE id = ?";
                    if ($stmt_update_stock = mysqli_prepare($conn, $sql_update_stock)) {
                        mysqli_stmt_bind_param($stmt_update_stock, "ii", $new_stock, $item['id']);
                        if (!mysqli_stmt_execute($stmt_update_stock)) {
                            $success = false;
                            $confirmation_message = "Gagal memperbarui stok produk: " . $item['name'] . ". " . mysqli_stmt_error($stmt_update_stock);
                            break; // Keluar dari loop jika error
                        }
                        mysqli_stmt_close($stmt_update_stock);
                    } else {
                        $success = false;
                        $confirmation_message = "Kesalahan internal: Tidak dapat menyiapkan query update stok.";
                        break;
                    }

                    // Insert into order_items
                    $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, store_id, store_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    if ($stmt_item = mysqli_prepare($conn, $sql_item)) {
                        mysqli_stmt_bind_param($stmt_item, "iisddss", $order_id, $item['id'], $item['name'], $item['unit_price'], $item['quantity'], $item['store_id_string'], $item['store_name']);
                        if (!mysqli_stmt_execute($stmt_item)) {
                            $success = false;
                            $confirmation_message = "Gagal menyimpan detail item: " . $item['name'] . ". " . mysqli_stmt_error($stmt_item);
                            break; // Keluar dari loop jika error
                        }
                        mysqli_stmt_close($stmt_item);
                    } else {
                        $success = false;
                        $confirmation_message = "Kesalahan internal: Tidak dapat menyiapkan query item pesanan.";
                        break;
                    }
                } // End foreach products_to_process
            } // End if success && order_id for items

            // --- Commit or Rollback Transaction ---
            if ($success) {
                mysqli_commit($conn);
                $confirmation_message = "Pesanan Anda berhasil dikonfirmasi! ID Pesanan: #" . ($order_id ?? 'N/A') . ". Metode Pembayaran: " . (ucwords(str_replace('_', ' ', $payment_method)));
                $confirmation_status_class = "success";
                $icon_class = "fas fa-check-circle";
                
                // Hapus item dari sesi keranjang setelah checkout berhasil
                foreach ($products_to_process as $item) {
                    if (isset($_SESSION['cart'][$item['id']])) {
                        unset($_SESSION['cart'][$item['id']]);
                    }
                }

            } else {
                mysqli_rollback($conn);
                if (empty($confirmation_message)) { // Jika belum ada pesan error spesifik
                    $confirmation_message = "Terjadi kesalahan yang tidak diketahui saat memproses pesanan Anda. Mohon coba lagi.";
                }
                $confirmation_status_class = "error";
                $icon_class = "fas fa-times-circle";
            }

            mysqli_autocommit($conn, TRUE); // Re-enable autocommit
        } // End if !empty($validation_errors) for product stock/price validation
    } // End if !empty($validation_errors) for initial form data

} else {
    // Ini adalah blok jika akses langsung tanpa POST yang valid
    $confirmation_message = "Akses tidak sah atau data pesanan tidak lengkap (Permintaan tidak dikenali).";
    $confirmation_status_class = "error";
    $icon_class = "fas fa-exclamation-triangle";
}

end_process_html: // Label untuk goto jika ada error fatal di awal
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
            <p><?php echo nl2br(htmlspecialchars($confirmation_message)); ?></p> <?php if ($success): // Only show order details if successful ?>
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
                <p><strong>Metode Pembayaran:</strong> <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment_method))); ?></span></p>
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