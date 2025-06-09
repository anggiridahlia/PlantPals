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

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

include 'data.php'; // For initial product fallback data
require_once 'config.php'; // Database connection, now opened once.

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
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <nav>
            <a href="/PlantPals/dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Home</a>
            <a href="/PlantPals/dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>"><i class="fas fa-seedling"></i> Produk</a>
            <a href="/PlantPals/dashboard.php?page=cart" class="<?php echo ($page == 'cart') ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Keranjang</a>
            <a href="/PlantPals/dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>"><i class="fas fa-box-open"></i> Pesanan Saya</a>
            <a href="/PlantPals/dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> Profil</a>
            <a href="/PlantPals/dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Kontak</a>
        </nav>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <div class="container">
        <nav class="sidebar">
            <a href="dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>"><i class="fas fa-seedling"></i> Produk</a>
            <a href="dashboard.php?page=cart" class="<?php echo ($page == 'cart') ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Keranjang</a>
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
                        <i classfas fa-search></i>
                    </button>
                </form>

                <div id="flowerGrid" class="grid">
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
                        // Use unique ID for product card if ID is not available (e.g., for fallback data)
                        $card_id = htmlspecialchars($flower['id'] ?? 'fallback_' . uniqid());
                        ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($flower['name']); ?></h3>
                                <p><strong>Nama Ilmiah:</strong> <?php echo htmlspecialchars($flower['scientific_name'] ?? $flower['scientific'] ?? 'N/A'); ?></p>
                                <p><strong>Familia:</strong> <?php echo htmlspecialchars($flower['family'] ?? 'N/A'); ?></p>
                                <p><?php echo htmlspecialchars(substr($flower['description'] ?? '', 0, 80)); ?>...</p>
                            </div>
                            <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn">Lihat Detail <i class="fas fa-arrow-right"></i></a>
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
                            // Dapatkan informasi toko yang menjual produk ini
                            $selling_store = null;
                            if (isset($flower['seller_id']) && isset($stores_by_seller_id[$flower['seller_id']])) {
                                // Ambil toko pertama dari daftar yang dimiliki seller (jika ada banyak)
                                $selling_store = $stores_by_seller_id[$flower['seller_id']][0] ?? null;
                            }
                            $store_link = "#"; // Default fallback
                            $store_name_display = "Toko Tidak Dikenal";
                            $store_id_string_for_order = "";
                            $store_name_full_for_order = ""; // Untuk nama lengkap di formulir pemesanan

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
                                <button class="buy-button"
                                        onclick="handleOrder('<?php echo htmlspecialchars($flower['name']); ?>', '<?php echo htmlspecialchars($flower['price']); ?>', '<?php echo $store_id_string_for_order; ?>', '<?php echo $store_name_full_for_order; ?>');"
                                        <?php echo ($selling_store ? '' : 'disabled'); // Disable if no selling store found? ?>>
                                    <i class="fas fa-shopping-cart"></i> Pesan Sekarang
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            } elseif ($page == 'cart') {
                // Halaman Keranjang Belanja - placeholder
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-shopping-cart"></i> Keranjang Belanja Anda</h2>
                    <p class="page-description">Fitur keranjang belanja akan segera hadir! Untuk saat ini, Anda dapat langsung memesan produk dari halaman detail produk atau daftar produk.</p>
                    <div class="empty-cart-message">
                        <i class="fas fa-box-open fa-3x"></i>
                        <p>Keranjang Anda masih kosong.</p>
                        <a href="dashboard.php?page=products" class="btn-primary" style="margin-top: 20px;">Mulai Belanja</a>
                    </div>
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
        // Updated handleOrder function to accept store_id_string and store_name_full directly
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
    </script>
</body>
</html>
<?php
// Close connection once at the very end of the file
mysqli_close($conn);
?>