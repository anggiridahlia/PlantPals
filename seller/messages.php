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

$seller_id = $_SESSION['id']; // Dapatkan ID penjual yang sedang login

// Fetch messages received by this seller
$messages = [];
$sql_messages = "SELECT m.*, u.username as sender_username, st.name as related_store_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN stores st ON m.store_id = st.id
                WHERE m.receiver_id = ?
                ORDER BY m.sent_at DESC";

if ($stmt_messages = mysqli_prepare($conn, $sql_messages)) {
    mysqli_stmt_bind_param($stmt_messages, "i", $seller_id);
    mysqli_stmt_execute($stmt_messages);
    $result_messages = mysqli_stmt_get_result($stmt_messages);
    while ($row = mysqli_fetch_assoc($result_messages)) {
        $messages[] = $row;
    }
    mysqli_stmt_close($stmt_messages);
} else {
    error_log("Error preparing seller messages fetch statement: " . mysqli_error($conn));
}

// Handle message reply/marking as read (basic functionality)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'mark_as_read' && isset($_POST['message_id'])) {
        $message_id = intval($_POST['message_id']);
        $stmt_read = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        if ($stmt_read) {
            mysqli_stmt_bind_param($stmt_read, "ii", $message_id, $seller_id);
            if (!mysqli_stmt_execute($stmt_read)) {
                error_log("Failed to mark message as read: " . mysqli_stmt_error($stmt_read));
            }
            mysqli_stmt_close($stmt_read);
        } else {
            error_log("Error preparing mark as read statement: " . mysqli_error($conn));
        }
        header("Location: messages.php"); // Refresh page after update
        exit;
    }
    // Add logic for replying to messages later if needed
}

mysqli_close($conn);
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Pesan Masuk</h1>

    <?php if (empty($messages)): ?>
        <p class="card-panel">Anda belum memiliki pesan masuk.</p>
    <?php else: ?>
        <ul class="message-list" style="list-style: none; padding: 0;">
            <?php foreach ($messages as $message): ?>
                <li class="card-panel" style="margin-bottom: 20px; border-left: 5px solid <?php echo $message['is_read'] ? '#ccc' : '#D60050'; ?>;">
                    <div class="message-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dashed #eee;">
                        <strong style="font-size: 1.1rem; color: #000;">Dari: <?php echo htmlspecialchars($message['sender_username']); ?></strong>
                        <span style="font-size: 0.85rem; color: #777;">Pada: <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($message['sent_at']))); ?></span>
                    </div>
                    <p style="font-size: 0.95rem; color: #555; margin-bottom: 10px;"><strong>Subjek:</strong> <?php echo htmlspecialchars($message['subject'] ?? 'Tidak ada subjek'); ?></p>
                    <p style="font-size: 0.95rem; color: #444; line-height: 1.6; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    <?php if ($message['related_store_name']): ?>
                        <p style="font-size: 0.9em; color: #777; margin-bottom: 10px;">(Terkait Toko: <?php echo htmlspecialchars($message['related_store_name']); ?>)</p>
                    <?php endif; ?>
                    
                    <?php if (!$message['is_read']): ?>
                        <form action="messages.php" method="post" style="text-align: right;">
                            <input type="hidden" name="action" value="mark_as_read">
                            <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($message['id']); ?>">
                            <button type="submit" class="btn" style="background-color: #007BFF; color: white; border: none; padding: 8px 15px; cursor: pointer;">Tandai Sudah Dibaca</button>
                        </form>
                    <?php else: ?>
                        <p style="text-align: right; font-size: 0.9em; color: #28A745;"><i class="fas fa-check-double"></i> Sudah Dibaca</p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>