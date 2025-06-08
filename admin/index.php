<?php
session_start();
include '../includes/auth_middleware.php';
require_role('admin');
require_once '../config.php';

// Fetch some summary data for admin dashboard
$total_users = 0;
$total_products = 0;
$total_orders = 0;
$pending_orders = 0;

$result_users = mysqli_query($conn, "SELECT COUNT(id) AS count FROM users");
if ($result_users) { $total_users = mysqli_fetch_assoc($result_users)['count']; }

$result_products = mysqli_query($conn, "SELECT COUNT(id) AS count FROM products");
if ($result_products) { $total_products = mysqli_fetch_assoc($result_products)['count']; }

$result_orders = mysqli_query($conn, "SELECT COUNT(id) AS count FROM orders");
if ($result_orders) { $total_orders = mysqli_fetch_assoc($result_orders)['count']; }

$result_pending = mysqli_query($conn, "SELECT COUNT(id) AS count FROM orders WHERE order_status = 'pending'");
if ($result_pending) { $pending_orders = mysqli_fetch_assoc($result_pending)['count']; }

mysqli_close($conn);
?>
<?php include '../includes/header.php'; ?>

    <h1>Dashboard Admin</h1>
    <p>Selamat datang, **<?php echo htmlspecialchars($_SESSION['username']); ?>** (Admin)!</p>

    <div class="stats-grid">
        <div class="stat-card card-panel">
            <h3>Total Pengguna</h3>
            <p><?php echo $total_users; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Total Produk</h3>
            <p><?php echo $total_products; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Total Pesanan</h3>
            <p><?php echo $total_orders; ?></p>
        </div>
        <div class="stat-card card-panel">
            <h3>Pesanan Pending</h3>
            <p><?php echo $pending_orders; ?></p>
        </div>
    </div>

    <p style="margin-top: 30px; text-align: center; color: #555;">Gunakan menu navigasi di atas untuk mengelola sistem.</p>

<?php include '../includes/footer.php'; ?>