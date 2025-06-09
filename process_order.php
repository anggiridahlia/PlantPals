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
include 'data.php'; // For store information if needed

// Retrieve POST data from the order form
$product_name_from_form = isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : 'N/A';
$product_price_from_form = isset($_POST['product_price']) ? floatval($_POST['product_price']) : 0; // Use floatval for price
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1; // Get quantity, default to 1
$store_id_from_form = isset($_POST['store_id']) ? htmlspecialchars($_POST['store_id']) : 'N/A';
$store_name_full_from_form = isset($_POST['store_name_full']) ? htmlspecialchars($_POST['store_name_full']) : 'N/A';

$full_name = isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : 'N/A';
$address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : 'N/A';
$phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : 'N/A';
$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'N/A';
$notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : 'Tidak ada';

// Basic validation for quantity
if ($quantity < 1) {
    echo "<script>alert('Kuantitas produk tidak boleh kurang dari 1.'); window.history.back();</script>";
    exit;
}

// --- Find product_id and current stock from database based on product_name ---
$product_id_db = null;
$db_product_price = 0;
$db_product_stock = 0;
$seller_of_product_id = null; // To check seller_id for updating stock

$stmt_product_info = mysqli_prepare($conn, "SELECT id, price, stock, seller_id FROM products WHERE name = ?");
if ($stmt_product_info) {
    mysqli_stmt_bind_param($stmt_product_info, "s", $product_name_from_form);
    mysqli_stmt_execute($stmt_product_info);
    mysqli_stmt_bind_result($stmt_product_info, $product_id_db, $db_product_price, $db_product_stock, $seller_of_product_id);
    if (mysqli_stmt_fetch($stmt_product_info)) {
        // Product found
    } else {
        echo "<script>alert('Produk tidak ditemukan di database. Pesanan tidak dapat diproses.'); window.location.href='dashboard.php';</script>";
        exit;
    }
    mysqli_stmt_close($stmt_product_info);
} else {
    error_log("Error preparing product info statement: " . mysqli_error($conn));
    echo "<script>alert('Terjadi kesalahan sistem saat mengambil info produk. Mohon coba lagi.'); window.location.href='dashboard.php';</script>";
    exit;
}

// --- Stock Check ---
if ($quantity > $db_product_stock) {
    echo "<script>alert('Stok produk " . htmlspecialchars($product_name_from_form) . " tidak mencukupi. Tersedia: " . htmlspecialchars($db_product_stock) . ".'); window.history.back();</script>";
    exit;
}
// Optionally, cross-check price from DB vs form to prevent tampering.
// For now, we trust the DB price ($db_product_price) if found.
$actual_unit_price = $db_product_price;


// --- Start Transaction (for atomicity) ---
mysqli_autocommit($conn, FALSE);
$success = true;

// 1. Insert into orders table
$order_id = null;
$total_amount = $actual_unit_price * $quantity;

$sql_order = "INSERT INTO orders (user_id, total_amount, delivery_address, customer_name, customer_phone, customer_email, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
    mysqli_stmt_bind_param($stmt_order, "idsssss", $user_id, $total_amount, $address, $full_name, $phone_number, $email, $notes);
    if (mysqli_stmt_execute($stmt_order)) {
        $order_id = mysqli_insert_id($conn); // Get the ID of the newly inserted order
    } else {
        $success = false;
        error_log("Error inserting into orders table: " . mysqli_stmt_error($stmt_order));
    }
    mysqli_stmt_close($stmt_order);
} else {
    $success = false;
    error_log("Error preparing order insert statement: " . mysqli_error($conn));
}

// 2. Insert into order_items table (only if order was successfully created)
if ($success && $order_id) {
    $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, store_id, store_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt_item = mysqli_prepare($conn, $sql_item)) {
        mysqli_stmt_bind_param($stmt_item, "iisddss", $order_id, $product_id_db, $product_name_from_form, $actual_unit_price, $quantity, $store_id_from_form, $store_name_full_from_form);
        if (!mysqli_stmt_execute($stmt_item)) {
            $success = false;
            error_log("Error inserting into order_items table: " . mysqli_stmt_error($stmt_item));
        }
        mysqli_stmt_close($stmt_item);
    } else {
        $success = false;
        error_log("Error preparing order_items insert statement: " . mysqli_error($conn));
    }
} else {
    $success = false;
}

// 3. Update product stock (only if order and order_item were successfully created)
if ($success) {
    $new_stock = $db_product_stock - $quantity;
    $sql_update_stock = "UPDATE products SET stock = ? WHERE id = ?";
    if ($stmt_update_stock = mysqli_prepare($conn, $sql_update_stock)) {
        mysqli_stmt_bind_param($stmt_update_stock, "ii", $new_stock, $product_id_db);
        if (!mysqli_stmt_execute($stmt_update_stock)) {
            $success = false;
            error_log("Error updating product stock: " . mysqli_stmt_error($stmt_update_stock));
        }
        mysqli_stmt_close($stmt_update_stock);
    } else {
        $success = false;
        error_log("Error preparing stock update statement: " . mysqli_error($conn));
    }
}


// --- Commit or Rollback Transaction ---
if ($success) {
    mysqli_commit($conn);
    $confirmation_message = "Pesanan Anda berhasil dikonfirmasi!";
    $confirmation_status_class = "success";
} else {
    mysqli_rollback($conn);
    $confirmation_message = "Terjadi kesalahan saat memproses pesanan Anda. Mohon coba lagi.";
    $confirmation_status_class = "error";
    // Error logging is already done above.
}

mysqli_autocommit($conn, TRUE); // Re-enable autocommit
mysqli_close($conn);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/order_form_styles.css">
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
                <?php echo ($confirmation_status_class == 'success') ? '🎉 Pesanan Terkonfirmasi! 🎉' : '❌ Gagal Memproses Pesanan ❌'; ?>
            </h2>
            <p><?php echo $confirmation_message; ?></p>

            <?php if ($success): // Only show order details if successful ?>
            <div class="order-details-summary">
                <h3>Detail Produk:</h3>
                <p><strong>Nama Produk:</strong> <span><?php echo htmlspecialchars($product_name_from_form); ?></span></p>
                <p><strong>Harga Satuan:</strong> <span>Rp <?php echo number_format($actual_unit_price, 0, ',', '.'); ?></span></p>
                <p><strong>Kuantitas:</strong> <span><?php echo htmlspecialchars($quantity); ?></span></p>
                <p><strong>Total Pembayaran:</strong> <span>Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></span></p>
                <p><strong>Dari Toko:</strong> <span><?php echo htmlspecialchars($store_name_full_from_form); ?></span></p>

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
        <a href="/PlantPals/dashboard.php?page=home" class="back-to-dashboard-btn">Kembali ke Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>
</body>
</html>