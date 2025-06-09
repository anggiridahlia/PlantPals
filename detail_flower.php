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

include 'data.php';
require_once 'config.php'; // Database connection, now opened once.

$selected_flower = null;
if (isset($_GET['name'])) {
    $flower_param = strtolower(str_replace('_', ' ', trim($_GET['name'])));

    // Fetch product from database, INCLUDING seller_id
    // Join dengan tabel users untuk memastikan seller_id yang valid
    $stmt = mysqli_prepare($conn, "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                                 FROM products p
                                 LEFT JOIN users u ON p.seller_id = u.id
                                 WHERE LOWER(p.name) = ? AND (u.role = 'seller' OR p.seller_id IS NULL)"); // Hanya produk dari seller terdaftar, atau yang belum ada seller_id
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $flower_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $selected_flower = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    // If product not found in DB, try initial data as fallback
    // Note: Fallback data might not have a real seller_id in 'users' table,
    // so its stores might not appear if not explicitly set.
    if (!$selected_flower) {
        foreach ($all_initial_products as $flower) {
            if (isset($flower['name']) && strtolower($flower['name']) === $flower_param) {
                $selected_flower = $flower;
                // For fallback products, ensure a seller_id is set if it's missing,
                // so stores can potentially be found from the fallback array.
                if (!isset($selected_flower['seller_id']) && isset($DEFAULT_FALLBACK_SELLER_ID)) {
                    $selected_flower['seller_id'] = $DEFAULT_FALLBACK_SELLER_ID;
                }
                break;
            }
        }
    }
}

// Fetch ALL stores from database and Organize by seller_user_id
// Pastikan hanya toko yang dimiliki oleh user dengan role 'seller'
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
} else {
    // Fallback for stores if DB fetch fails or no stores linked to seller
    // IMPORTANT: Seller IDs here must match actual seller IDs in your users table
    if (isset($DEFAULT_FALLBACK_SELLER_ID)) {
        $stores_by_seller_id = [
            $DEFAULT_FALLBACK_SELLER_ID => [ // Using default seller ID from data.php
                ["id" => 1, "store_id_string" => "toko_bunga_asri", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Raya Puputan No. 100, Denpasar", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID],
            ],
        ];
    }
}

// Dapatkan informasi toko yang menjual produk yang dipilih
$selling_store_detail = null;
if ($selected_flower && isset($selected_flower['seller_id']) && isset($stores_by_seller_id[$selected_flower['seller_id']])) {
    $selling_store_detail = $stores_by_seller_id[$selected_flower['seller_id']][0] ?? null; // Ambil toko pertama dari seller
}
$store_link_detail = "#";
$store_name_display_detail = "Toko Tidak Dikenal";
$store_id_string_for_order_detail = "";
$store_name_full_for_order_detail = ""; // For full name in order form

if ($selling_store_detail) {
    $store_link_detail = "store_profile.php?store_id_string=" . urlencode($selling_store_detail['store_id_string']);
    $store_name_display_detail = htmlspecialchars($selling_store_detail['name']);
    $store_id_string_for_order_detail = htmlspecialchars($selling_store_detail['store_id_string']);
    $store_name_full_for_order_detail = htmlspecialchars($selling_store_detail['name'] . " - (" . $selling_store_detail['address'] . ")");
}


// Generate recommendations (6 random unique flowers, excluding the selected one)
$recommended_flowers = [];
$all_products_db = [];
// Select products from database, ensure they are from a seller, or have a fallback seller_id
$sql_all = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            WHERE u.role = 'seller' OR p.seller_id IS NULL
            ORDER BY RAND()";
$result_all = mysqli_query($conn, $sql_all);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_products_db[] = $row;
    }
}
// If no products in DB, or insufficient, use initial fallback data
if (empty($all_products_db) && isset($all_initial_products)) {
    $all_products_db = $all_initial_products;
}

if ($selected_flower) {
    $count = 0;
    foreach ($all_products_db as $f) {
        // Ensure 'name' key exists and is not empty before comparison
        if (isset($f['name']) && $f['name'] !== $selected_flower['name'] && !empty($f['img']) && $count < 6) {
            $recommended_flowers[] = $f;
            $count++;
        }
    }
}

