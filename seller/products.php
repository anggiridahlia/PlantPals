<?php
session_start();
include '../includes/auth_middleware.php';
require_role('seller');
require_once '../config.php';

$seller_id = $_SESSION['id']; // Get current seller's ID

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
        $scientific_name = trim($_POST['scientific_name']);
        $family = trim($_POST['family']);
        $description = trim($_POST['description']);
        $habitat = trim($_POST['habitat']);
        $care_instructions = trim($_POST['care_instructions']);
        $unique_fact = trim($_POST['unique_fact']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        if ($action == 'add') {
            $sql = "INSERT INTO products (name, img, scientific_name, family, description, habitat, care_instructions, unique_fact, price, stock, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddi", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $seller_id); // seller_id from session
        } else { // action == 'edit'
            // Ensure seller can only edit their own product
            $sql = "UPDATE products SET name = ?, img = ?, scientific_name = ?, family = ?, description = ?, habitat = ?, care_instructions = ?, unique_fact = ?, price = ?, stock = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssddii", $name, $img, $scientific_name, $family, $description, $habitat, $care_instructions, $unique_fact, $price, $stock, $product_id, $seller_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: products.php");
        exit;

    } elseif ($action == 'delete') {
        $product_id = intval($_POST['product_id']);
        // Ensure seller can only delete their own product
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ? AND seller_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $product_id, $seller_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: products.php");
        exit;
    }
}

// Fetch products by current seller for display
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

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Saya - PlantPals</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; flex-direction: column; min-height: 100vh; background: rgb(245, 255, 245); color: #2a4d3a; }
        .main-content { flex: 1; padding: 40px; }
        h1 { font-size: 2.8rem; margin-bottom: 30px; color: #386641; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 2rem; color: #386641; }
        .add-new-btn { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; }

        .product-form-container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); margin-bottom: 40px; }
        .product-form-container label { display: block; margin-bottom: 8px; font-weight: 600; color: #3a5a20; }
        .product-form-container input[type="text"], .product-form-container input[type="number"], .product-form-container textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
        .product-form-container textarea { min-height: 80px; resize: vertical; }
        .form-action-btns { text-align: right; }
        .form-action-btns button { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .form-action-btns .submit-btn { background-color: #4CAF50; color: white; margin-left: 10px; }
        .form-action-btns .cancel-btn { background-color: #f44336; color: white; }

        .product-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .product-table th, .product-table td { border: 1px solid #eee; padding: 12px 15px; text-align: left; }
        .product-table th { background-color: #f0f8f0; font-weight: 700; color: #3a5a20; }
        .product-table tr:nth-child(even) { background-color: #f9f9f9; }
        .product-table .action-buttons { display: flex; gap: 5px; }
        .product-table .action-buttons a, .product-table .action-buttons button { padding: 6px 10px; border-radius: 6px; font-size: 0.9rem; cursor: pointer; }
        .product-table .edit-btn { background-color: #2196F3; color: white; border: none; }
        .product-table .delete-btn { background-color: #f44336; color: white; border: none; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            h1 { font-size: 2.2rem; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .section-header h2 { margin-bottom: 0; }
            .product-form-container { padding: 20px; }
            .product-table th, .product-table td { padding: 10px; font-size: 0.9rem; }
            .product-table .action-buttons { flex-direction: column; gap: 3px; }
            .product-table .action-buttons a, .product-table .action-buttons button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="main-content">
        <h1>Produk Saya</h1>

        <div class="product-form-container">
            <h2><?php echo $product_to_edit ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h2>
            <form action="products.php" method="post">
                <input type="hidden" name="action" value="<?php echo $product_to_edit ? 'edit' : 'add'; ?>">
                <input type="hidden" name="product_id" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['id']) : ''; ?>">
                
                <label for="name">Nama Produk:</label>
                <input type="text" id="name" name="name" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['name']) : ''; ?>" required><br>
                
                <label for="img">URL Gambar (assets/...):</label>
                <input type="text" id="img" name="img" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['img']) : ''; ?>" required><br>
                
                <label for="scientific_name">Nama Ilmiah:</label>
                <input type="text" id="scientific_name" name="scientific_name" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['scientific_name']) : ''; ?>"><br>
                
                <label for="family">Familia:</label>
                <input type="text" id="family" name="family" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['family']) : ''; ?>"><br>
                
                <label for="description">Deskripsi:</label>
                <textarea id="description" name="description"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['description']) : ''; ?></textarea><br>
                
                <label for="habitat">Habitat:</label>
                <textarea id="habitat" name="habitat"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['habitat']) : ''; ?></textarea><br>
                
                <label for="care_instructions">Instruksi Perawatan:</label>
                <textarea id="care_instructions" name="care_instructions"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['care_instructions']) : ''; ?></textarea><br>
                
                <label for="unique_fact">Fakta Unik:</label>
                <textarea id="unique_fact" name="unique_fact"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['unique_fact']) : ''; ?></textarea><br>
                
                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['price']) : ''; ?>" required><br>
                
                <label for="stock">Stok:</label>
                <input type="number" id="stock" name="stock" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['stock']) : ''; ?>" required><br>
                
                <div class="form-action-btns">
                    <?php if ($product_to_edit): ?>
                        <a href="products.php" class="cancel-btn">Batal Edit</a>
                    <?php endif; ?>
                    <button type="submit" class="submit-btn"><?php echo $product_to_edit ? 'Update Produk' : 'Tambah Produk'; ?></button>
                </div>
            </form>
        </div>

        <div class="section-header">
            <h2>Daftar Produk Anda</h2>
        </div>
        <table class="product-table">
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

    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>