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

// --- Fetch ALL stores from database and Organize by seller_user_id ---
// Bagian ini dipindahkan ke atas agar bisa digunakan oleh logika add_to_cart
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
    if (isset($DEFAULT_FALLBACK_SELLER_ID)) {
        $stores_by_seller_id = [
            $DEFAULT_FALLBACK_SELLER_ID => [
                ["id" => 1, "store_id_string" => "toko_bunga_asri", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Raya Puputan No. 100, Denpasar", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID],
            ],
        ];
    }
}

// --- Handle Add to Cart Action from this page ---
// Tambahkan logging untuk debugging
error_log("detail_flower.php: Request method is " . $_SERVER['REQUEST_METHOD']);
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart_from_detail') {
    error_log("detail_flower.php: Add to cart action detected.");
    $product_id = $_POST['product_id'] ?? null;
    $quantity = 1; // Default quantity for adding to cart from detail page, can be expanded to allow user input

    if ($product_id) {
        error_log("detail_flower.php: Product ID " . $product_id . " received for add to cart.");
        $sql_product_for_cart = "SELECT p.id, p.name, p.img, p.price, p.stock, p.seller_id
                                FROM products p
                                LEFT JOIN users u ON p.seller_id = u.id
                                WHERE p.id = ? AND (u.role = 'seller' OR p.seller_id IS NULL)";
        
        // Debugging koneksi dan prepared statement
        if (!$conn) {
            error_log("detail_flower.php: DB connection is null before prepare statement.");
            echo "<script>alert('ERROR: Koneksi database tidak aktif. Mohon coba lagi.'); window.location.href='dashboard.php?page=home';</script>";
            exit();
        }

        if ($stmt_product_for_cart = mysqli_prepare($conn, $sql_product_for_cart)) {
            mysqli_stmt_bind_param($stmt_product_for_cart, "i", $product_id);
            if (mysqli_stmt_execute($stmt_product_for_cart)) {
                $result_product_for_cart = mysqli_stmt_get_result($stmt_product_for_cart);
                $product_details_for_cart = mysqli_fetch_assoc($result_product_for_cart);
                mysqli_stmt_close($stmt_product_for_cart);

                if ($product_details_for_cart) {
                    error_log("detail_flower.php: Product details fetched for ID " . $product_id);
                    // Fetch store details for the product
                    $selling_store_for_cart = null;
                    if (isset($product_details_for_cart['seller_id']) && isset($stores_by_seller_id[$product_details_for_cart['seller_id']])) {
                        $selling_store_for_cart = $stores_by_seller_id[$product_details_for_cart['seller_id']][0] ?? null;
                    }

                    $store_id_for_cart = $selling_store_for_cart['store_id_string'] ?? 'N/A';
                    $store_name_for_cart = $selling_store_for_cart['name'] ?? 'Toko Tidak Dikenal';
                    if ($selling_store_for_cart && $selling_store_for_cart['address']) {
                        $store_name_for_cart .= " - (" . htmlspecialchars($selling_store_for_cart['address']) . ")";
                    }

                    // Add or update item in cart session
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    if (isset($_SESSION['cart'][$product_id])) {
                        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                    } else {
                        $_SESSION['cart'][$product_id] = [
                            'id' => $product_details_for_cart['id'],
                            'name' => $product_details_for_cart['name'],
                            'img' => $product_details_for_cart['img'],
                            'price' => $product_details_for_cart['price'],
                            'stock' => $product_details_for_cart['stock'],
                            'seller_id' => $product_details_for_cart['seller_id'],
                            'store_id_string' => $store_id_for_cart,
                            'store_name' => $store_name_for_cart,
                            'quantity' => $quantity,
                        ];
                    }
                    error_log("detail_flower.php: Product " . $product_details_for_cart['name'] . " added to cart. Redirecting.");
                    echo "<script>alert('Produk berhasil ditambahkan ke keranjang!'); window.location.href='dashboard.php?page=cart';</script>";
                    exit();
                } else {
                    error_log("detail_flower.php: Product not found in DB for ID " . $product_id);
                    echo "<script>alert('Produk tidak ditemukan atau tidak tersedia.'); window.location.href='dashboard.php?page=home';</script>";
                    exit();
                }
            } else {
                error_log("detail_flower.php: Error executing statement to fetch product details: " . mysqli_stmt_error($stmt_product_for_cart));
                echo "<script>alert('Terjadi kesalahan database saat mengambil info produk. Mohon coba lagi.'); window.location.href='dashboard.php?page=home';</script>";
                exit();
            }
        } else {
            error_log("detail_flower.php: Error preparing product fetch for add_to_cart: " . mysqli_error($conn));
            echo "<script>alert('Terjadi kesalahan sistem. Mohon coba lagi.'); window.location.href='dashboard.php?page=home';</script>";
            exit();
        }
    } else {
        error_log("detail_flower.php: Product ID not received for add to cart.");
        echo "<script>alert('Produk tidak ditemukan atau tidak tersedia.'); window.location.href='dashboard.php?page=home';</script>";
        exit();
    }
}


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
            ORDER BY RAND() LIMIT 6"; // Mengambil 6 produk acak
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
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
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
                <h2 class="section-heading">Beli Produk Ini</h2>
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
                <form action="detail_flower.php" method="post" style="margin:0;">
                    <input type="hidden" name="action" value="add_to_cart_from_detail">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selected_flower['id'] ?? ''); ?>"> <button type="submit" class="buy-button-detail"
                            <?php echo ($selling_store_detail ? '' : 'disabled'); // Disable if no selling store found? ?>>
                        <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flower-details-column card-panel" style="text-align: center; width: 100%;">
                <h3><i class="fas fa-exclamation-triangle"></i> Bunga Tidak Ditemukan</h3>
                <p>Informasi bunga yang Anda cari tidak tersedia. Pastikan nama bunga yang Anda masukkan benar.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_flower && !empty($recommended_flowers)): ?>
        <div class="recommended-flowers-section-container card-panel"> <h2 class="section-heading"><i class="fas fa-seedling"></i> Rekomendasi Bunga Lainnya</h2>
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

</body>
</html>
<?php
// Close connection once at the very end of the file
mysqli_close($conn);
?>