// No mysqli_close($conn); here, it will be at the very end of the file.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $selected_flower ? htmlspecialchars($selected_flower['name']) : 'Detail Bunga'; ?> - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/detail_flower_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Mengatur ulang gaya .store-info-display yang sudah ada di dashboard_styles.css */
        .store-info-display {
            margin-top: 15px;
            text-align: center;
        }
        .store-info-display .label {
            font-weight: bold;
            color: #3a5a20;
            display: block;
            margin-bottom: 5px;
            display: flex; /* Untuk ikon */
            align-items: center;
            justify-content: center; /* Pusatkan label juga */
            gap: 8px; /* Jarak antara ikon dan teks label */
        }
        .store-name-link {
            display: inline-block;
            padding: 8px 15px;
            background-color: #f0f8ff; /* Light blue/white background */
            border: 1px solid #c3d9c3; /* Light green border */
            border-radius: 8px;
            text-decoration: none;
            color: #E5989B; /* Pinkish color */
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .store-name-link:hover {
            background-color: #e6f7ff; /* Slightly darker on hover */
            border-color: #E5989B;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .buy-button-detail {
            margin-top: 20px;
            /* Menggunakan gaya tombol dari main_styles.css atau dashboard_styles.css */
            display: inline-flex; /* Untuk ikon */
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #E5989B;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }
        .buy-button-detail:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(182, 88, 117, 0.4);
        }

        /* Gaya baru untuk bagian detail item bunga */
        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px; /* Jarak antara ikon dan teks */
            margin-bottom: 10px;
            font-size: 1.1em;
            color: #555;
            text-align: left; /* Biar rata kiri */
        }
        .detail-item i {
            color: #E5989B; /* Warna ikon */
            font-size: 1.3em;
            width: 25px; /* Pastikan lebar ikon seragam */
            text-align: center;
            flex-shrink: 0; /* Jangan biarkan ikon menyusut */
        }
        .detail-item strong {
            color: #3a5a20; /* Warna label hijau tua */
            min-width: 120px; /* Lebar minimum untuk label agar rapi */
        }
        .detail-item .detail-value {
            flex-grow: 1; /* Konten mengisi sisa ruang */
        }

        .flower-details-column {
            padding: 30px;
            text-align: center;
        }
        .flower-details-column h3 {
            font-size: 2.2rem;
            color: #3a5a20;
            margin-bottom: 25px;
            position: relative;
        }
        .flower-details-column h3::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #E5989B;
            margin: 15px auto 0;
            border-radius: 2px;
        }
        .flower-details-column img {
            max-width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .purchase-column .section-heading {
            font-size: 2.2rem;
            color: #E5989B;
            margin-bottom: 25px;
            position: relative;
        }
        .purchase-column .section-heading::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #3a5a20;
            margin: 15px auto 0;
            border-radius: 2px;
        }
        .purchase-column .price-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3a5a20;
            margin-bottom: 30px;
        }

        /* Responsive adjustments for detail page */
        @media (max-width: 768px) {
            .main-content-area {
                flex-direction: column;
                margin: 20px;
                padding: 0;
            }
            .flower-details-column, .purchase-column {
                width: 100%;
                margin-bottom: 20px;
                padding: 20px;
            }
            .flower-details-column img {
                height: 250px;
            }
            .flower-details-column h3, .purchase-column .section-heading {
                font-size: 1.8rem;
            }
            .purchase-column .price-display {
                font-size: 2rem;
            }
            .detail-item {
                font-size: 1em;
                flex-direction: column; /* Stack label and value vertically */
                align-items: flex-start;
                gap: 5px;
            }
            .detail-item strong {
                min-width: unset;
                width: 100%;
            }
            .detail-item .detail-value {
                padding-left: 30px; /* Indent value slightly */
            }
        }
    </style>
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <nav>
            <a href="/PlantPals/dashboard.php?page=home"><i class="fas fa-home"></i> Home</a>
            <a href="/PlantPals/dashboard.php?page=products"><i class="fas fa-seedling"></i> Produk</a>
            <a href="/PlantPals/dashboard.php?page=cart"><i class="fas fa-shopping-cart"></i> Keranjang</a>
            <a href="/PlantPals/dashboard.php?page=orders"><i class="fas fa-box-open"></i> Pesanan Saya</a>
            <a href="/PlantPals/dashboard.php?page=profile"><i class="fas fa-user-circle"></i> Profil</a>
            <a href="/PlantPals/dashboard.php?page=contact"><i class="fas fa-envelope"></i> Kontak</a>
        </nav>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <h1 class="page-main-title">Detail Bunga</h1>

    <div class="main-content-area">
        <?php if ($selected_flower): ?>
            <div class="flower-details-column card-panel">
                <img src="<?php echo htmlspecialchars($selected_flower['img']); ?>" alt="<?php echo htmlspecialchars($selected_flower['name']); ?>" />
                <h3><?php echo htmlspecialchars($selected_flower['name']); ?></h3>

                <div class="detail-item">
                    <i class="fas fa-tag"></i> <span class="detail-label">Nama Ilmiah:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['scientific_name'] ?? $selected_flower['scientific'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-tree"></i> <span class="detail-label">Familia:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['family'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-info-circle"></i> <span class="detail-label">Deskripsi:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['description'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-globe-americas"></i> <span class="detail-label">Habitat:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['habitat'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-leaf"></i> <span class="detail-label">Perawatan:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['care_instructions'] ?? $selected_flower['care'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-lightbulb"></i> <span class="detail-label">Fakta unik:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['unique_fact'] ?? $selected_flower['fact'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="purchase-column card-panel">
                <h2 class="section-heading">Pesan Bunga Ini</h2>
                <p class="price-display">Rp <?php echo number_format($selected_flower['price'], 0, ',', '.'); ?></p>
                <div class="store-info-display">
                    <span class="label"><i class="fas fa-store"></i> Dijual oleh:</span>
                    <?php if ($selling_store_detail): ?>
                        <a href="<?php echo $store_link_detail; ?>" class="store-name-link">
                            <?php echo $store_name_display_detail; ?>
                            <?php if ($selling_store_detail['address']) echo " - (" . htmlspecialchars($selling_store_detail['address']) . ")"; ?>
                        </a>
                    <?php else: ?>
                        <span class="store-name-link">Toko Tidak Dikenal</span>
                    <?php endif; ?>
                </div>
                <button class="buy-button-detail"
                        onclick="handleOrder('<?php echo htmlspecialchars($selected_flower['name']); ?>', '<?php echo htmlspecialchars($selected_flower['price']); ?>', '<?php echo $store_id_string_for_order_detail; ?>', '<?php echo $store_name_full_for_order_detail; ?>');"
                        <?php echo ($selling_store_detail ? '' : 'disabled'); ?>>
                    <i class="fas fa-shopping-cart"></i> Pesan Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="flower-details-column card-panel" style="text-align: center; width: 100%;">
                <h3><i class="fas fa-exclamation-triangle"></i> Bunga Tidak Ditemukan</h3>
                <p>Informasi bunga yang Anda cari tidak tersedia. Pastikan nama bunga yang Anda masukkan benar.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_flower && !empty($recommended_flowers)): ?>
        <div class="main-content-area recommended-flowers-section-container card-panel">
            <h2 class="section-heading"><i class="fas fa-seedling"></i> Rekomendasi Bunga Lainnya</h2>
            <div class="recommended-grid">
                <?php foreach ($recommended_flowers as $rec_flower): ?>
                    <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $rec_flower['name']))); ?>" class="recommended-item card">
                        <img src="<?php echo htmlspecialchars($rec_flower['img']); ?>" alt="<?php echo htmlspecialchars($rec_flower['name']); ?>">
                        <h4><?php echo htmlspecialchars($rec_flower['name']); ?></h4>
                        <p class="price">Rp <?php echo number_format($rec_flower['price'], 0, ',', '.'); ?></p>
                        <span class="view-button">Lihat Detail <i class="fas fa-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="back-btn-container">
        <a href="/PlantPals/dashboard.php?page=home" class="back-btn"><i class="fas fa-arrow-left"></i> Kembali ke Home</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
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