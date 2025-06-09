<?php
session_start();
include '../includes/auth_middleware.php';
require_role('admin');
require_once '../config.php';

// Handle logic to update order status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['new_status']);
    
    // Validate status to prevent SQL injection for ENUM type
    $allowed_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        die("Status tidak valid.");
    }

    $stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: orders.php"); // Redirect after update
    exit;
}

// Fetch all orders with user and product details for display
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

mysqli_close($conn);
?>
<?php include '../includes/header.php'; ?>

    <h1>Manajemen Pesanan</h1>

    <?php if (empty($orders)): ?>
        <p class="card-panel">Belum ada pesanan dalam database.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <div class="order-card card-panel">
            <h3>Pesanan #<?php echo htmlspecialchars($order['id']); ?></h3>
            <p><strong>Pembeli:</strong> <?php echo htmlspecialchars($order['buyer_username']); ?></p>
            <p><strong>Total Jumlah:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
            <p><strong>Status Pesanan:</strong>
                <span class="status-badge <?php echo htmlspecialchars($order['order_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span>
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
            <p><strong>Kontak Pelanggan:</strong> <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo htmlspecialchars($order['customer_phone']); ?>)</p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            <p><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
            <p><strong>Tanggal Pesanan:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></p>
            
            <h4>Item Pesanan:</h4>
            <ul>
                <?php foreach ($order['items'] as $item): ?>
                    <li>- <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['quantity']); ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> dari <?php echo htmlspecialchars($item['store_name']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php include '../includes/footer.php'; ?>