<?php
session_start();
include 'includes/auth_middleware.php';
require_role('admin');
require_once 'config.php';

// Fetch all products
$products = [];
$sql = "SELECT p.*, u.username as seller_username FROM products p LEFT JOIN users u ON p.seller_id = u.id";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

// Logic for ADD, EDIT, DELETE (simplified, needs full implementation)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action == 'add_product' || $action == 'edit_product') {
            $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
            $name = $_POST['name'];
            $price = $_POST['price'];
            $stock = $_POST['stock'];
            // ... other fields
            $seller_id = $_POST['seller_id']; // Admin can assign seller

            if ($action == 'add_product') {
                $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, stock, seller_id) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sddi", $name, $price, $stock, $seller_id);
            } else { // edit_product
                $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, price = ?, stock = ?, seller_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sddii", $name, $price, $stock, $seller_id, $product_id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: admin_products.php"); // Redirect after action
            exit;
        } elseif ($action == 'delete_product') {
            $product_id = $_POST['product_id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: admin_products.php"); // Redirect after action
            exit;
        }
    }
}

// You would include HTML forms for add/edit product here, perhaps in a modal or separate page.
// Example: Minimal HTML structure
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk Admin - PlantPals</title>
    <style>
        /* Add some basic styling for tables and forms */
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-buttons a, .action-buttons button { margin-right: 5px; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .add-btn { background-color: #4CAF50; color: white; border: none; }
        .edit-btn { background-color: #2196F3; color: white; border: none; }
        .delete-btn { background-color: #f44336; color: white; border: none; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
        input[type="text"], input[type="number"], textarea, select { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .submit-btn { background-color: #8BC34A; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; // Example header for admin ?>
    <h1>Manajemen Produk Admin</h1>

    <h2>Tambah/Edit Produk</h2>
    <form action="admin_products.php" method="post">
        <input type="hidden" name="action" value="add_product"> <input type="hidden" name="product_id" value=""> <label for="name">Nama Produk:</label>
        <input type="text" id="name" name="name" required><br>
        <label for="price">Harga:</label>
        <input type="number" id="price" name="price" step="0.01" required><br>
        <label for="stock">Stok:</label>
        <input type="number" id="stock" name="stock" required><br>
        <label for="seller_id">Penjual:</label>
        <select id="seller_id" name="seller_id">
            <?php
            $sellers = [];
            $s_res = mysqli_query($conn, "SELECT id, username FROM users WHERE role = 'seller'");
            while($s_row = mysqli_fetch_assoc($s_res)) {
                $sellers[] = $s_row;
            }
            foreach($sellers as $seller) {
                echo "<option value='" . $seller['id'] . "'>" . $seller['username'] . "</option>";
            }
            ?>
        </select><br>
        <button type="submit" class="submit-btn">Simpan Produk</button>
    </form>

    <h2>Daftar Produk</h2>
    <table>
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
                <tr><td colspan="6">Belum ada produk.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['id']; ?></td>
                    <td><?php echo $product['name']; ?></td>
                    <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                    <td><?php echo $product['stock']; ?></td>
                    <td><?php echo $product['seller_username'] ?? 'N/A'; ?></td>
                    <td class="action-buttons">
                        <a href="?edit=<?php echo $product['id']; ?>" class="edit-btn">Edit</a>
                        <form action="admin_products.php" method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Yakin ingin menghapus produk ini?');">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php mysqli_close($conn); ?>