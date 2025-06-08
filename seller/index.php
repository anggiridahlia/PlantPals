<?php
session_start();
include '../includes/auth_middleware.php'; // Path adjusted for subfolder
require_role('seller');
require_once '../config.php'; // Path adjusted for subfolder

$seller_id = $_SESSION['id'];

// Fetch summary data for seller dashboard
$seller_products_count = 0;
$seller_total_sales_amount = 0;
$seller_pending_orders = 0;

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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Penjual - PlantPals</title>
    <style>
        /* Shared basic styling with other backend pages */
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; flex-direction: column; min-height: 100vh; background: rgb(245, 255, 245); color: #2a4d3a; }
        .main-content { flex: 1; padding: 40px; }
        h1 { font-size: 2.8rem; margin-bottom: 30px; color: #386641; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); text-align: center; }
        .stat-card h3 { font-size: 1.8rem; color: #E5989B; margin-bottom: 10px; }
        .stat-card p { font-size: 1.1rem; color: #555; }
        /* Responsive */
        @media (max-width: 768px) { .main-content { padding: 20px; } h1 { font-size: 2.2rem; margin-bottom: 20px; } .stats-grid { grid-template-columns: 1fr; gap: 20px; } }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="main-content">
        <h1>Dashboard Penjual</h1>
        <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?> (Penjual)!</p>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Produk Anda</h3>
                <p><?php echo $seller_products_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Penjualan</h3>
                <p>Rp <?php echo number_format($seller_total_sales_amount, 0, ',', '.'); ?></p>
            </div>
            <div class="stat-card">
                <h3>Pesanan Pending</h3>
                <p><?php echo $seller_pending_orders; ?></p>
            </div>
        </div>

        <p style="margin-top: 30px;">Gunakan menu navigasi di atas untuk mengelola produk dan pesanan Anda.</p>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>