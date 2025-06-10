<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
$role = $_SESSION['role'] ?? 'buyer';

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

include 'data.php'; // For initial product fallback data
require_once 'config.php'; // Database connection, now opened once.

// --- Handle Add to Cart Action ---
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = 1; // Default quantity for adding to cart

    if ($product_id) {
        $sql_product = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                        FROM products p
                        LEFT JOIN users u ON p.seller_id = u.id
                        WHERE p.id = ? AND (u.role = 'seller' OR p.seller_id IS NULL)";
        
        if ($stmt_product = mysqli_prepare($conn, $sql_product)) {
            mysqli_stmt_bind_param($stmt_product, "i", $product_id);
            mysqli_stmt_execute($stmt_product);
            $result_product = mysqli_stmt_get_result($stmt_product);
            $product_details = mysqli_fetch_assoc($result_product);
            mysqli_stmt_close($stmt_product);

            if ($product_details) {
                // Fetch store details for the product
                $selling_store_for_cart = null;
                if (isset($product_details['seller_id']) && isset($stores_by_seller_id[$product_details['seller_id']])) {
                    $selling_store_for_cart = $stores_by_seller_id[$product_details['seller_id']][0] ?? null;
                }

                $store_id_for_cart = $selling_store_for_cart['store_id_string'] ?? 'N/A';
                $store_name_for_cart = $selling_store_for_cart['name'] ?? 'Toko Tidak Dikenal';
                if ($selling_store_for_cart && $selling_store_for_cart['address']) {
                    $store_name_for_cart .= " - (" . htmlspecialchars($selling_store_for_cart['address']) . ")";
                }

                // Add or update item in cart
                if (isset($_SESSION['cart'][$product_id])) {
                    // Update quantity if item already in cart
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    // Add new item to cart
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product_details['id'],
                        'name' => $product_details['name'],
                        'img' => $product_details['img'],
                        'price' => $product_details['price'],
                        'stock' => $product_details['stock'],
                        'seller_id' => $product_details['seller_id'],
                        'store_id_string' => $store_id_for_cart,
                        'store_name' => $store_name_for_cart,
                        'quantity' => $quantity,
                    ];
                }
                echo "<script>alert('Produk berhasil ditambahkan ke keranjang!'); window.location.href='dashboard.php?page=cart';</script>";
                exit();
            } else {
                echo "<script>alert('Produk tidak ditemukan atau tidak tersedia.'); window.location.href='dashboard.php?page=home';</script>";
                exit();
            }
        } else {
            error_log("Error preparing product fetch for cart: " . mysqli_error($conn));
            echo "<script>alert('Terjadi kesalahan sistem. Mohon coba lagi.'); window.location.href='dashboard.php?page=home';</script>";
            exit();
        }
    }
}

// --- Handle Update Cart Quantity Action ---
if (isset($_POST['action']) && $_POST['action'] === 'update_cart_quantity') {
    $product_id = $_POST['product_id'] ?? null;
    $new_quantity = intval($_POST['quantity'] ?? 0);

    if ($product_id && isset($_SESSION['cart'][$product_id])) {
        if ($new_quantity > 0) {
            // Re-fetch current stock from DB for absolute accuracy
            $current_db_stock = 0;
            $stmt_stock = mysqli_prepare($conn, "SELECT stock FROM products WHERE id = ?");
            if ($stmt_stock) {
                mysqli_stmt_bind_param($stmt_stock, "i", $product_id);
                mysqli_stmt_execute($stmt_stock);
                mysqli_stmt_bind_result($stmt_stock, $current_db_stock);
                mysqli_stmt_fetch($stmt_stock);
                mysqli_stmt_close($stmt_stock);
            }

            if ($new_quantity <= $current_db_stock) {
                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                echo "<script>alert('Kuantitas diperbarui.'); window.location.href='dashboard.php?page=cart';</script>";
            } else {
                echo "<script>alert('Stok tidak mencukupi untuk kuantitas yang diminta. Stok tersedia: " . $current_db_stock . ".'); window.location.href='dashboard.php?page=cart';</script>";
            }
        } else {
            unset($_SESSION['cart'][$product_id]); // Remove if quantity is 0 or less
            echo "<script>alert('Produk dihapus dari keranjang.'); window.location.href='dashboard.php?page=cart';</script>";
        }
    }
    exit();
}

