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

// Retrieve POST data from the order form (using PHP 5.6 compatible syntax)
$product_name_from_form = isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : 'N/A';
$product_price_from_form = isset($_POST['product_price']) ? htmlspecialchars($_POST['product_price']) : '0';
$store_id_from_form = isset($_POST['store_id']) ? htmlspecialchars($_POST['store_id']) : 'N/A';
$store_name_full_from_form = isset($_POST['store_name_full']) ? htmlspecialchars($_POST['store_name_full']) : 'N/A';

$full_name = isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : 'N/A';
$address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : 'N/A';
$phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : 'N/A';
$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'N/A';
$notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : 'Tidak ada';

// --- Find product_id from database based on product_name ---
$product_id_db = null;
$stmt_product_id = mysqli_prepare($conn, "SELECT id, price FROM products WHERE name = ?");
if ($stmt_product_id) {
    mysqli_stmt_bind_param($stmt_product_id, "s", $product_name_from_form);
    mysqli_stmt_execute($stmt_product_id);
    mysqli_stmt_bind_result($stmt_product_id, $db_product_id, $db_product_price);
    if (mysqli_stmt_fetch($stmt_product_id)) {
        $product_id_db = $db_product_id;
        // Optionally, cross-check price from DB vs form to prevent tampering
        // For simplicity, we trust form data for price here, but in real app, use $db_product_price
    }
    mysqli_stmt_close($stmt_product_id);
}

if ($product_id_db === null) {
    // Product not found in database, handle error or redirect
    echo "<script>alert('Produk tidak ditemukan di database. Pesanan tidak dapat diproses.'); window.location.href='dashboard.php';</script>";
    exit;
}

// --- Start Transaction (for atomicity) ---
mysqli_autocommit($conn, FALSE);
$success = true;

// 1. Insert into orders table
$order_id = null;
$total_amount = $product_price_from_form * 1; // Assuming quantity 1 for simplicity in this flow

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
    $quantity = 1; // Always 1 for now, as it's single product per order form
    $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, store_id, store_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt_item = mysqli_prepare($conn, $sql_item)) {
        mysqli_stmt_bind_param($stmt_item, "iisddss", $order_id, $product_id_db, $product_name_from_form, $product_price_from_form, $quantity, $store_id_from_form, $store_name_full_from_form);
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


// --- Commit or Rollback Transaction ---
if ($success) {
    mysqli_commit($conn);
    $confirmation_message = "Pesanan Anda berhasil dikonfirmasi!";
    $confirmation_status_class = "success";
} else {
    mysqli_rollback($conn);
    $confirmation_message = "Terjadi kesalahan saat memproses pesanan Anda. Mohon coba lagi.";
    $confirmation_status_class = "error";
    // Log error details for debugging in a real application - already done above
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
                <p><strong>Harga:</strong> <span>Rp <?php echo number_format($product_price_from_form, 0, ',', '.'); ?></span></p>
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