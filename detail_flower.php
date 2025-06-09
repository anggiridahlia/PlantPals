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
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <h1 class="page-main-title">Detail Bunga</h1>

    <div class="main-content-area">
        <?php if ($selected_flower): ?>
            <div class="flower-details-column card-panel">
                <img src="<?php echo htmlspecialchars($selected_flower['img']); ?>" alt="<?php echo htmlspecialchars($selected_flower['name']); ?>" />
                <h3><?php echo htmlspecialchars($selected_flower['name']); ?></h3>

                <div class="detail-item">
                    <span class="detail-label">Nama Ilmiah:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['scientific_name'] ?? $selected_flower['scientific'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Familia:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['family'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Deskripsi:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['description'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Habitat:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['habitat'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Perawatan:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['care_instructions'] ?? $selected_flower['care'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Fakta unik:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($selected_flower['unique_fact'] ?? $selected_flower['fact'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="purchase-column card-panel">
                <h2 class="section-heading">Pesan Bunga Ini</h2>
                <p class="price-display">Rp <?php echo number_format($selected_flower['price'], 0, ',', '.'); ?></p>
                <div class="store-selection-detail">
                    <label for="store-select-detail">Pilih Toko untuk Pengiriman:</label>
                    <select id="store-select-detail" name="store">
                        <?php
                        // Dapatkan daftar toko yang relevan untuk produk ini (dari seller_id produk)
                        $stores_for_this_product_detail = $stores_by_seller_id[$selected_flower['seller_id']] ?? [];

                        if (empty($stores_for_this_product_detail)) {
                            ?>
                            <option value="">Tidak Ada Toko Tersedia</option>
                        <?php } else { ?>
                            <?php foreach ($stores_for_this_product_detail as $store_item): ?>
                                <option value="<?php echo htmlspecialchars($store_item['store_id_string']); ?>">
                                    <?php echo htmlspecialchars($store_item['name']); ?> - (<?php echo htmlspecialchars($store_item['address']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php } ?>
                    </select>
                </div>
                <button class="buy-button-detail"
                        onclick="handleOrder('<?php echo htmlspecialchars($selected_flower['name']); ?>', '<?php echo htmlspecialchars($selected_flower['price']); ?>', 'store-select-detail');"
                        <?php echo (empty($stores_for_this_product_detail)) ? 'disabled' : ''; ?>>
                    Pesan Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="flower-details-column card-panel" style="text-align: center; width: 100%;">
                <h3>Bunga Tidak Ditemukan</h3>
                <p>Informasi bunga yang Anda cari tidak tersedia. Pastikan nama bunga yang Anda masukkan benar.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_flower && !empty($recommended_flowers)): ?>
        <div class="main-content-area recommended-flowers-section-container card-panel">
            <h2 class="section-heading">Rekomendasi Bunga Lainnya</h2>
            <div class="recommended-grid">
                <?php foreach ($recommended_flowers as $rec_flower): ?>
                    <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $rec_flower['name']))); ?>" class="recommended-item">
                        <img src="<?php echo htmlspecialchars($rec_flower['img']); ?>" alt="<?php echo htmlspecialchars($rec_flower['name']); ?>">
                        <h4><?php echo htmlspecialchars($rec_flower['name']); ?></h4>
                        <p class="price">Rp <?php echo number_format($rec_flower['price'], 0, ',', '.'); ?></p>
                        <span class="view-button">Lihat Detail</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="back-btn-container">
        <a href="/PlantPals/dashboard.php?page=home" class="back-btn">Kembali ke Home</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        function handleOrder(productName, productPrice, storeSelectId) {
            const storeSelect = document.getElementById(storeSelectId);
            const selectedStoreOption = storeSelect.options[storeSelect.selectedIndex];
            const selectedStoreId = selectedStoreOption.value;
            const selectedStoreName = selectedStoreOption.text;

            if (selectedStoreId === "") { // Check if 'Tidak Ada Toko Tersedia' is selected
                alert("Mohon pilih toko yang tersedia untuk produk ini.");
                return; // Stop the function
            }

            const urlParams = [];
            urlParams.push('product_name=' + encodeURIComponent(productName));
            urlParams.push('product_price=' + encodeURIComponent(productPrice));
            urlParams.push('store_id=' + encodeURIComponent(selectedStoreId));
            urlParams.push('store_name=' + encodeURIComponent(selectedStoreName));

            window.location.href = `/PlantPals/order_form.php?${urlParams.join('&')}`;
        }
    </script>
</body>
</html>
<?php
// Close connection once at the very end of the file
mysqli_close($conn);
?>