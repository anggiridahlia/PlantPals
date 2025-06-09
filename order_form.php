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

// Include data.php to get store names if needed
include 'data.php';

// Get product data from URL parameters (PHP 5.6 compatible)
$product_name = isset($_GET['product_name']) ? htmlspecialchars($_GET['product_name']) : 'Produk Tidak Dikenal';
$product_price = isset($_GET['product_price']) ? htmlspecialchars($_GET['product_price']) : '0';
$store_id = isset($_GET['store_id']) ? htmlspecialchars($_GET['store_id']) : '';
$store_name_full = isset($_GET['store_name']) ? htmlspecialchars($_GET['store_name']) : 'Toko Tidak Dikenal'; // Full name from URL

// You might want to strip the address from store_name_full for display if needed
$store_display_name = $store_name_full;
preg_match('/^(.*?) - \(.*\)$/', $store_name_full, $matches);
if (isset($matches[1])) {
    $store_display_name = trim($matches[1]);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pemesanan - PlantPals</title>
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
        <div class="order-form-container">
            <h2>Lengkapi Detail Pemesanan</h2>
            <div class="order-summary">
                <p><strong>Produk:</strong> <?php echo htmlspecialchars($product_name); ?></p>
                <p><strong>Harga:</strong> Rp <?php echo number_format($product_price, 0, ',', '.'); ?></p>
                <p><strong>Toko:</strong> <?php echo htmlspecialchars($store_display_name); ?></p>
            </div>

            <form action="process_order.php" method="post">
                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>">
                <input type="hidden" name="product_price" value="<?php echo htmlspecialchars($product_price); ?>">
                <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($store_id); ?>">
                <input type="hidden" name="store_name_full" value="<?php echo htmlspecialchars($store_name_full); ?>">

                <div class="form-group">
                    <label for="full_name">Nama Lengkap:</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="address">Alamat Lengkap Pengiriman:</label>
                    <textarea id="address" name="address" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="phone_number">Nomor Telepon (WhatsApp):</label>
                    <input type="tel" id="phone_number" name="phone_number" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="notes">Catatan Tambahan (Opsional):</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>

                <button type="submit" class="submit-btn">Konfirmasi Pesanan</button>
            </form>
        </div>
        <a href="/PlantPals/dashboard.php?page=home" class="back-to-dashboard-btn">Kembali ke Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>
</body>
</html>