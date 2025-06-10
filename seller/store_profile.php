<?php
session_start();
ini_set('display_errors', 1); // Aktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Aktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_once ROOT_PATH . 'config.php'; // config.php ada di root PlantPals/

require_role('seller'); // Memastikan user adalah seller

$seller_id = $_SESSION['id']; // Dapatkan ID penjual yang sedang login

$store_details = null;
$message = '';
$message_type = ''; // 'success' or 'error'

// Ambil detail toko penjual yang sedang login
$stmt = mysqli_prepare($conn, "SELECT id, store_id_string, name, address, phone_number, email FROM stores WHERE seller_user_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $seller_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $store_details = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$store_details) {
        // Jika penjual belum punya toko (ini seharusnya sudah dibuat saat register, tapi sebagai fallback)
        $message = "Anda belum memiliki profil toko. Silakan hubungi administrator.";
        $message_type = "error";
    }
} else {
    $message = "Terjadi kesalahan sistem saat mengambil profil toko.";
    $message_type = "error";
    error_log("Error preparing store details fetch: " . mysqli_error($conn));
}


// Handle form submission for updating store profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_store_profile') {
    $store_id = $store_details['id'] ?? null; // Pastikan ID toko ada
    $store_name = trim($_POST['name'] ?? '');
    $store_address = trim($_POST['address'] ?? '');
    $store_phone = trim($_POST['phone_number'] ?? '');
    $store_email = trim($_POST['email'] ?? '');

    if ($store_id) {
        $sql_update = "UPDATE stores SET name = ?, address = ?, phone_number = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_user_id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, "ssssii", $store_name, $store_address, $store_phone, $store_email, $store_id, $seller_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $message = "Profil toko berhasil diperbarui!";
                $message_type = "success";
                // Perbarui $store_details agar tampilan langsung berubah
                $store_details['name'] = $store_name;
                $store_details['address'] = $store_address;
                $store_details['phone_number'] = $store_phone;
                $store_details['email'] = $store_email;
            } else {
                $message = "Gagal memperbarui profil toko. Detail: " . mysqli_stmt_error($stmt_update);
                $message_type = "error";
                error_log("Error updating store profile: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $message = "Terjadi kesalahan sistem saat menyiapkan pembaruan profil toko.";
            $message_type = "error";
            error_log("Error preparing store update statement: " . mysqli_error($conn));
        }
    } else {
        $message = "ID toko tidak ditemukan untuk diperbarui.";
        $message_type = "error";
    }
}

mysqli_close($conn); // Tutup koneksi setelah semua operasi selesai
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Profil Toko Anda</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>-message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="store-profile-container card-panel">
        <?php if ($store_details): ?>
            <div class="profile-info-display">
                <p><strong><i class="fas fa-store"></i> Nama Toko:</strong> <?php echo htmlspecialchars($store_details['name']); ?></p>
                <p><strong><i class="fas fa-map-marker-alt"></i> Alamat:</strong> <?php echo htmlspecialchars($store_details['address'] ?? 'N/A'); ?></p>
                <p><strong><i class="fas fa-phone"></i> Telepon:</strong> <?php echo htmlspecialchars($store_details['phone_number'] ?? 'N/A'); ?></p>
                <p><strong><i class="fas fa-at"></i> Email:</strong> <?php echo htmlspecialchars($store_details['email'] ?? 'N/A'); ?></p>
                <p><strong><i class="fas fa-id-card"></i> ID Toko (String):</strong> <?php echo htmlspecialchars($store_details['store_id_string'] ?? 'N/A'); ?></p>
            </div>

            <h3 class="section-sub-title" style="text-align: left; margin-top: 30px;"><i class="fas fa-edit"></i> Edit Detail Toko</h3>
            <form action="store_profile.php" method="post" class="store-edit-form">
                <input type="hidden" name="action" value="update_store_profile">

                <div class="form-group">
                    <label for="name"><i class="fas fa-store"></i> Nama Toko:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($store_details['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Alamat:</label>
                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($store_details['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="phone_number"><i class="fas fa-phone"></i> Nomor Telepon:</label>
                    <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($store_details['phone_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-at"></i> Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($store_details['email'] ?? ''); ?>">
                </div>

                <div class="form-action-btns">
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        <?php else: ?>
            <p class="no-results">Tidak dapat memuat profil toko Anda. Pastikan akun Anda terhubung dengan toko.</p>
        <?php endif; ?>
    </div>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>