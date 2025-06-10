<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_role('seller');
require_once ROOT_PATH . 'config.php';

$seller_id = $_SESSION['id']; // Dapatkan ID penjual yang sedang login

// Logic to update order status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['new_status']);

    // Validate status
    $allowed_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo "<script>alert('Status tidak valid.'); window.history.back();</script>";
        exit;
    }

    // SECURITY: Check if this order contains products from this seller before updating
    $check_sql = "SELECT COUNT(oi.id)
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = ? AND p.seller_id = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $seller_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $item_count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);

        if ($item_count > 0) { // Only update if order has items from this seller
            $stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
                if (!mysqli_stmt_execute($stmt)) {
                    error_log("Error executing order status update in seller/orders.php: " . mysqli_stmt_error($stmt));
                    echo "<script>alert('Terjadi kesalahan saat memperbarui status pesanan. Detail: " . mysqli_stmt_error($stmt) . " Mohon coba lagi.'); window.history.back();</script>";
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing order status update in seller/orders.php: " . mysqli_error($conn));
                echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan pembaruan status. Mohon coba lagi.'); window.history.back();</script>";
            }
        } else {
            error_log("Seller " . $seller_id . " attempted to update order " . $order_id . " which does not contain their products.");
            echo "<script>alert('Anda tidak diizinkan untuk memperbarui pesanan ini.'); window.history.back();</script>";
        }
    } else {
        error_log("Error preparing check_sql in seller/orders.php: " . mysqli_error($conn));
        echo "<script>alert('Terjadi kesalahan sistem saat memverifikasi pesanan. Mohon coba lagi.'); window.history.back();</script>";
    }
    header("Location: orders.php");
    exit;
}

// Fetch all orders with user and product details for display
// SQL to fetch orders that contain at least one product from this seller
// Using DISTINCT to avoid duplicate orders if multiple items from same seller are in one order
$orders = [];
// UBAH: Tambahkan payment_method ke query
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
        // Fetch only items from this seller for this specific order
        $order_items = [];
        $item_sql = "SELECT oi.*
                     FROM order_items oi
                     JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id = ? AND p.seller_id = ?";
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
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Pesanan untuk Produk Anda</h1>

    <?php if (empty($orders)): ?>
        <p class="card-panel">Belum ada pesanan yang melibatkan produk Anda.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <div class="order-card card-panel">
            <h3>Pesanan #<?php echo htmlspecialchars($order['id']); ?></h3>
            <p><strong><i class="fas fa-user"></i> Pembeli:</strong> <?php echo htmlspecialchars($order['buyer_username']); ?></p>
            <p><strong><i class="fas fa-truck"></i> Alamat Pengiriman:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
            <p><strong><i class="fas fa-phone"></i> Kontak Pembeli:</strong> <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo htmlspecialchars($order['customer_phone']); ?>)</p>
            <p><strong><i class="fas fa-at"></i> Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            <p><strong><i class="fas fa-sticky-note"></i> Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
            <p><strong><i class="fas fa-calendar-alt"></i> Tanggal Pesanan:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></p>
            <p><strong><i class="fas fa-dollar-sign"></i> Total Jumlah Pesanan:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
            <p><strong><i class="fas fa-credit-card"></i> Metode Pembayaran:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'))); ?></p>

            <p><strong><i class="fas fa-info-circle"></i> Status Pesanan:</strong>
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
                    <button type="submit" class="update-btn"><i class="fas fa-sync-alt"></i> Update</button>
                </form>
            </p>
            
            <h4>Produk Anda dalam Pesanan Ini:</h4>
            <ul>
                <?php if (empty($order['items'])): ?>
                    <li>Tidak ada produk dari toko Anda dalam pesanan ini (kemungkinan error data).</li>
                <?php else: ?>
                    <?php foreach ($order['items'] as $item): ?>
                        <li>- <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['quantity']); ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> dari <?php echo htmlspecialchars($item['store_name']); ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php mysqli_close($conn); ?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>