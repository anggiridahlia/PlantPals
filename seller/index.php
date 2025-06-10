<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_role('seller'); // Memastikan user adalah seller
require_once ROOT_PATH . 'config.php'; // config.php ada di root PlantPals/

$seller_id = $_SESSION['id'];

// Fetch summary data for seller dashboard
$seller_products_count = 0;
$seller_total_sales_amount = 0;
$seller_pending_orders = 0;
$seller_total_reviews = 0; // NEW: Total reviews
$seller_average_rating = 0; // NEW: Average rating

$result_products = mysqli_query($conn, "SELECT COUNT(id) AS count FROM products WHERE seller_id = $seller_id");
if ($result_products) { $seller_products_count = mysqli_fetch_assoc($result_products)['count']; }

// Total sales amount for this seller's products (simplified: sum of completed order items)
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
$sql_pending_orders = "SELECT COUNT(DISTINCT o.id) AS count
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN products p ON oi.product_id = p.id
                       WHERE p.seller_id = ? AND (o.order_status = 'pending' OR o.order_status = 'processing')"; // Include processing for pending state
if ($stmt_pending = mysqli_prepare($conn, $sql_pending_orders)) {
    mysqli_stmt_bind_param($stmt_pending, "i", $seller_id);
    mysqli_stmt_execute($stmt_pending);
    mysqli_stmt_bind_result($stmt_pending, $pending_count);
    mysqli_stmt_fetch($stmt_pending);
    $seller_pending_orders = $pending_count;
    mysqli_stmt_close($stmt_pending);
}

// NEW: Fetch total reviews and average rating for this seller's products
$sql_reviews_stats = "SELECT COUNT(pr.id) AS total_reviews, AVG(pr.rating) AS avg_rating
                      FROM product_reviews pr
                      JOIN products p ON pr.product_id = p.id
                      WHERE p.seller_id = ?";
if ($stmt_reviews_stats = mysqli_prepare($conn, $sql_reviews_stats)) {
    mysqli_stmt_bind_param($stmt_reviews_stats, "i", $seller_id);
    mysqli_stmt_execute($stmt_reviews_stats);
    mysqli_stmt_bind_result($stmt_reviews_stats, $total_reviews, $avg_rating);
    mysqli_stmt_fetch($stmt_reviews_stats);
    $seller_total_reviews = $total_reviews ?? 0;
    $seller_average_rating = round($avg_rating ?? 0, 1);
    mysqli_stmt_close($stmt_reviews_stats);
}


mysqli_close($conn);
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Dashboard Penjual</h1>
    <p>Selamat datang, **<?php echo htmlspecialchars($_SESSION['username']); ?>** (Penjual)!</p>

    <div class="stats-grid">
        <div class="stat-card card-panel">
            <h3>Total Produk Anda <i class="fas fa-boxes"></i></h3>
            <p class="stat-value"><?php echo $seller_products_count; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Total Penjualan Anda <i class="fas fa-money-bill-wave"></i></h3>
            <p class="stat-value">Rp <?php echo number_format($seller_total_sales_amount, 0, ',', '.'); ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Pesanan Pending <i class="fas fa-hourglass-half"></i></h3>
            <p class="stat-value"><?php echo $seller_pending_orders; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Ulasan Produk <i class="fas fa-star"></i></h3>
            <p class="stat-value">
                <?php echo $seller_average_rating; ?>/5 (<?php echo $seller_total_reviews; ?>)
            </p>
        </div>
    </div>

    <p style="margin-top: 30px; text-align: center; color: #555;">Gunakan menu navigasi di atas untuk mengelola produk dan pesanan Anda.</p>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>