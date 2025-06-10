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
$seller_username = $_SESSION['username']; // Dapatkan username penjual

$messages = [];
$selected_conversation_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; // NEW: ID pembeli untuk percakapan yang dipilih
$selected_conversation_username = ''; // NEW: Username pembeli untuk percakapan yang dipilih
$target_store_id_for_reply = 0; // NEW: Store ID terkait pesan untuk balasan

// Handle message reply or marking as read
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
        // Redirect back with selected user_id to maintain conversation view
        header("Location: messages.php" . ($selected_conversation_user_id ? "?user_id=" . $selected_conversation_user_id : ""));
        exit;
    } elseif ($_POST['action'] == 'send_reply' && isset($_POST['receiver_user_id']) && isset($_POST['reply_message'])) {
        $receiver_user_id = intval($_POST['receiver_user_id']); // ID pembeli yang dibalas
        $reply_message_content = trim($_POST['reply_message']);
        $reply_subject = trim($_POST['reply_subject'] ?? 'Balasan Pesan'); // Subjek balasan
        $related_store_id = intval($_POST['related_store_id'] ?? 0); // Store ID terkait

        if ($receiver_user_id > 0 && !empty($reply_message_content)) {
            $sql_insert_reply = "INSERT INTO messages (sender_id, receiver_id, store_id, subject, message) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_insert_reply = mysqli_prepare($conn, $sql_insert_reply)) {
                // Sender is seller_id, Receiver is the buyer's user_id
                $store_id_param = ($related_store_id > 0) ? $related_store_id : NULL;
                mysqli_stmt_bind_param($stmt_insert_reply, "iiiss", $seller_id, $receiver_user_id, $store_id_param, $reply_subject, $reply_message_content);
                if (!mysqli_stmt_execute($stmt_insert_reply)) {
                    error_log("Failed to send reply: " . mysqli_stmt_error($stmt_insert_reply));
                }
                mysqli_stmt_close($stmt_insert_reply);
            } else {
                error_log("Error preparing reply insert statement: " . mysqli_error($conn));
            }
        }
        // Redirect back to the same conversation
        header("Location: messages.php" . ($receiver_user_id ? "?user_id=" . $receiver_user_id : ""));
        exit;
    }
}


// Fetch unique users who sent messages to this seller
$conversation_users = [];
$sql_conversation_users = "SELECT DISTINCT u.id, u.username
                          FROM messages m
                          JOIN users u ON m.sender_id = u.id
                          WHERE m.receiver_id = ? AND u.role = 'buyer'
                          ORDER BY m.sent_at DESC"; // Order by latest message for sender list

if ($stmt_conv_users = mysqli_prepare($conn, $sql_conversation_users)) {
    mysqli_stmt_bind_param($stmt_conv_users, "i", $seller_id);
    mysqli_stmt_execute($stmt_conv_users);
    $result_conv_users = mysqli_stmt_get_result($stmt_conv_users);
    while ($row_conv_user = mysqli_fetch_assoc($result_conv_users)) {
        $conversation_users[] = $row_conv_user;
    }
    mysqli_stmt_close($stmt_conv_users);
} else {
    error_log("Error preparing conversation users fetch: " . mysqli_error($conn));
}

