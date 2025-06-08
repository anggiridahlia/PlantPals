<?php
session_start();
include '../includes/auth_middleware.php';
require_role('admin');
require_once '../config.php';

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
        $full_name_form = trim($_POST['full_name']);
        $phone_number_form = trim($_POST['phone_number']);
        $address_form = trim($_POST['address']);
        $password_form = trim($_POST['password']); // Only if new/changed

        if ($action == 'add') {
            // *** Perubahan: Menyimpan password teks biasa (TIDAK AMAN untuk produksi) ***
            $sql = "INSERT INTO users (username, password, email, full_name, phone_number, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssss", $username_form, $password_form, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form);
        } else { // action == 'edit'
            $sql_update = "UPDATE users SET username = ?, email = ?, full_name = ?, phone_number = ?, address = ?, role = ? WHERE id = ?";
            if (!empty($password_form)) { // Update password only if provided
                // *** Perubahan: Menyimpan password teks biasa jika diupdate (TIDAK AMAN untuk produksi) ***
                $sql_update = "UPDATE users SET username = ?, password = ?, email = ?, full_name = ?, phone_number = ?, address = ?, role = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt, "sssssssi", $username_form, $password_form, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form, $user_id);
            } else {
                $stmt = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt, "ssssssi", $username_form, $email_form, $full_name_form, $phone_number_form, $address_form, $role_form, $user_id);
            }
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: users.php");
        exit;

    } elseif ($action == 'delete') {
        $user_id = intval($_POST['user_id']);
        if ($user_id == $_SESSION['id']) { // Prevent admin from deleting themselves
             echo "<script>alert('Anda tidak bisa menghapus akun Anda sendiri!'); window.location.href='users.php';</script>";
             exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: users.php");
        exit;
    }
}

// Fetch all users for display
$users = [];
$sql = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

mysqli_close($conn);
?>
<?php include '../includes/header.php'; ?>

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
                <th>Terdaftar Sejak</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6">Belum ada pengguna.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($user['created_at']))); ?></td>
                    <td class="action-buttons">
                        <a href="users.php?edit_id=<?php echo htmlspecialchars($user['id']); ?>" class="edit-btn">Edit</a>
                        <?php if ($user['id'] != $_SESSION['id']): // Prevent admin from deleting themselves ?>
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

<?php include '../includes/footer.php'; ?>