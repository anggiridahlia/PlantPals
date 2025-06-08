<?php
session_start();
include '../includes/auth_middleware.php';
require_role('admin');
require_once '../config.php';

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

// Handle form submission for ADD/EDIT/DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add' || $action == 'edit') {
        $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
        $name = trim($_POST['name']);
        $img = trim($_POST['img']);
        $scientific_name = trim($_POST['scientific_name']);
        $family = trim($_POST['family']);
        $description = trim($_POST['description']);
        $habitat = trim($_POST['habitat']);
        $care_instructions = trim($_POST['care_instructions']);
        $unique_fact = trim($_POST['unique_fact']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $seller_id = intval($_POST['seller_id']); // Admin can assign seller

        if ($action == 'add') {
            $sql = "INSERT INTO products (name, img, scientific_name, family, description, habitat, care_instructions, unique_fact, price, stock, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddi", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id);
        } else { // action == 'edit'
            $sql = "UPDATE products SET name = ?, img = ?, scientific_name = ?, family = ?, description = ?, habitat = ?, care_instructions = ?, unique_fact = ?, price = ?, stock = ?, seller_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddii", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id, $product_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: products.php");
        exit;

    } elseif ($action == 'delete') {
        $product_id = intval($_POST['product_id']);
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: products.php");
        exit;
    }
}

// Fetch all products for display
$products = [];
$sql = "SELECT p.*, u.username as seller_username FROM products p LEFT JOIN users u ON p.seller_id = u.id ORDER BY p.id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

// Fetch all sellers for dropdown
$sellers = [];
$sql_sellers = "SELECT id, username FROM users WHERE role = 'seller' ORDER BY username ASC";
$result_sellers = mysqli_query($conn, $sql_sellers);
if ($result_sellers) {
    while ($row_seller = mysqli_fetch_assoc($result_sellers)) {
        $sellers[] = $row_seller;
    }
}

mysqli_close($conn);
?>
<?php include '../includes/header.php'; ?>

    <h1>Manajemen Produk</h1>

    <div class="product-form-container card-panel">
        <h2><?php echo $product_to_edit ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h2>
        <form action="products.php" method="post">
            <input type="hidden" name="action" value="<?php echo $product_to_edit ? 'edit' : 'add'; ?>">
            <input type="hidden" name="product_id" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['id']) : ''; ?>">
            
            <div class="form-group">
                <label for="name">Nama Produk:</label>
                <input type="text" id="name" name="name" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="img">URL Gambar (assets/...):</label>
                <input type="text" id="img" name="img" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['img']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="scientific_name">Nama Ilmiah:</label>
                <input type="text" id="scientific_name" name="scientific_name" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['scientific_name'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="family">Familia:</label>
                <input type="text" id="family" name="family" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['family'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Deskripsi:</label>
                <textarea id="description" name="description"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['description'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="habitat">Habitat:</label>
                <textarea id="habitat" name="habitat"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['habitat'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="care_instructions">Instruksi Perawatan:</label>
                <textarea id="care_instructions" name="care_instructions"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['care_instructions'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="unique_fact">Fakta Unik:</label>
                <textarea id="unique_fact" name="unique_fact"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['unique_fact'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['price']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stok:</label>
                <input type="number" id="stock" name="stock" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['stock']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="seller_id">Penjual:</label>
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
                    <a href="products.php" class="cancel-btn btn-link">Batal Edit</a>
                <?php endif; ?>
                <button type="submit" class="submit-btn"><?php echo $product_to_edit ? 'Update Produk' : 'Tambah Produk'; ?></button>
            </div>
        </form>
    </div>

    <div class="section-header">
        <h2>Daftar Produk</h2>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Penjual</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="6">Belum ada produk dalam database.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($product['stock']); ?></td>
                    <td><?php echo htmlspecialchars($product['seller_username'] ?? 'N/A'); ?></td>
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

<?php include '../includes/footer.php'; ?>