// If a conversation user is selected, fetch the messages for that conversation
if ($selected_conversation_user_id > 0) {
    // Get username of the selected conversation user
    $stmt_get_username = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ? AND role = 'buyer'");
    if ($stmt_get_username) {
        mysqli_stmt_bind_param($stmt_get_username, "i", $selected_conversation_user_id);
        mysqli_stmt_execute($stmt_get_username);
        mysqli_stmt_bind_result($stmt_get_username, $uname);
        if (mysqli_stmt_fetch($stmt_get_username)) {
            $selected_conversation_username = htmlspecialchars($uname);
        }
        mysqli_stmt_close($stmt_get_username);
    }

    // Get messages between seller and the selected buyer
    $sql_conversation_messages = "SELECT m.*, s.username as sender_username, r.username as receiver_username, st.name as related_store_name
                                  FROM messages m
                                  JOIN users s ON m.sender_id = s.id
                                  JOIN users r ON m.receiver_id = r.id
                                  LEFT JOIN stores st ON m.store_id = st.id
                                  WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                                  ORDER BY m.sent_at ASC";
    if ($stmt_conv_messages = mysqli_prepare($conn, $sql_conversation_messages)) {
        mysqli_stmt_bind_param($stmt_conv_messages, "iiii", $selected_conversation_user_id, $seller_id, $seller_id, $selected_conversation_user_id);
        mysqli_stmt_execute($stmt_conv_messages);
        $result_conv_messages = mysqli_stmt_get_result($stmt_conv_messages);
        while ($row_msg = mysqli_fetch_assoc($result_conv_messages)) {
            $messages[] = $row_msg;
            // Capture related store_id from the last message to pre-fill reply form
            if ($row_msg['store_id']) {
                $target_store_id_for_reply = $row_msg['store_id'];
            }
        }
        mysqli_stmt_close($stmt_conv_messages);
    } else {
        error_log("Error preparing conversation messages fetch: " . mysqli_error($conn));
    }

    // Mark messages from this sender as read when conversation is opened
    $stmt_mark_read = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    if ($stmt_mark_read) {
        mysqli_stmt_bind_param($stmt_mark_read, "ii", $selected_conversation_user_id, $seller_id);
        mysqli_stmt_execute($stmt_mark_read);
        mysqli_stmt_close($stmt_mark_read);
    }
}

