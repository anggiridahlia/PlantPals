<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_role('admin');
require_once ROOT_PATH . 'config.php';

$user_to_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, username, email, full_name, phone_number, address, role FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_to_edit = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// Handle form submission for ADD/EDIT/DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add' || $action == 'edit') {
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        $username_form = trim($_POST['username']);
        $email_form = trim($_POST['email']);
        $role_form = trim($_POST['role']);
        $full_name_form = trim($_POST['full_name'] ?? '');
        $phone_number_form = trim($_POST['phone_number'] ?? '');
        $address_form = trim($_POST['address'] ?? '');
        $password_form = trim($_POST['password'] ?? '');

        // Basic validation for required fields
        if (empty($username_form) || empty($email_form) || empty($role_form)) {
            echo "<script>alert('Username, Email, dan Peran wajib diisi.'); window.history.back();</script>";
            exit;
        }

        if ($action == 'add') {
            if (empty($password_form) || strlen($password_form) < 6) {
                echo "<script>alert('Password baru harus diisi dan minimal 6 karakter saat menambah pengguna.'); window.history.back();</script>";
                exit;
            }
            $hashed_password = password_hash($password_form, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, email, full_name, phone_number, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("Error preparing add user statement: " . mysqli_error($conn));
                echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan penambahan pengguna. Mohon coba lagi.'); window.history.back();</script>";
                exit;
            }
            mysqli_stmt_bind_param($stmt, "sssssss", $username_form, $hashed_password, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form);
        } else { // action == 'edit'
            $sql_update = "UPDATE users SET username = ?, email = ?, full_name = ?, phone_number = ?, address = ?, role = ? WHERE id = ?";
            if (!empty($password_form)) {
                if (strlen($password_form) < 6) {
                    echo "<script>alert('Password baru harus minimal 6 karakter.'); window.history.back();</script>";
                    exit;
                }
                $hashed_password = password_hash($password_form, PASSWORD_DEFAULT);
                $sql_update = "UPDATE users SET username = ?, password = ?, email = ?, full_name = ?, phone_number = ?, address = ?, role = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_update);
                if (!$stmt) {
                    error_log("Error preparing edit user with password statement: " . mysqli_error($conn));
                    echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan pengeditan pengguna. Mohon coba lagi.'); window.history.back();</script>";
                    exit;
                }
                mysqli_stmt_bind_param($stmt, "sssssssi", $username_form, $hashed_password, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form, $user_id);
            } else {
                $stmt = mysqli_prepare($conn, $sql_update);
                if (!$stmt) {
                    error_log("Error preparing edit user without password statement: " . mysqli_error($conn));
                    echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan pengeditan pengguna. Mohon coba lagi.'); window.history.back();</script>";
                    exit;
                }
                mysqli_stmt_bind_param($stmt, "ssssssi", $username_form, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form, $user_id);
            }
        }
        
        // Cek eksekusi statement user dan tangani error
        if (mysqli_stmt_execute($stmt)) {
            $current_user_id = ($action == 'add') ? mysqli_insert_id($conn) : $user_id; // Get the user ID
            
            // Jika peran diubah menjadi seller, pastikan ada entri toko default.
            // Jika sudah ada, tidak perlu dibuat lagi.
            if ($role_form == 'seller') {
                $sql_check_store = "SELECT id FROM stores WHERE seller_user_id = ?";
                if ($stmt_check = mysqli_prepare($conn, $sql_check_store)) {
                    mysqli_stmt_bind_param($stmt_check, "i", $current_user_id);
                    mysqli_stmt_execute($stmt_check);
                    mysqli_stmt_store_result($stmt_check);
                    if (mysqli_stmt_num_rows($stmt_check) == 0) { // Jika tidak ada toko untuk seller ini
                         // Fetch user's data for default store creation
                        $temp_user_data = [];
                        $sql_get_user_data = "SELECT full_name, phone_number, address, username FROM users WHERE id = ?";
                        if ($stmt_get_user_data = mysqli_prepare($conn, $sql_get_user_data)) {
                            mysqli_stmt_bind_param($stmt_get_user_data, "i", $current_user_id);
                            mysqli_stmt_execute($stmt_get_user_data);
                            $res_get_user_data = mysqli_stmt_get_result($stmt_get_user_data);
                            $temp_user_data = mysqli_fetch_assoc($res_get_user_data);
                            mysqli_stmt_close($stmt_get_user_data);
                        }

                        // Generate unique store_id_string
                        $store_id_str = 'toko_' . strtolower(str_replace([' ', '-', '.'], '_', $temp_user_data['username'] ?? 'unknown_user')) . '_' . time();

                        $default_store_name = !empty($temp_user_data['full_name']) ? htmlspecialchars($temp_user_data['full_name']) . "'s Store" : htmlspecialchars($temp_user_data['username'] ?? 'New Seller') . "'s Store";
                        $default_store_address = !empty($temp_user_data['address']) ? htmlspecialchars($temp_user_data['address']) : 'Alamat belum ditentukan';
                        $default_store_phone = !empty($temp_user_data['phone_number']) ? htmlspecialchars($temp_user_data['phone_number']) : 'Nomor telepon belum ditentukan';
                        

                        $sql_insert_store = "INSERT INTO stores (store_id_string, name, address, phone_number, seller_user_id) VALUES (?, ?, ?, ?, ?)";
                        if ($stmt_insert_store = mysqli_prepare($conn, $sql_insert_store)) {
                            mysqli_stmt_bind_param($stmt_insert_store, "ssssi", $store_id_str, $default_store_name, $default_store_address, $default_store_phone, $current_user_id);
                            if (!mysqli_stmt_execute($stmt_insert_store)) {
                                error_log("Error creating default store for new/updated seller " . $current_user_id . ": " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($stmt_insert_store);
                        } else {
                            error_log("Error preparing store insert statement: " . mysqli_error($conn));
                        }
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                    error_log("Error preparing store check statement: " . mysqli_error($conn));
                }
            }
            header("Location: users.php");
            exit;
        } else {
            error_log("Error inserting/updating user in admin/users.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menyimpan data pengguna ke database. Mohon coba lagi atau hubungi administrator. Detail: " . mysqli_stmt_error($stmt) . "'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_close($stmt); // Tutup statement user
    } elseif ($action == 'delete') {
        $user_id = intval($_POST['user_id']);
        if ($user_id == $_SESSION['id']) {
             echo "<script>alert('Anda tidak bisa menghapus akun Anda sendiri!'); window.location.href='users.php';</script>";
             exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        if (!$stmt) {
            error_log("Error preparing delete user statement: " . mysqli_error($conn));
            echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan penghapusan pengguna. Mohon coba lagi.'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: users.php");
            exit;
        } else {
            error_log("Error deleting user in admin/users.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menghapus pengguna. Mohon coba lagi. Detail: " . mysqli_stmt_error($stmt) . "'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch all users for display
$users = [];
$sql = "SELECT id, username, email, role, full_name, phone_number, address, created_at FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

mysqli_close($conn); // Close connection after all data fetching and processing
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna Admin - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/admin_seller_styles.css">
</head>
<body>
    <?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Manajemen Pengguna</h1>

    <div class="user-form-container card-panel">
        <h2><?php echo $user_to_edit ? 'Edit Pengguna' : 'Tambah Pengguna Baru'; ?></h2>
        <form action="users.php" method="post">
            <input type="hidden" name="action" value="<?php echo $user_to_edit ? 'edit' : 'add'; ?>">
            <input type="hidden" name="user_id" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['id']) : ''; ?>">
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password (kosongkan jika tidak ingin diubah):</label>
                <input type="password" id="password" name="password" value="">
            </div>

            <div class="form-group">
                <label for="full_name">Nama Lengkap:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['full_name'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="phone_number">Nomor Telepon:</label>
                <input type="text" id="phone_number" name="phone_number" value="<?php echo $user_to_edit ? htmlspecialchars($user_to_edit['phone_number'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label for="address">Alamat:</label>
                <textarea id="address" name="address"><?php echo $user_to_edit ? htmlspecialchars($user_to_edit['address'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="role">Peran:</label>
                <select id="role" name="role" required>
                    <option value="buyer" <?php echo ($user_to_edit && $user_to_edit['role'] == 'buyer') ? 'selected' : ''; ?>>Pembeli</option>
                    <option value="seller" <?php echo ($user_to_edit && $user_to_edit['role'] == 'seller') ? 'selected' : ''; ?>>Penjual</option>
                    <option value="admin" <?php echo ($user_to_edit && $user_to_edit['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="form-action-btns">
                <?php if ($user_to_edit): ?>
                    <a href="users.php" class="cancel-btn btn-link">Batal Edit</a>
                <?php endif; ?>
                <button type="submit" class="submit-btn"><?php echo $user_to_edit ? 'Update Pengguna' : 'Tambah Pengguna'; ?></button>
            </div>
        </form>
    </div>

    <div class="section-header">
        <h2>Daftar Pengguna</h2>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Peran</th>
                <th>Nama Lengkap</th>
                <th>Telepon</th>
                <th>Alamat</th>
                <th>Terdaftar Sejak</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="9">Belum ada pengguna.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($user['created_at']))); ?></td>
                    <td class="action-buttons">
                        <a href="users.php?edit_id=<?php echo htmlspecialchars($user['id']); ?>" class="edit-btn">Edit</a>
                        <?php if ($user['id'] != $_SESSION['id']): ?>
                        <form action="users.php" method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Yakin ingin menghapus pengguna ini?');">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>