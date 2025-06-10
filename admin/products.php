<?php
session_start();
ini_set('display_errors', 1); // Aktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Aktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

include ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_role('admin');
require_once ROOT_PATH . 'config.php';

$product_to_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product_to_edit = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// Fetch sellers for dropdown (needed for both add and edit form)
$sellers = [];
$sql_sellers = "SELECT id, username FROM users WHERE role = 'seller' ORDER BY username ASC";
$result_sellers = mysqli_query($conn, $sql_sellers);
if ($result_sellers) {
    while ($row_seller = mysqli_fetch_assoc($result_sellers)) {
        $sellers[] = $row_seller;
    }
}

// Handle form submission for ADD/EDIT/DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add' || $action == 'edit') {
        $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
        $name = trim($_POST['name']);
        $scientific_name = trim($_POST['scientific_name'] ?? '');
        $family = trim($_POST['family'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $habitat = trim($_POST['habitat'] ?? '');
        $care_instructions = trim($_POST['care_instructions'] ?? '');
        $unique_fact = trim($_POST['unique_fact'] ?? '');
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $seller_id = intval($_POST['seller_id']); // Admin can assign seller

        // --- Handle Image Upload ---
        $img_path = $product_to_edit['img'] ?? null; // Default to existing image path for edit
        $upload_dir = '/PlantPals/assets/uploads/'; // Folder untuk menyimpan gambar
        $target_dir = ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR; // Path fisik server

        // Pastikan direktori upload ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Buat folder jika belum ada, berikan izin penuh (0777)
        }

        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid('product_') . '.' . $file_type; // Nama unik untuk menghindari konflik

            $target_file = $target_dir . $new_file_name;

            // Validasi tipe file
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_type, $allowed_types)) {
                echo "<script>alert('Format gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.'); window.history.back();</script>";
                exit;
            }

            // Validasi ukuran file (misal: max 5MB)
            if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) {
                echo "<script>alert('Ukuran gambar terlalu besar. Maksimal 5MB.'); window.history.back();</script>";
                exit;
            }

            // Pindahkan file yang diunggah
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                $img_path = $upload_dir . $new_file_name;
                // Jika mengedit dan ada gambar lama, hapus gambar lama (opsional)
                if ($action == 'edit' && $product_to_edit['img'] && strpos($product_to_edit['img'], $upload_dir) !== false) { // Hanya hapus jika dari folder uploads
                    $old_file_path = ROOT_PATH . substr($product_to_edit['img'], strlen('/PlantPals/')); // Hapus "/PlantPals/"
                    if (file_exists($old_file_path) && is_file($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            } else {
                echo "<script>alert('Gagal mengunggah gambar. Kode error: " . $_FILES['product_image']['error'] . "'); window.history.back();</script>";
                exit;
            }
        } else if ($action == 'add' && (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] == UPLOAD_ERR_NO_FILE)) {
            // Jika tambah produk, gambar wajib diunggah
            echo "<script>alert('Gambar produk wajib diunggah.'); window.history.back();</script>";
            exit;
        }
        // Jika edit dan tidak ada file baru diunggah, $img_path akan tetap menggunakan path lama

        // Basic validation for required fields (now including img_path check)
        if (empty($name) || empty($img_path) || !is_numeric($price) || $price < 0 || !is_numeric($stock) || $stock < 0 || empty($seller_id)) {
            echo "<script>alert('Nama Produk, Gambar, Harga (harus angka positif), Stok (harus angka positif), dan Penjual wajib diisi dengan benar.'); window.history.back();</script>";
            exit;
        }

        if ($action == 'add') {
            $sql = "INSERT INTO products (name, img, scientific_name, family, description, habitat, care_instructions, unique_fact, price, stock, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddi", $name, $img_path, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id);
        } else { // action == 'edit'
            $sql = "UPDATE products SET name = ?, img = ?, scientific_name = ?, family = ?, description = ?, habitat = ?, care_instructions = ?, unique_fact = ?, price = ?, stock = ?, seller_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddii", $name, $img_path, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id, $product_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit;
        } else {
            error_log("Error inserting/updating product in admin/products.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menyimpan produk ke database. Mohon coba lagi atau hubungi administrator.'); window.history.back();</script>";
            exit;
        }

        mysqli_stmt_close($stmt);
    } elseif ($action == 'delete') {
        $product_id = intval($_POST['product_id']);
        // Fetch image path before deleting to unlink the file
        $stmt_img = mysqli_prepare($conn, "SELECT img FROM products WHERE id = ?");
        if ($stmt_img) {
            mysqli_stmt_bind_param($stmt_img, "i", $product_id);
            mysqli_stmt_execute($stmt_img);
            mysqli_stmt_bind_result($stmt_img, $img_to_delete);
            mysqli_stmt_fetch($stmt_img);
            mysqli_stmt_close($stmt_img);

            // Delete the image file if it exists and is in the uploads directory
            if ($img_to_delete && strpos($img_to_delete, $upload_dir) !== false) {
                 $file_to_delete = ROOT_PATH . substr($img_to_delete, strlen('/PlantPals/'));
                if (file_exists($file_to_delete) && is_file($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit;
        } else {
            error_log("Error deleting product in admin/products.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menghapus produk. Mohon coba lagi.'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch all products for display (after any modifications)
$products = [];
$sql = "SELECT p.*, u.username as seller_username FROM products p LEFT JOIN users u ON p.seller_id = u.id ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

mysqli_close($conn);
?>
<?php include ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Manajemen Produk</h1>

    <div class="product-form-container card-panel">
        <h2><?php echo $product_to_edit ? '<i class="fas fa-edit"></i> Edit Produk' : '<i class="fas fa-plus-circle"></i> Tambah Produk Baru'; ?></h2>
        <form action="products.php" method="post" enctype="multipart/form-data"> <input type="hidden" name="action" value="<?php echo $product_to_edit ? 'edit' : 'add'; ?>">
            <input type="hidden" name="product_id" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['id']) : ''; ?>">

            <div class="form-group">
                <label for="name"><i class="fas fa-tag"></i> Nama Produk:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product_to_edit['name'] ?? ''); ?>" required placeholder="Nama bunga atau produk">
            </div>

            <div class="form-group">
                <label for="product_image"><i class="fas fa-image"></i> Gambar Produk:</label>
                <input type="file" id="product_image" name="product_image" accept="image/*"> <?php if ($product_to_edit && $product_to_edit['img']): ?>
                    <small>Gambar saat ini: <a href="<?php echo htmlspecialchars($product_to_edit['img']); ?>" target="_blank"><?php echo htmlspecialchars(basename($product_to_edit['img'])); ?></a></small><br>
                    <img src="<?php echo htmlspecialchars($product_to_edit['img']); ?>" alt="Gambar Saat Ini" style="max-width: 100px; max-height: 100px; object-fit: cover; margin-top: 10px; border-radius: 5px;">
                    <input type="hidden" name="current_img_path" value="<?php echo htmlspecialchars($product_to_edit['img']); ?>">
                <?php endif; ?>
                <small>Format: JPG, JPEG, PNG, GIF. Maksimal 5MB.</small>
            </div>

            <div class="form-group">
                <label for="scientific_name"><i class="fas fa-flask"></i> Nama Ilmiah (Opsional):</label>
                <input type="text" id="scientific_name" name="scientific_name" value="<?php echo htmlspecialchars($product_to_edit['scientific_name'] ?? ''); ?>" placeholder="Contoh: Rosa chinensis">
            </div>

            <div class="form-group">
                <label for="family"><i class="fas fa-sitemap"></i> Familia (Opsional):</label>
                <input type="text" id="family" name="family" value="<?php echo htmlspecialchars($product_to_edit['family'] ?? ''); ?>" placeholder="Contoh: Rosaceae">
            </div>

            <div class="form-group">
                <label for="description"><i class="fas fa-align-left"></i> Deskripsi (Opsional):</label>
                <textarea id="description" name="description" placeholder="Deskripsi singkat produk..."><?php echo htmlspecialchars($product_to_edit['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="habitat"><i class="fas fa-globe-americas"></i> Habitat (Opsional):</label>
                <textarea id="habitat" name="habitat" placeholder="Informasi habitat tanaman..."><?php echo htmlspecialchars($product_to_edit['habitat'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="care_instructions"><i class="fas fa-leaf"></i> Instruksi Perawatan (Opsional):</label>
                <textarea id="care_instructions" name="care_instructions" placeholder="Panduan cara merawat tanaman ini..."><?php echo htmlspecialchars($product_to_edit['care_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="unique_fact"><i class="fas fa-lightbulb"></i> Fakta Unik (Opsional):</label>
                <textarea id="unique_fact" name="unique_fact" placeholder="Fakta menarik atau sejarah tentang produk ini..."><?php echo htmlspecialchars($product_to_edit['unique_fact'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="price"><i class="fas fa-dollar-sign"></i> Harga (Rp):</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($product_to_edit['price'] ?? ''); ?>" required min="0">
            </div>

            <div class="form-group">
                <label for="stock"><i class="fas fa-cubes"></i> Stok:</label>
                <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars($product_to_edit['stock'] ?? ''); ?>" required min="0">
            </div>

            <div class="form-group">
                <label for="seller_id"><i class="fas fa-store"></i> Penjual:</label>
                <select id="seller_id" name="seller_id" required>
                    <option value="">Pilih Penjual</option>
                    <?php foreach($sellers as $seller): ?>
                        <option value="<?php echo htmlspecialchars($seller['id']); ?>" <?php echo ($product_to_edit && $product_to_edit['seller_id'] == $seller['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($seller['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-action-btns">
                <?php if ($product_to_edit): ?>
                    <a href="products.php" class="cancel-btn btn-link"><i class="fas fa-times-circle"></i> Batal Edit</a>
                <?php endif; ?>
                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> <?php echo $product_to_edit ? 'Update Produk' : 'Tambah Produk'; ?></button>
            </div>
        </form>
    </div>

    <div class="section-header">
        <h2>Daftar Produk</h2>
        <a href="products.php" class="add-new-btn"><i class="fas fa-plus"></i> Tambah Produk Baru</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Gambar</th>
                <th>Nama</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Penjual</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="7">Belum ada produk dalam database.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                    <td><img src="<?php echo htmlspecialchars($product['img']); ?>" alt="Gambar <?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($product['stock']); ?></td>
                    <td><?php echo htmlspecialchars($product['seller_username'] ?? 'N/A'); ?></td>
                    <td class="action-buttons">
                        <a href="products.php?edit_id=<?php echo htmlspecialchars($product['id']); ?>" class="edit-btn"><i class="fas fa-edit"></i> Edit</a>
                        <form action="products.php" method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Yakin ingin menghapus produk ini?');"><i class="fas fa-trash-alt"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php mysqli_close($conn); ?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>