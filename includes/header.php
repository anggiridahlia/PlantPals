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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <nav>
            <?php if ($current_role == 'admin'): ?>
                <a href="/PlantPals/admin/index.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/index.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</a>
                <a href="/PlantPals/admin/products.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/products.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-box"></i> Manajemen Produk</a>
                <a href="/PlantPals/admin/users.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/users.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-users"></i> Manajemen User</a>
                <a href="/PlantPals/admin/orders.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'admin/orders.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-receipt"></i> Manajemen Pesanan</a>
            <?php elseif ($current_role == 'seller'): ?>
                <a href="/PlantPals/seller/index.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/index.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard Penjual</a>
                <a href="/PlantPals/seller/products.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/products.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-boxes"></i> Produk Saya</a>
                <a href="/PlantPals/seller/orders.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/orders.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-receipt"></i> Pesanan Saya</a>
                <a href="/PlantPals/seller/store_profile.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/store_profile.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-store"></i> Profil Toko</a>
                <a href="/PlantPals/seller/reviews.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/reviews.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-star-half-alt"></i> Ulasan Produk</a>
                <a href="/PlantPals/seller/messages.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'seller/messages.php') !== false) ? 'active' : ''; ?>"><i class="fas fa-envelope-open-text"></i> Pesan Masuk</a>
            <?php endif; ?>
        </nav>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($current_username); ?>)</button>
        </form>
    </header>
    <div class="main-content">