// --- Handle Remove from Cart Action ---
if (isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $product_id = $_POST['product_id'] ?? null;
    if ($product_id && isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        echo "<script>alert('Produk dihapus dari keranjang.'); window.location.href='dashboard.php?page=cart';</script>";
        exit();
    }
}


// --- Fetch Products from Database ---
// Pastikan kita ambil seller_id dari produk dan hanya produk dari seller yang aktif/terdaftar
$flowers_from_db = [];
$sql_products = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                 FROM products p
                 LEFT JOIN users u ON p.seller_id = u.id
                 WHERE u.role = 'seller' OR p.seller_id IS NULL  -- Hanya produk dari seller terdaftar, atau yang belum ada seller_id (utk fallback)
                 ORDER BY p.name ASC";
$result_products = mysqli_query($conn, $sql_products);
if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $flowers_from_db[] = $row;
    }
}
// Jika database kosong, gunakan data fallback
$flowers_to_display = empty($flowers_from_db) ? $all_initial_products : $flowers_from_db;


// --- Fetch Stores from Database and Organize by seller_user_id ---
// Hanya ambil toko yang dimiliki oleh user dengan role 'seller'
$stores_by_seller_id = [];
$sql_stores = "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id
               FROM stores s
               JOIN users u ON s.seller_user_id = u.id
               WHERE u.role = 'seller'
               ORDER BY s.name ASC";
$result_stores = mysqli_query($conn, $sql_stores);
if ($result_stores) {
    while ($row_store = mysqli_fetch_assoc($result_stores)) {
        $seller_id_for_store = $row_store['seller_user_id'];
        if (!isset($stores_by_seller_id[$seller_id_for_store])) {
            $stores_by_seller_id[$seller_id_for_store] = [];
        }
        $stores_by_seller_id[$seller_id_for_store][] = $row_store;
    }
}