mysqli_close($conn);
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Pesan Masuk Anda</h1>

    <div class="messages-page-layout" style="display: flex; gap: 20px; margin-top: 20px;">
        <div class="conversation-list-panel card-panel" style="flex-basis: 300px; flex-shrink: 0; max-height: 700px; overflow-y: auto; padding: 20px;">
            <h3 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: #000; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Percakapan</h3>
            <?php if (empty($conversation_users)): ?>
                <p style="color: #777;">Belum ada percakapan.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($conversation_users as $user_conv): 
                        // Count unread messages for this user
                        $unread_count = 0;
                        if ($user_conv['id']) { // Make sure user_id is valid
                            $temp_conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                            $stmt_unread = mysqli_prepare($temp_conn, "SELECT COUNT(id) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                            if ($stmt_unread) {
                                mysqli_stmt_bind_param($stmt_unread, "ii", $user_conv['id'], $seller_id);
                                mysqli_stmt_execute($stmt_unread);
                                mysqli_stmt_bind_result($stmt_unread, $count);
                                mysqli_stmt_fetch($stmt_unread);
                                $unread_count = $count;
                                mysqli_stmt_close($stmt_unread);
                            }
                            mysqli_close($temp_conn);
                        }
                    ?>
                        <li style="margin-bottom: 10px;">
                            <a href="messages.php?user_id=<?php echo htmlspecialchars($user_conv['id']); ?>" 
                               class="conversation-item" 
                               style="display: block; padding: 10px 15px; background-color: <?php echo ($user_conv['id'] == $selected_conversation_user_id) ? '#f0f0f0' : '#fff'; ?>; border: 1px solid #eee; text-decoration: none; color: #333; position: relative;">
                                <strong><?php echo htmlspecialchars($user_conv['username']); ?></strong>
                                <?php if ($unread_count > 0): ?>
                                    <span style="background-color: #D60050; color: white; padding: 2px 7px; font-size: 0.75em; float: right; border-radius: 50%;">
                                        <?php echo $unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="chat-area-panel card-panel" style="flex-grow: 1; display: flex; flex-direction: column; padding: 20px;">
            <?php if ($selected_conversation_user_id > 0): ?>
                <h3 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: #000; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Percakapan dengan <?php echo $selected_conversation_username; ?></h3>
                
                <div class="chat-messages-display" style="flex-grow: 1; overflow-y: auto; padding-right: 10px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <?php if (empty($messages)): ?>
                        <p style="text-align: center; color: #777;">Mulai percakapan dengan <?php echo $selected_conversation_username; ?>.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="chat-message-item <?php echo ($msg['sender_id'] == $seller_id) ? 'sent' : 'received'; ?>" style="padding: 10px 15px; max-width: 70%; border: 1px solid transparent; word-wrap: break-word; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <span class="message-sender" style="font-weight: 600; margin-bottom: 5px; font-size: 0.9em; color: rgba(255,255,255,0.8);">
                                    <?php echo ($msg['sender_id'] == $seller_id) ? 'Anda' : htmlspecialchars($msg['sender_username']); ?> 
                                    <?php if ($msg['related_store_name']): ?>
                                        (Toko: <?php echo htmlspecialchars($msg['related_store_name']); ?>)
                                    <?php endif; ?>
                                </span>
                                <p class="message-content" style="font-size: 1rem;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                <span class="message-date" style="font-size: 0.8em; color: rgba(255,255,255,0.6); margin-top: 5px; text-align: right;"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($msg['sent_at']))); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="reply-form-container">
                    <form action="messages.php?user_id=<?php echo htmlspecialchars($selected_conversation_user_id); ?>" method="post" style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="hidden" name="action" value="send_reply">
                        <input type="hidden" name="receiver_user_id" value="<?php echo htmlspecialchars($selected_conversation_user_id); ?>">
                        <input type="hidden" name="reply_subject" value="Balasan dari <?php echo htmlspecialchars($seller_username); ?>">
                        <input type="hidden" name="related_store_id" value="<?php echo htmlspecialchars($target_store_id_for_reply); ?>">
                        
                        <textarea name="reply_message" rows="3" placeholder="Tulis balasan Anda di sini..." style="padding: 10px; border: 1px solid #ccc; font-size: 1rem; resize: vertical;"></textarea>
                        <button type="submit" class="btn" style="background-color: #D60050; color: white; padding: 10px 20px; border: none; cursor: pointer; align-self: flex-end;">Kirim Balasan</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #777;">Pilih percakapan dari daftar di sebelah kiri untuk melihat pesan.</p>
            <?php endif; ?>
        </div>
    </div>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>

<style>
    /* Specific styles for chat bubbles for seller/messages.php */
    .chat-message-item.sent {
        background-color: #D60050; /* Sent messages are pink */
        color: white;
        align-self: flex-end;
        border-color: #D60050;
        margin-left: auto; /* Push to right */
        margin-right: 0;
        max-width: 70%;
        padding: 10px 15px;
        position: relative;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .chat-message-item.sent::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 100%;
        border: 10px solid transparent;
        border-left-color: #D60050;
    }

    .chat-message-item.received {
        background-color: #000000; /* Received messages are black */
        color: white;
        align-self: flex-start;
        border-color: #000000;
        margin-right: auto; /* Push to left */
        margin-left: 0;
        max-width: 70%;
        padding: 10px 15px;
        position: relative;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .chat-message-item.received::before {
        content: '';
        position: absolute;
        top: 10px;
        right: 100%;
        border: 10px solid transparent;
        border-right-color: #000000;
    }
    .message-sender {
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 0.9em;
        color: rgba(255,255,255,0.8);
    }
    .message-date {
        font-size: 0.8em;
        color: rgba(255,255,255,0.6);
        margin-top: 5px;
        text-align: right;
    }

    .chat-messages-display {
        flex-grow: 1;
        overflow-y: auto;
        padding-right: 10px;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .conversation-item.active {
        background-color: #f0f0f0 !important; /* Highlight active conversation */
        border-left: 3px solid #D60050;
    }
    .conversation-item:hover {
        background-color: #f5f5f5;
    }
</style>