<?php
session_start();
include '../includes/auth_middleware.php';
require_role('seller');
require_once '../config.php';

$seller_id = $_SESSION['id'];

$orders = [];
// SQL to fetch orders that contain at least one product from this seller
$sql = "SELECT DISTINCT o.*, u.username as buyer_username
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
        ORDER BY o.order_date DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $seller_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        // Fetch only items from this seller for this order
        $order_items = [];
        $item_sql = "SELECT oi.* FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND p.seller_id = ?";
        if ($item_stmt = mysqli_prepare($conn, $item_sql)) {
            mysqli_stmt_bind_param($item_stmt, "ii", $row['id'], $seller_id);
            mysqli_stmt_execute($item_stmt);
            $item_result = mysqli_stmt_get_result($item_stmt);
            while ($item_row = mysqli_fetch_assoc($item_result)) {
                $order_items[] = $item_row;
            }
            mysqli_stmt_close($item_stmt);
        }
        $row['items'] = $order_items;
        $orders[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Logic to update order status (similar to admin, but needs to check if order items belong to this seller)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['new_status']);

    // SECURITY: Check if this order contains products from this seller before updating
    $check_sql = "SELECT COUNT(oi.id) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND p.seller_id = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $seller_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $item_count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);

        if ($item_count > 0) { // Only update if order has items from this seller
            $stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    header("Location: orders.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Anda - PlantPals</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; flex-direction: column; min-height: 100vh; background: rgb(245, 255, 245); color: #2a4d3a; }
        .main-content { flex: 1; padding: 40px; }
        h1 { font-size: 2.8rem; margin-bottom: 30px; color: #386641; }
        .order-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .order-card h3 { font-size: 1.8rem; margin-top: 0; margin-bottom: 15px; color: #386641; }
        .order-card p { margin-bottom: 8px; font-size: 1.1rem; }
        .order-card p strong { color: #2f5d3a; }
        .order-card h4 { margin-top: 15px; margin-bottom: 10px; font-size: 1.3rem; color: #386641; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .order-card ul { list-style: none; padding: 0; margin-left: 20px; }
        .order-card li { margin-bottom: 5px; font-size: 1rem; color: #555; }
        .order-status-form { display: inline-block; margin-left: 15px; }
        .order-status-select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 0.95rem; }
        .update-btn { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.95rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            h1 { font-size: 2.2rem; }
            .order-card { padding: 15px; }
            .order-card h3 { font-size: 1.5rem; }
            .order-card p { font-size: 1rem; }
            .order-card h4 { font-size: 1.2rem; }
            .order-status-form { display: block; margin-left: 0; margin-top: 10px; }
            .order-status-select, .update-btn { width: 100%; margin-top: 5px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="main-content">
        <h1>Pesanan untuk Produk Anda</h1>

        <?php if (empty($orders)): ?>
            <p>Belum ada pesanan yang melibatkan produk Anda.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <h3>Pesanan #<?php echo htmlspecialchars($order['id']); ?></h3>
                <p><strong>Pembeli:</strong> <?php echo htmlspecialchars($order['buyer_username']); ?></p>
                <p><strong>Status Pesanan:</strong>
                    <strong><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></strong>
                    <form action="orders.php" method="post" class="order-status-form">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                        <select name="new_status" class="order-status-select">
                            <option value="pending" <?php echo ($order['order_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo ($order['order_status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo ($order['order_status'] == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                            <option value="completed" <?php echo ($order['order_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($order['order_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="update-btn">Update</button>
                    </form>
                </p>
                <p><strong>Alamat Pengiriman:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                <p><strong>Kontak Pembeli:</strong> <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo htmlspecialchars($order['customer_phone']); ?>)</p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                <p><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                <p><strong>Tanggal Pesanan:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></p>
                
                <h4>Produk Anda dalam Pesanan Ini:</h4>
                <ul>
                    <?php foreach ($order['items'] as $item): ?>
                        <li>- <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['quantity']); ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> (dari toko: <?php echo htmlspecialchars($item['store_name']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>