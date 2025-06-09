<?php
// includes/header.php
// Pastikan session_start() sudah dipanggil di halaman yang menyertakan ini.
// File ini HANYA berisi tag <header> untuk digunakan oleh admin/seller dashboard.

$current_role = $_SESSION['role'] ?? 'guest';
$current_username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantPals Dashboard</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/admin_seller_styles.css">
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <nav>
            <?php if ($current_role == 'admin'): ?>
                <a href="/PlantPals/admin/index.php">Dashboard Admin</a>
                <a href="/PlantPals/admin/products.php">Manajemen Produk</a>
                <a href="/PlantPals/admin/users.php">Manajemen User</a>
                <a href="/PlantPals/admin/orders.php">Manajemen Pesanan</a>
            <?php elseif ($current_role == 'seller'): ?>
                <a href="/PlantPals/seller/index.php">Dashboard Penjual</a>
                <a href="/PlantPals/seller/products.php">Produk Saya</a>
                <a href="/PlantPals/seller/orders.php">Pesanan Saya</a>
                <?php // Buyer links are not in this header, as buyer dashboard has its own header ?>
            <?php endif; ?>
        </nav>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo htmlspecialchars($current_username); ?>)</button>
        </form>
    </header>
    <div class="main-content">