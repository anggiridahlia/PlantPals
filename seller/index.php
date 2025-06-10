<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
// Ini akan mengambil direktori dari file saat ini (seller/index.php)
// lalu naik satu level (ke PlantPals/)
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Sertakan file-file yang dibutuhkan dengan path yang lebih stabil
require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_once ROOT_PATH . 'config.php'; // config.php ada di root PlantPals/

require_role('seller'); // Memastikan user adalah seller

$seller_id = $_SESSION['id'];

// Fetch summary data for seller dashboard
$seller_products_count = 0;
$seller_total_sales_amount = 0;
$seller_pending_orders = 0;

$result_products = mysqli_query($conn, "SELECT COUNT(id) AS count FROM products WHERE seller_id = $seller_id");
if ($result_products) { $seller_products_count = mysqli_fetch_assoc($result_products)['count']; }

// Total sales amount for this seller's products (simplified: sum of completed order items)
// Join with order_items and orders to filter by seller_id AND order_status
$sql_sales = "SELECT SUM(oi.unit_price * oi.quantity) AS total_sales
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              JOIN orders o ON oi.order_id = o.id
              WHERE p.seller_id = ? AND o.order_status = 'completed'";
if ($stmt_sales = mysqli_prepare($conn, $sql_sales)) {
    mysqli_stmt_bind_param($stmt_sales, "i", $seller_id);
    mysqli_stmt_execute($stmt_sales);
    mysqli_stmt_bind_result($stmt_sales, $total_sales);
    mysqli_stmt_fetch($stmt_sales);
    $seller_total_sales_amount = $total_sales ?? 0;
    mysqli_stmt_close($stmt_sales);
}

// Count pending orders that include this seller's products
// Use DISTINCT to count unique orders, even if multiple of seller's items are in it
$sql_pending_orders = "SELECT COUNT(DISTINCT o.id) AS count
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN products p ON oi.product_id = p.id
                       WHERE p.seller_id = ? AND o.order_status = 'pending'";
if ($stmt_pending = mysqli_prepare($conn, $sql_pending_orders)) {
    mysqli_stmt_bind_param($stmt_pending, "i", $seller_id);
    mysqli_stmt_execute($stmt_pending);
    mysqli_stmt_bind_result($stmt_pending, $pending_count);
    mysqli_stmt_fetch($stmt_pending);
    $seller_pending_orders = $pending_count;
    mysqli_stmt_close($stmt_pending);
}

mysqli_close($conn);
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Dashboard Penjual</h1>
    <p>Selamat datang, **<?php echo htmlspecialchars($_SESSION['username']); ?>** (Penjual)!</p>

    <div class="stats-grid">
        <div class="stat-card card-panel">
            <h3>Total Produk Anda</h3>
            <p><?php echo $seller_products_count; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Total Penjualan Anda</h3>
            <p>Rp <?php echo number_format($seller_total_sales_amount, 0, ',', '.'); ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Pesanan Pending</h3>
            <p><?php echo $seller_pending_orders; ?></p>
        </div>
    </div>

    <p style="margin-top: 30px; text-align: center; color: #555;">Gunakan menu navigasi di atas untuk mengelola produk dan pesanan Anda.</p>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>