// Fallback jika tidak ada toko yang terhubung ke seller di DB (misal baru insert user dan belum assign toko),
// atau untuk testing jika DB stores belum diisi.
// Gunakan DEFAULT_FALLBACK_SELLER_ID dari data.php
if (empty($stores_by_seller_id) && isset($DEFAULT_FALLBACK_SELLER_ID)) {
    // Asumsi ID seller1 adalah 2. Ini harus sinkron dengan ID seller di tabel 'users' Anda.
    $stores_by_seller_id = [
        $DEFAULT_FALLBACK_SELLER_ID => [ // Menggunakan ID seller default dari data.php
            ["id" => 1, "store_id_string" => "toko_bunga_asri", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Raya Puputan No. 100, Denpasar", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID],
        ],
    ];
}

// --- Fetch Featured Products (e.g., top 4 random products or specific picks) ---
$featured_products = [];
// Asumsi 'products' memiliki kolom 'is_featured' atau Anda mengambil acak
$sql_featured = "SELECT p.id, p.name, p.img, p.price, p.description, p.seller_id
                 FROM products p
                 LEFT JOIN users u ON p.seller_id = u.id
                 WHERE u.role = 'seller' OR p.seller_id IS NULL
                 ORDER BY RAND() LIMIT 4"; // Mengambil 4 produk acak sebagai unggulan
$result_featured = mysqli_query($conn, $sql_featured);
if ($result_featured) {
    while ($row = mysqli_fetch_assoc($result_featured)) {
        $featured_products[] = $row;
    }
}


// No mysqli_close($conn); here, it will be at the very end of the file.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/dashboard_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya khusus untuk keranjang belanja */
        .cart-item {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fcfcfc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }
        .cart-item-details {
            flex-grow: 1;
            text-align: left;
        }
        .cart-item-details h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: #3a5a20;
            font-size: 1.2rem;
        }
        .cart-item-details .price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #E5989B;
            margin-bottom: 10px;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-item-actions input[type="number"] {
            width: 70px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            text-align: center;
            font-size: 1rem;
        }
        .cart-item-actions .remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9em;
        }
        .cart-item-actions .remove-btn:hover {
            background-color: #c82333;
        }
        .cart-summary {
            margin-top: 30px;
            border-top: 2px solid #e0e0e0;
            padding-top: 20px;
            text-align: right;
        }
        .cart-summary p {
            font-size: 1.4rem;
            font-weight: bold;
            color: #3a5a20;
            margin-bottom: 20px;
        }
        .cart-summary .checkout-btn {
            background-color: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .cart-summary .checkout-btn:hover {
            background-color: #388e3c;
        }
        .empty-cart-message {
            text-align: center;
            color: #777;
            font-size: 1.1em;
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }

        /* Gaya untuk tombol "Lihat Detail" dan "Tambah ke Keranjang" */
        .card-buttons-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px; /* Jarak antar tombol */
            padding: 0 20px 20px; /* Padding sama seperti sebelumnya */
        }
        .card-buttons-container .see-more-btn,
        .card-buttons-container .buy-button {
            flex: 1; /* Agar tombol mengisi ruang yang tersedia */
            width: auto; /* Override width: calc(100% - 40px) */
            margin: 0; /* Hapus margin individu */
            padding: 10px 15px; /* Sesuaikan padding agar lebih ringkas */
            font-size: 0.95rem; /* Sesuaikan ukuran font */
        }
        .card-buttons-container .see-more-btn {
            background-color: #3a5a20; /* Warna hijau untuk lihat detail */
        }
        .card-buttons-container .see-more-btn:hover {
            background-color: #2f4d3a;
        }

        /* Penyesuaian untuk product-item-page di halaman products.php */
        .product-item-page .card-buttons-container {
            flex-direction: column; /* Pada halaman produk, mungkin lebih baik bertumpuk */
            gap: 10px;
            padding: 0 20px 20px;
        }
        .product-item-page .card-buttons-container .buy-button {
             width: 100%;
        }
        .product-item-page .card-buttons-container .see-more-btn {
            display: none; /* Sembunyikan tombol detail jika hanya ingin tombol beli */
        }

        /* Gaya baru untuk Banner Promosi */
        .promo-banner {
            background: linear-gradient(to right, #d9f2d9, #f0fff0);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .promo-banner h3 {
            font-size: 2.2rem;
            color: #3a5a20;
            margin-bottom: 15px;
        }
        .promo-banner p {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 25px;
        }
        .promo-banner .btn-promo {
            background-color: #E5989B;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .promo-banner .btn-promo:hover {
            background-color: rgb(182, 88, 117);
        }

        /* Gaya baru untuk Produk Unggulan */
        .featured-products-section {
            margin-bottom: 40px;
        }
        .featured-products-section .section-heading {
            font-size: 2rem;
            color: #3a5a20;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        .featured-products-section .section-heading::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #E5989B;
            margin: 15px auto 0;
            border-radius: 2px;
        }
        .featured-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        .featured-item.card { /* Menggunakan kembali gaya .card */
            /* Pastikan gaya card di sini sesuai dengan .grid .card */
            text-align: left; /* Rata kiri untuk featured item */
        }
        .featured-item.card .card-content {
            padding-bottom: 0; /* Hindari double padding dengan container tombol */
        }
        .featured-item.card .card-buttons-container {
             padding-top: 15px; /* Tambahkan padding atas untuk memisahkan dari konten */
        }
    </style>
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <div class="container">
        <nav class="sidebar">
            <a href="dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>"><i class="fas fa-seedling"></i> Produk</a>
            <a href="dashboard.php?page=cart" class="<?php echo ($page == 'cart') ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Keranjang (<?php echo count($_SESSION['cart']); ?>)</a>
            <a href="dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>"><i class="fas fa-box-open"></i> Pesanan Saya</a>
            <a href="dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> Profil</a>
            <a href="dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Kontak</a>
        </nav>

        <main class="content">
            <?php
            if ($page == 'home') {
                ?>
                <h2>Selamat Datang di PlantPals!</h2>
                <form class="search-bar" action="dashboard.php" method="get">
                    <input type="hidden" name="page" value="home" />
                    <input type="text" id="searchInput" name="q" placeholder="Cari tanaman hias atau kebutuhan kebun..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>

                <div class="promo-banner">
                    <h3>Diskon Spesial!</h3>
                    <p>Dapatkan Potongan Harga 20% untuk semua tanaman hias di bulan ini! Waktu Terbatas!</p>
                    <a href="dashboard.php?page=products" class="btn-promo"><i class="fas fa-tags"></i> Lihat Promosi Sekarang</a>
                </div>

                <?php if (!empty($featured_products)): ?>
                <div class="featured-products-section">
                    <h2 class="section-heading">Produk Unggulan Pilihan Kami</h2>
                    <div class="featured-products-grid grid"> <?php foreach ($featured_products as $f_product): ?>
                            <div class="featured-item card">
                                <img src="<?php echo htmlspecialchars($f_product['img']); ?>" alt="<?php echo htmlspecialchars($f_product['name']); ?>" />
                                <div class="card-content">
                                    <h3><?php echo htmlspecialchars($f_product['name']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($f_product['description'] ?? '', 0, 70)); ?>...</p>
                                    <p class="price">Rp <?php echo number_format($f_product['price'], 0, ',', '.'); ?></p>
                                </div>
                                <div class="card-buttons-container">
                                    <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $f_product['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                    <form action="dashboard.php" method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($f_product['id']); ?>">
                                        <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Add</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <h2 class="section-heading">Jelajahi Semua Produk</h2> <div id="flowerGrid" class="grid">
                <?php
                $filtered_flowers = [];
                $keyword = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

                if (!empty($keyword)) {
                    foreach ($flowers_to_display as $flower) {
                        if (
                            stripos($flower['name'], $keyword) !== false ||
                            stripos($flower['description'] ?? '', $keyword) !== false ||
                            stripos($flower['scientific_name'] ?? $flower['scientific'] ?? '', $keyword) !== false ||
                            stripos($flower['family'] ?? '', $keyword) !== false
                        ) {
                            $filtered_flowers[] = $flower;
                        }
                    }
                } else {
                    $filtered_flowers = $flowers_to_display;
                }

                if (empty($filtered_flowers)) {
                    echo "<p class='no-results'>Tidak ada hasil untuk pencarian Anda.</p>";
                } else {
                    foreach ($filtered_flowers as $flower) {
                        $card_id = htmlspecialchars($flower['id'] ?? 'fallback_' . uniqid());
                        ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($flower['name']); ?></h3>
                                <p><strong>Nama Ilmiah:</strong> <?php echo htmlspecialchars($flower['scientific_name'] ?? $flower['scientific'] ?? 'N/A'); ?></p>
                                <p><strong>Familia:</strong> <?php echo htmlspecialchars($flower['family'] ?? 'N/A'); ?></p>
                                <p><?php echo htmlspecialchars(substr($flower['description'] ?? '', 0, 80)); ?>...</p>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                            </div>
                            <div class="card-buttons-container">
                                <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
                </div> <?php
            } elseif ($page == 'products') {
                ?>
                <div class="page-content-panel">
                    <h2>Katalog Produk Kami</h2>
                    <p class="page-description">Temukan berbagai tanaman hias pilihan dari penjual terpercaya!</p>
                    <div class="product-list-page">
                        <?php foreach ($flowers_to_display as $flower):
                            $selling_store = null;
                            if (isset($flower['seller_id']) && isset($stores_by_seller_id[$flower['seller_id']])) {
                                $selling_store = $stores_by_seller_id[$flower['seller_id']][0] ?? null;
                            }
                            $store_link = "#";
                            $store_name_display = "Toko Tidak Dikenal";
                            $store_id_string_for_order = "";
                            $store_name_full_for_order = "";

                            if ($selling_store) {
                                $store_link = "store_profile.php?store_id_string=" . urlencode($selling_store['store_id_string']);
                                $store_name_display = htmlspecialchars($selling_store['name']);
                                $store_id_string_for_order = htmlspecialchars($selling_store['store_id_string']);
                                $store_name_full_for_order = htmlspecialchars($selling_store['name'] . " - (" . $selling_store['address'] . ")");
                            }
                        ?>
                        <div class="product-item-page">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h4><?php echo htmlspecialchars($flower['name']); ?></h4>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                                <div class="store-info-display">
                                    <span class="label"><i class="fas fa-store"></i> Dijual oleh:</span>
                                    <?php if ($selling_store): ?>
                                        <a href="<?php echo $store_link; ?>" class="store-name-link">
                                            <?php echo $store_name_display; ?>
                                            <?php if ($selling_store['address']) echo " - (" . htmlspecialchars($selling_store['address']) . ")"; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="store-name-link"><?php echo $store_name_display; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-buttons-container">
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Tambah ke Keranjang</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            } elseif ($page == 'cart') {
                // Halaman Keranjang Belanja - Implementasi
                $cart_items = $_SESSION['cart'] ?? [];
                $total_cart_amount = 0;
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-shopping-cart"></i> Keranjang Belanja Anda</h2>
                    <?php if (empty($cart_items)): ?>
                        <div class="empty-cart-message">
                            <i class="fas fa-box-open fa-3x"></i>
                            <p>Keranjang Anda masih kosong.</p>
                            <a href="dashboard.php?page=products" class="btn-primary" style="margin-top: 20px;">Mulai Belanja</a>
                        </div>
                    <?php else: ?>
                        <div class="cart-items-list">
                            <?php foreach ($cart_items as $product_id => $item):
                                $subtotal = $item['price'] * $item['quantity'];
                                $total_cart_amount += $subtotal;
                            ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                                        <p>Dari: <?php echo htmlspecialchars($item['store_name']); ?></p>
                                    </div>
                                    <div class="cart-item-actions">
                                        <form action="dashboard.php" method="post" style="display:flex; align-items:center;">
                                            <input type="hidden" name="action" value="update_cart_quantity">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                            <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" onchange="this.form.submit()">
                                            <button type="submit" class="buy-button" style="display:none;">Update</button> </form>
                                        <form action="dashboard.php" method="post">
                                            <input type="hidden" name="action" value="remove_from_cart">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                            <button type="submit" class="remove-btn"><i class="fas fa-trash"></i> Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="cart-summary">
                            <p>Total Keranjang: Rp <?php echo number_format($total_cart_amount, 0, ',', '.'); ?></p>
                            <form action="order_form.php" method="post">
                                <input type="hidden" name="action" value="checkout_cart">
                                <?php foreach ($cart_items as $product_id => $item): ?>
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][id]" value="<?php echo htmlspecialchars($item['id']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][name]" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][price]" value="<?php echo htmlspecialchars($item['price']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_id_string]" value="<?php echo htmlspecialchars($item['store_id_string']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_name]" value="<?php echo htmlspecialchars($item['store_name']); ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="checkout-btn"><i class="fas fa-money-check-alt"></i> Lanjutkan ke Pembayaran</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'profile') {
                // Koneksi sudah ada dari config.php di awal file
                $user_data = [];
                $sql_user = "SELECT * FROM users WHERE id = ?";
                if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
                    mysqli_stmt_bind_param($stmt_user, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_user);
                    $result_user = mysqli_stmt_get_result($stmt_user);
                    $user_data = mysqli_fetch_assoc($result_user);
                    mysqli_stmt_close($stmt_user);
                }
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-user-circle"></i> Profil Pengguna</h2>
                    <p class="page-description">Kelola informasi akun Anda di sini.</p>
                    <div class="profile-info">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username'] ?? $username); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? 'N/A'); ?></p>
                        <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A'); ?></p>
                        <p><strong>Nomor Telepon:</strong> <?php echo htmlspecialchars($user_data['phone_number'] ?? 'N/A'); ?></p>
                        <p><strong>Alamat:</strong> <?php echo htmlspecialchars($user_data['address'] ?? 'N/A'); ?></p>
                        <p><strong>Bergabung Sejak:</strong> <?php echo htmlspecialchars(date('d F Y', strtotime($user_data['created_at'] ?? 'now'))); ?></p>
                        <p><strong>Status Akun:</strong> Aktif (<?php echo htmlspecialchars($user_data['role'] ?? 'buyer'); ?>)</p>
                        <button class="profile-info-btn"><i class="fas fa-edit"></i> Edit Profil</button>
                    </div>
                </div>
                <?php
            } elseif ($page == 'orders') {
                // Koneksi sudah ada dari config.php di awal file
                $user_orders = [];
                $sql_orders = "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC";
                if ($stmt_orders = mysqli_prepare($conn, $sql_orders)) {
                    mysqli_stmt_bind_param($stmt_orders, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_orders);
                    $result_orders = mysqli_stmt_get_result($stmt_orders);
                    while ($row = mysqli_fetch_assoc($result_orders)) {
                        $order_items = [];
                        $item_sql = "SELECT * FROM order_items WHERE order_id = ?";
                        if ($item_stmt = mysqli_prepare($conn, $item_sql)) {
                            mysqli_stmt_bind_param($item_stmt, "i", $row['id']);
                            mysqli_stmt_execute($item_stmt);
                            $item_result = mysqli_stmt_get_result($item_stmt);
                            while ($item_row = mysqli_fetch_assoc($item_result)) {
                                $order_items[] = $item_row;
                            }
                            mysqli_stmt_close($item_stmt);
                        }
                        $row['items'] = $order_items;
                        $user_orders[] = $row;
                    }
                    mysqli_stmt_close($stmt_orders);
                }
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-box-open"></i> Pesanan Anda</h2>
                    <p class="page-description">Berikut adalah daftar pesanan yang telah Anda lakukan.</p>
                    <?php if (empty($user_orders)): ?>
                        <p class="no-results" style="margin-top: 20px;">Tidak ada pesanan yang ditemukan.</p>
                    <?php else: ?>
                        <ul class="order-list">
                            <?php foreach ($user_orders as $order): ?>
                                <li>
                                    <div class="order-header">
                                        <span><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order['id']); ?></span>
                                        <span><strong>Tanggal:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></span>
                                    </div>
                                    <div class="order-summary-footer">
                                        <span><strong>Total:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                        <span><strong>Status:</strong> <span class="status-badge <?php echo htmlspecialchars($order['order_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span></span>
                                    </div>
                                    <div class="order-items-detail">
                                        <strong>Item:</strong>
                                        <ul>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <li>- <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['quantity']); ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> (dari <?php echo htmlspecialchars($item['store_name']); ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'contact') {
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-envelope-open-text"></i> Hubungi Kami</h2>
                    <p class="page-description">Kami siap membantu Anda. Silakan hubungi kami melalui informasi di bawah ini:</p>
                    <div class="contact-info">
                        <p><i class="fas fa-at"></i> <strong>Email:</strong> info@plantpals.com</p>
                        <p><i class="fas fa-phone-alt"></i> <strong>Telepon:</strong> +62 812-3456-7890</p>
                        <p><i class="fas fa-map-marked-alt"></i> <strong>Alamat:</strong> Jl. Bunga Indah No. 123, Denpasar, Bali, Indonesia</p>
                    </div>
                    <h3 class="section-sub-title">Form Kontak</h3>
                    <form class="contact-form">
                        <div class="form-group">
                            <label for="contactName"><i class="fas fa-user"></i> Nama Anda:</label>
                            <input type="text" id="contactName" placeholder="Nama Lengkap Anda" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="contactEmail"><i class="fas fa-envelope"></i> Email Anda:</label>
                            <input type="email" id="contactEmail" placeholder="email@contoh.com" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="contactMessage"><i class="fas fa-comment-dots"></i> Pesan Anda:</label>
                            <textarea id="contactMessage" placeholder="Tulis pesan Anda di sini..." name="message" rows="6"></textarea>
                        </div>
                        <button type="submit" class="submit-button"><i class="fas fa-paper-plane"></i> Kirim Pesan</button>
                    </form>
                </div>
                <?php
            } else {
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-exclamation-triangle"></i> Halaman Tidak Ditemukan</h2>
                    <p class="no-results">Halaman yang Anda cari tidak tersedia. Silakan kembali ke <a href="dashboard.php?page=home">Home</a>.</p>
                </div>
                <?php
            }
            ?>
        </main>
    </div>
    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        // Fungsi handleOrder kini tidak lagi digunakan untuk tombol "Pesan Sekarang"
        // karena diganti dengan "Tambah ke Keranjang"
        // Fungsi ini bisa dihapus jika tidak ada lagi tombol yang langsung menuju order_form.php
        /*
        function handleOrder(productName, productPrice, storeIdString, storeNameFull) {
            if (storeIdString === "" || storeNameFull === "") {
                alert("Informasi toko tidak lengkap untuk produk ini.");
                return;
            }

            const urlParams = [];
            urlParams.push('product_name=' + encodeURIComponent(productName));
            urlParams.push('product_price=' + encodeURIComponent(productPrice));
            urlParams.push('store_id=' + encodeURIComponent(storeIdString));
            urlParams.push('store_name=' + encodeURIComponent(storeNameFull));

            window.location.href = `/PlantPals/order_form.php?${urlParams.join('&')}`;
        }
        */
    </script>
</body>
</html>
<?php
// Close connection once at the very end of the file
mysqli_close($conn);
?>