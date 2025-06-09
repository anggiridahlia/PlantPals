<?php
session_start();
ini_set('display_errors', 1); // Aktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Aktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
// Ini akan mengambil direktori dari file saat ini (seller/products.php)
// lalu naik satu level (ke PlantPals/)
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Sertakan file-file yang dibutuhkan dengan path yang lebih stabil
require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_once ROOT_PATH . 'config.php'; // config.php ada di root PlantPals/

require_role('seller'); // Memastikan user adalah seller

$seller_id = $_SESSION['id']; // Get current seller's ID from session (CRUCIAL)

$product_to_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    // Ensure seller can only edit their own products
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? AND seller_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $edit_id, $seller_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product_to_edit = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// Handle form submission for ADD/EDIT/DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add' || $action == 'edit') {
        $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
        $name = trim($_POST['name']);
        $img = trim($_POST['img']);
        $scientific_name = trim($_POST['scientific_name'] ?? '');
        $family = trim($_POST['family'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $habitat = trim($_POST['habitat'] ?? '');
        $care_instructions = trim($_POST['care_instructions'] ?? '');
        $unique_fact = trim($_POST['unique_fact'] ?? '');
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        // Basic validation for required fields
        if (empty($name) || empty($img) || !is_numeric($price) || $price < 0 || !is_numeric($stock) || $stock < 0) {
            echo "<script>alert('Nama Produk, URL Gambar, Harga (harus angka positif), dan Stok (harus angka positif) wajib diisi dengan benar.'); window.history.back();</script>";
            exit;
        }

        if ($action == 'add') {
            $sql = "INSERT INTO products (name, img, scientific_name, family, description, habitat, care_instructions, unique_fact, price, stock, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("Error preparing add product statement: " . mysqli_error($conn));
                echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan penambahan produk. Mohon coba lagi.'); window.history.back();</script>";
                exit;
            }
            mysqli_stmt_bind_param($stmt, "ssssssssddi", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id); // Use seller_id from session
        } else { // action == 'edit'
            // Ensure seller can only edit their own product
            $sql = "UPDATE products SET name = ?, img = ?, scientific_name = ?, family = ?, description = ?, habitat = ?, care_instructions = ?, unique_fact = ?, price = ?, stock = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?"; // Added seller_id security check
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("Error preparing edit product statement: " . mysqli_error($conn));
                echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan pengeditan produk. Mohon coba lagi.'); window.history.back();</script>";
                exit;
            }
            mysqli_stmt_bind_param($stmt, "ssssssssddii", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $product_id, $seller_id);
        }
        
        // Cek eksekusi statement dan tangani error
        if (mysqli_stmt_execute($stmt)) {
            // Berhasil
            header("Location: products.php");
            exit;
        } else {
            // Tangani error database
            // mysqli_stmt_error($stmt) akan memberikan pesan error spesifik dari MySQL
            error_log("Error inserting/updating product in seller/products.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menyimpan produk ke database. Detail: " . mysqli_stmt_error($stmt) . " Mohon coba lagi atau hubungi administrator.'); window.history.back();</script>"; // Tampilkan error spesifik dari MySQL
            exit;
        }

        mysqli_stmt_close($stmt); // Tutup statement setelah eksekusi, terlepas dari sukses/gagal
    } elseif ($action == 'delete') {
        $product_id = intval($_POST['product_id']);
        // Ensure seller can only delete their own product
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ? AND seller_id = ?"); // Added seller_id security check
        if (!$stmt) {
            error_log("Error preparing delete product statement: " . mysqli_error($conn));
            echo "<script>alert('Terjadi kesalahan sistem saat menyiapkan penghapusan produk. Mohon coba lagi.'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_bind_param($stmt, "ii", $product_id, $seller_id);
        
        // Cek eksekusi statement delete
        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit;
        } else {
            error_log("Error deleting product in seller/products.php: " . mysqli_stmt_error($stmt));
            echo "<script>alert('Terjadi kesalahan saat menghapus produk. Detail: " . mysqli_stmt_error($stmt) . " Mohon coba lagi.'); window.history.back();</script>";
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch products by current seller for display (after any modifications)
$products = [];
$sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY id DESC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $seller_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn); // Close connection after fetching all data for display
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Saya - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/admin_seller_styles.css">
</head>
<body>
    <?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Produk Saya</h1>

    <div class="product-form-container card-panel">
        <h2><?php echo $product_to_edit ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h2>
        <form action="products.php" method="post">
            <input type="hidden" name="action" value="<?php echo $product_to_edit ? 'edit' : 'add'; ?>">
            <input type="hidden" name="product_id" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['id']) : ''; ?>">

            <div class="form-group">
                <label for="name">Nama Produk:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product_to_edit['name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="img">URL Gambar (assets/...):</label>
                <input type="text" id="img" name="img" value="<?php echo htmlspecialchars($product_to_edit['img'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="scientific_name">Nama Ilmiah (Opsional):</label>
                <input type="text" id="scientific_name" name="scientific_name" value="<?php echo htmlspecialchars($product_to_edit['scientific_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="family">Familia (Opsional):</label>
                <input type="text" id="family" name="family" value="<?php echo htmlspecialchars($product_to_edit['family'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Deskripsi (Opsional):</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($product_to_edit['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="habitat">Habitat (Opsional):</label>
                <textarea id="habitat" name="habitat"><?php echo htmlspecialchars($product_to_edit['habitat'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="care_instructions">Instruksi Perawatan (Opsional):</label>
                <textarea id="care_instructions" name="care_instructions"><?php echo htmlspecialchars($product_to_edit['care_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="unique_fact">Fakta Unik (Opsional):</label>
                <textarea id="unique_fact" name="unique_fact"><?php echo htmlspecialchars($product_to_edit['unique_fact'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($product_to_edit['price'] ?? ''); ?>" required min="0">
            </div>

            <div class="form-group">
                <label for="stock">Stok:</label>
                <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars($product_to_edit['stock'] ?? ''); ?>" required min="0">
            </div>

            <div class="form-action-btns">
                <?php if ($product_to_edit): ?>
                    <a href="products.php" class="cancel-btn btn-link">Batal Edit</a>
                <?php endif; ?>
                <button type="submit" class="btn-primary"><?php echo $product_to_edit ? 'Update Produk' : 'Tambah Produk'; ?></button>
            </div>
        </form>
    </div>

    <div class="section-header">
        <h2>Daftar Produk Anda</h2>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="5">Anda belum memiliki produk.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($product['stock']); ?></td>
                    <td class="action-buttons">
                        <a href="products.php?edit_id=<?php echo htmlspecialchars($product['id']); ?>" class="edit-btn">Edit</a>
                        <form action="products.php" method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Yakin ingin menghapus produk ini?');">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>