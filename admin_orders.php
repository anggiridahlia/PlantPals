<?php
session_start();
include 'includes/auth_middleware.php';
require_role('admin');
require_once 'config.php';

// Fetch all orders with user and product details
$orders = [];
$sql = "SELECT o.*, u.username as buyer_username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Fetch order items for each order
        $order_items = [];
        $item_sql = "SELECT * FROM order_items WHERE order_id = ?";
        if ($item_stmt = mysqli_prepare($conn, $item_sql)) {
            mysqli_stmt_bind_param($item_stmt, "i", $row['id']);
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
}

// Logic to update order status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: admin_orders.php"); // Redirect after update
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan Admin - PlantPals</title>
    <style>
        /* Basic styling */
        body { font-family: sans-serif; margin: 20px; }
        .order-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; background-color: #fff; }
        .order-card h3 { margin-top: 0; }
        .order-status-select { margin-left: 10px; padding: 5px; border-radius: 4px; }
        .update-btn { padding: 5px 10px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <h1>Manajemen Pesanan Admin</h1>

    <?php if (empty($orders)): ?>
        <p>Belum ada pesanan.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <h3>Pesanan #<?php echo $order['id']; ?> (Pembeli: <?php echo $order['buyer_username']; ?>)</h3>
            <p>Total: Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
            <p>Status:
                <strong><?php echo $order['order_status']; ?></strong>
                <form action="admin_orders.php" method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
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
            <p>Alamat: <?php echo $order['delivery_address']; ?></p>
            <p>Kontak: <?php echo $order['customer_name']; ?> (<?php echo $order['customer_phone']; ?>)</p>
            <h4>Item Pesanan:</h4>
            <ul>
                <?php foreach ($order['items'] as $item): ?>
                    <li>- <?php echo $item['product_name']; ?> (<?php echo $item['quantity']; ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> dari <?php echo $item['store_name']; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php mysqli_close($conn); ?>