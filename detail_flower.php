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
$user_id = $_SESSION['id']; // Ambil user_id dari sesi untuk cek pembelian dan ulasan

include 'data.php';
require_once 'config.php'; // Database connection, now opened once.

// Variabel untuk pesan pop-up
$popup_message = "";
$popup_status = ""; // 'success' or 'error'

// --- Fetch ALL stores from database and Organize by seller_user_id ---
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
        $stores_by_seller_id[$DEFAULT_FALLBACK_SELLER_ID] = [
            ["id" => 1, "store_id_string" => "toko_bunga_asri", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Raya Puputan No. 100, Denpasar", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID],
        ];
    }
}

// --- Handle Add to Cart Action from this page ---
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart_from_detail') {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = 1;

    if ($product_id) {
        $sql_product_for_cart = "SELECT p.id, p.name, p.img, p.price, p.stock, p.seller_id
                                FROM products p
                                WHERE p.id = ?"; 
        
        if ($stmt_product_for_cart = mysqli_prepare($conn, $sql_product_for_cart)) {
            mysqli_stmt_bind_param($stmt_product_for_cart, "i", $product_id);
            mysqli_stmt_execute($stmt_product_for_cart);
            $result_product_for_cart = mysqli_stmt_get_result($stmt_product_for_cart);
            $product_details_for_cart = mysqli_fetch_assoc($result_product_for_cart);
            mysqli_stmt_close($stmt_product_for_cart);

            if ($product_details_for_cart) {
                $selling_store_for_cart = null;
                if (isset($product_details_for_cart['seller_id']) && isset($stores_by_seller_id[$product_details_for_cart['seller_id']])) {
                    $selling_store_for_cart = $stores_by_seller_id[$product_details_for_cart['seller_id']][0] ?? null;
                }

                $store_id_for_cart = $selling_store_for_cart['store_id_string'] ?? 'N/A';
                $store_name_for_cart = $selling_store_for_cart['name'] ?? 'Toko Tidak Dikenal';
                if ($selling_store_for_cart && $selling_store_for_cart['address']) {
                    $store_name_for_cart .= " - (" . htmlspecialchars($selling_store_for_cart['address']) . ")";
                }

                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                if (isset($_SESSION['cart'][$product_id])) {
                    if ($_SESSION['cart'][$product_id]['quantity'] + $quantity > $product_details_for_cart['stock']) {
                        $popup_message = "Tidak dapat menambahkan. Stok untuk " . htmlspecialchars($product_details_for_cart['name']) . " tidak mencukupi. Tersedia: " . htmlspecialchars($product_details_for_cart['stock']) . ".";
                        $popup_status = "error";
                    } else {
                        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                        $popup_message = "Produk '" . htmlspecialchars($product_details_for_cart['name']) . "' berhasil ditambahkan ke keranjang!";
                        $popup_status = "success";
                    }
                } else {
                    if ($quantity > $product_details_for_cart['stock']) {
                        $popup_message = "Tidak dapat menambahkan. Stok untuk " . htmlspecialchars($product_details_for_cart['name']) . " tidak mencukupi. Tersedia: " . htmlspecialchars($product_details_for_cart['stock']) . ".";
                        $popup_status = "error";
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
                        $popup_message = "Produk '" . htmlspecialchars($product_details_for_cart['name']) . "' berhasil ditambahkan ke keranjang!";
                        $popup_status = "success";
                    }
                }
                header('Location: detail_flower.php?name=' . urlencode(strtolower(str_replace(' ', '_', $product_details_for_cart['name']))) . '&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
                exit();
            } else {
                $popup_message = "Produk tidak ditemukan atau tidak tersedia.";
                $popup_status = "error";
                header('Location: dashboard.php?page=home&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
                exit();
            }
        } else {
            error_log("Error preparing product fetch for add_to_cart: " . mysqli_error($conn));
            $popup_message = "Terjadi kesalahan sistem. Mohon coba lagi.";
            $popup_status = "error";
            header('Location: dashboard.php?page=home&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
            exit();
        }
    }
}


// --- Handle Submit Review Action ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $product_id_review = intval($_POST['product_id_review'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    // Basic validation
    if ($product_id_review <= 0 || $rating < 1 || $rating > 5) {
        $popup_message = "Rating dan komentar tidak valid.";
        $popup_status = "error";
    } else {
        // Cek apakah user sudah membeli produk ini dan pesanan sudah completed
        $has_bought_product = false;
        $sql_check_purchase = "SELECT COUNT(oi.id) FROM order_items oi
                               JOIN orders o ON oi.order_id = o.id
                               WHERE oi.product_id = ? AND o.user_id = ? AND o.order_status = 'completed'";
        if ($stmt_check_purchase = mysqli_prepare($conn, $sql_check_purchase)) {
            mysqli_stmt_bind_param($stmt_check_purchase, "ii", $product_id_review, $user_id);
            mysqli_stmt_execute($stmt_check_purchase);
            mysqli_stmt_bind_result($stmt_check_purchase, $count_purchases);
            mysqli_stmt_fetch($stmt_check_purchase);
            mysqli_stmt_close($stmt_check_purchase);
            if ($count_purchases > 0) {
                $has_bought_product = true;
            }
        } else {
            error_log("Error preparing check purchase statement: " . mysqli_error($conn));
        }

        // Cek apakah user sudah memberikan review untuk produk ini
        $has_reviewed = false;
        $sql_check_review = "SELECT COUNT(id) FROM product_reviews WHERE product_id = ? AND user_id = ?";
        if ($stmt_check_review = mysqli_prepare($conn, $sql_check_review)) {
            mysqli_stmt_bind_param($stmt_check_review, "ii", $product_id_review, $user_id);
            mysqli_stmt_execute($stmt_check_review);
            mysqli_stmt_bind_result($stmt_check_review, $count_reviews);
            mysqli_stmt_fetch($stmt_check_review);
            mysqli_stmt_close($stmt_check_review);
            if ($count_reviews > 0) {
                $has_reviewed = true;
            }
        } else {
            error_log("Error preparing check review statement: " . mysqli_error($conn));
        }

        if (!$has_bought_product) {
            $popup_message = "Anda hanya bisa memberikan ulasan untuk produk yang sudah Anda beli dan pesanan berstatus 'completed'.";
            $popup_status = "error";
        } elseif ($has_reviewed) {
            $popup_message = "Anda sudah memberikan ulasan untuk produk ini. Anda hanya bisa memberikan satu ulasan per produk.";
            $popup_status = "error";
        } else {
            $sql_insert_review = "INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)";
            if ($stmt_insert_review = mysqli_prepare($conn, $sql_insert_review)) {
                mysqli_stmt_bind_param($stmt_insert_review, "iiis", $product_id_review, $user_id, $rating, $comment);
                if (mysqli_stmt_execute($stmt_insert_review)) {
                    $popup_message = "Ulasan Anda berhasil ditambahkan!";
                    $popup_status = "success";
                } else {
                    $popup_message = "Gagal menambahkan ulasan: " . mysqli_stmt_error($stmt_insert_review);
                    $popup_status = "error";
                    error_log("Error inserting review: " . mysqli_stmt_error($stmt_insert_review));
                }
                mysqli_stmt_close($stmt_insert_review);
            } else {
                $popup_message = "Terjadi kesalahan sistem saat menyiapkan ulasan.";
                $popup_status = "error";
                error_log("Error preparing review insert statement: " . mysqli_error($conn));
            }
        }
    }
    // Redirect kembali ke halaman detail produk
    $product_name_for_redirect = strtolower(str_replace(' ', '_', $_POST['product_name_for_review'] ?? 'unknown_product'));
    header('Location: detail_flower.php?name=' . urlencode($product_name_for_redirect) . '&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}


$selected_flower = null;
if (isset($_GET['name'])) {
    $flower_param = strtolower(str_replace('_', ' ', trim($_GET['name'])));

    $stmt = mysqli_prepare($conn, "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                                 FROM products p
                                 LEFT JOIN users u ON p.seller_id = u.id
                                 WHERE LOWER(p.name) = ? AND (u.role = 'seller' OR p.seller_id IS NULL)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $flower_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $selected_flower = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    if (!$selected_flower) {
        foreach ($all_initial_products as $flower) {
            if (isset($flower['name']) && strtolower($flower['name']) === $flower_param) {
                $selected_flower = $flower;
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
    $selling_store_detail = $stores_by_seller_id[$selected_flower['seller_id']][0] ?? null;
}
$store_link_detail = "#";
$store_name_display_detail = "Toko Tidak Dikenal";

if ($selling_store_detail) {
    $store_link_detail = "store_profile_buyer.php?store_id_string=" . urlencode($selling_store_detail['store_id_string']);
    $store_name_display_detail = htmlspecialchars($selling_store_detail['name']);
}


// Generate recommendations (6 random unique flowers, excluding the selected one)
$recommended_flowers = [];
$all_products_db = [];
$sql_all = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
            FROM products p
            LEFT JOIN users u ON p.seller_id = u.id
            WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
            ORDER BY RAND() LIMIT 6";
$result_all = mysqli_query($conn, $sql_all);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_products_db[] = $row;
    }
}
if (empty($all_products_db) && isset($all_initial_products)) {
    $all_products_db = $all_initial_products;
}

if ($selected_flower) {
    $count = 0;
    foreach ($all_products_db as $f) {
        if (isset($f['name']) && $f['name'] !== $selected_flower['name'] && !empty($f['img']) && $count < 6) {
            $recommended_flowers[] = $f;
            $count++;
        }
    }
}

// Fetch reviews for the selected product
$product_reviews = [];
$average_rating = 0;
$total_reviews_count = 0;

if ($selected_flower && isset($selected_flower['id'])) {
    $sql_reviews = "SELECT pr.*, u.username FROM product_reviews pr JOIN users u ON pr.user_id = u.id WHERE pr.product_id = ? ORDER BY pr.created_at DESC";
    if ($stmt_reviews = mysqli_prepare($conn, $sql_reviews)) {
        mysqli_stmt_bind_param($stmt_reviews, "i", $selected_flower['id']);
        mysqli_stmt_execute($stmt_reviews);
        $result_reviews = mysqli_stmt_get_result($stmt_reviews);
        while ($row_review = mysqli_fetch_assoc($result_reviews)) {
            $product_reviews[] = $row_review;
        }
        mysqli_stmt_close($stmt_reviews);

        // Calculate average rating
        if (!empty($product_reviews)) {
            $total_rating = 0;
            foreach ($product_reviews as $review) {
                $total_rating += $review['rating'];
            }
            $total_reviews_count = count($product_reviews);
            $average_rating = round($total_rating / $total_reviews_count, 1);
        }
    } else {
        error_log("Error preparing review fetch statement: " . mysqli_error($conn));
    }
}

// Check if current user has bought this product and if order is completed
$user_has_bought_product_completed = false;
if ($selected_flower && isset($selected_flower['id'])) {
    $sql_check_user_purchase = "SELECT COUNT(oi.id) FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                WHERE oi.product_id = ? AND o.user_id = ? AND o.order_status = 'completed'";
    if ($stmt_check_user_purchase = mysqli_prepare($conn, $sql_check_user_purchase)) {
        mysqli_stmt_bind_param($stmt_check_user_purchase, "ii", $selected_flower['id'], $user_id);
        mysqli_stmt_execute($stmt_check_user_purchase);
        mysqli_stmt_bind_result($stmt_check_user_purchase, $count_user_purchases);
        mysqli_stmt_fetch($stmt_check_user_purchase);
        mysqli_stmt_close($stmt_check_user_purchase);
        if ($count_user_purchases > 0) {
            $user_has_bought_product_completed = true;
        }
    }
}

// Check if current user has already reviewed this product
$user_has_reviewed_product = false;
if ($selected_flower && isset($selected_flower['id'])) {
    $sql_check_user_review = "SELECT COUNT(id) FROM product_reviews WHERE product_id = ? AND user_id = ?";
    if ($stmt_check_user_review = mysqli_prepare($conn, $sql_check_user_review)) {
        mysqli_stmt_bind_param($stmt_check_user_review, "ii", $selected_flower['id'], $user_id);
        mysqli_stmt_execute($stmt_check_user_review);
        mysqli_stmt_bind_result($stmt_check_user_review, $count_user_reviews);
        mysqli_stmt_fetch($stmt_check_user_review);
        mysqli_stmt_close($stmt_check_user_review);
        if ($count_user_reviews > 0) {
            $user_has_reviewed_product = true;
        }
    }
}


// Check for popup messages from redirect
if (isset($_GET['popup_message']) && isset($_GET['popup_status'])) {
    $popup_message = urldecode($_GET['popup_message']);
    $popup_status = urldecode($_GET['popup_status']);
}

mysqli_close($conn);
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
        /* Gaya Pop-up Notifikasi (dari dashboard.php) */
        /* Pastikan gaya ini sudah ada di main_styles.css atau dashboard_styles.css jika ingin global */
        /* Jika tidak, biarkan di sini */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .popup-box {
            background: #FFFFFF;
            padding: 40px;
            border-radius: 0;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            text-align: center;
            max-width: 450px;
            width: 90%;
            transform: translateY(-30px) scale(0.95);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 2px solid #D60050;
        }
        .popup-overlay.active .popup-box {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        .popup-box .icon {
            font-size: 4.5rem;
            margin-bottom: 25px;
            display: block;
            line-height: 1;
        }
        .popup-box .icon.success {
            color: #28A745;
            text-shadow: none;
        }
        .popup-box .icon.error {
            color: #DC3545;
            text-shadow: none;
        }
        .popup-box h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            color: #000000;
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .popup-box p {
            font-size: 1.15rem;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .popup-box .close-btn {
            background-color: #000000;
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .popup-box .close-btn:hover {
            background-color: #333333;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
        /* End Pop-up Styles */


        /* Review Section Styles */
        .review-section {
            background: #FFFFFF;
            padding: 40px;
            margin-top: 60px;
            border-radius: 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border: 1px solid #888888;
            width: calc(100% - 40px); /* Adjusted to match main content padding */
            max-width: 1200px; /* Constrain width */
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }

        .review-section h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            color: #000000;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            font-weight: 700;
        }
        .review-section h2::after {
            content: '';
            display: block;
            width: 100px;
            height: 4px;
            background-color: #D60050;
            margin: 20px auto 0;
            border-radius: 0;
        }

        .review-summary {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 0;
        }
        .review-summary .stars {
            font-size: 2.5rem;
            color: #FFD700; /* Gold color for stars */
            margin-bottom: 10px;
        }
        .review-summary p {
            font-size: 1.1rem;
            color: #555;
            margin: 0;
        }
        .review-summary p strong {
            color: #000;
        }

        /* Review Form */
        .review-form-container {
            background-color: #f9f9f9;
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 0;
            margin-bottom: 40px;
            text-align: left;
        }
        .review-form-container h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            color: #000;
            margin-bottom: 25px;
            text-align: center;
            position: relative;
        }
        .review-form-container h3::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background-color: #D60050;
            margin: 15px auto 0;
            border-radius: 0;
        }
        .review-form-container .form-group {
            margin-bottom: 20px;
        }
        .review-form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .review-form-container select,
        .review-form-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #999;
            border-radius: 0;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .review-form-container select:focus,
        .review-form-container textarea:focus {
            border-color: #D60050;
            outline: none;
            box-shadow: 0 0 0 3px rgba(214,0,80,0.2);
        }
        .review-form-container textarea {
            min-height: 100px;
            resize: vertical;
        }
        .review-form-container .submit-btn {
            background-color: #000;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 0;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: auto; /* auto width for submit btn */
        }
        .review-form-container .submit-btn:hover {
            background-color: #333;
        }
        .review-form-container .info-message {
            background-color: #e0e0e0;
            border: 1px solid #aaa;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0;
            color: #555;
            text-align: center;
        }
        .review-form-container .info-message i {
            color: #D60050;
            margin-right: 8px;
        }


        /* Individual Reviews List */
        .review-list {
            list-style: none;
            padding: 0;
        }
        .review-list li {
            background-color: #fcfcfc;
            border: 1px solid #ccc;
            border-radius: 0;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .review-list li .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 10px;
        }
        .review-list li .review-header strong {
            font-size: 1.1rem;
            color: #000;
        }
        .review-list li .review-header .stars {
            color: #FFD700;
            font-size: 1.2rem;
        }
        .review-list li .review-comment {
            font-size: 0.95rem;
            color: #444;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .review-list li .review-date {
            font-size: 0.85rem;
            color: #777;
            text-align: right;
            display: block;
        }
        .review-list .no-reviews {
            text-align: center;
            color: #777;
            font-size: 1.1em;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px dashed #bbb;
            border-radius: 0;
        }

        /* Responsive for pop-up */
        @media (max-width: 480px) {
            .popup-box {
                padding: 25px;
            }
            .popup-box .icon {
                font-size: 4rem;
                margin-bottom: 20px;
            }
            .popup-box h3 {
                font-size: 1.7rem;
                margin-bottom: 10px;
            }
            .popup-box p {
                font-size: 1.05rem;
                margin-bottom: 20px;
            }
            .popup-box .close-btn {
                padding: 12px 30px;
                font-size: 1rem;
            }
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

    <h1 class="page-main-title">Detail Bunga</h1>

    <div class="main-content-area">
        <?php if ($selected_flower): ?>
            <div class="flower-details-column">
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
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($selected_flower['description'] ?? 'N/A')); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-globe-americas"></i> <span class="detail-label">Habitat:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($selected_flower['habitat'] ?? 'N/A')); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-leaf"></i> <span class="detail-label">Perawatan:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($selected_flower['care_instructions'] ?? $selected_flower['care'] ?? 'N/A')); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-lightbulb"></i> <span class="detail-label">Fakta unik:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($selected_flower['unique_fact'] ?? $selected_flower['fact'] ?? 'N/A')); ?></span>
                </div>
            </div>

            <div class="purchase-column">
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
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($selected_flower['id']); ?>">
                    <button type="submit" class="buy-button-detail"
                            <?php echo ($selected_flower['stock'] <= 0 ? 'disabled' : ''); ?>>
                        <i class="fas fa-cart-plus"></i> <?php echo ($selected_flower['stock'] <= 0 ? 'Stok Habis' : 'Tambah ke Keranjang'); ?>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flower-details-column" style="text-align: center; width: 100%;">
                <h3><i class="fas fa-exclamation-triangle"></i> Bunga Tidak Ditemukan</h3>
                <p>Informasi bunga yang Anda cari tidak tersedia. Pastikan nama bunga yang Anda masukkan benar.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selected_flower): // Only show review section if product is found ?>
        <div class="review-section">
            <h2><i class="fas fa-star"></i> Ulasan Produk</h2>

            <?php if ($total_reviews_count > 0): ?>
                <div class="review-summary">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php echo ($i <= $average_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                        <?php endfor; ?>
                    </div>
                    <p>Rating Rata-rata: <strong><?php echo htmlspecialchars($average_rating); ?> dari 5</strong> (dari <?php echo htmlspecialchars($total_reviews_count); ?> ulasan)</p>
                </div>
            <?php else: ?>
                <div class="no-reviews">
                    <p>Belum ada ulasan untuk produk ini.</p>
                </div>
            <?php endif; ?>

            <div class="review-form-container">
                <h3>Tulis Ulasan Anda</h3>
                <?php if ($user_has_bought_product_completed && !$user_has_reviewed_product): ?>
                    <form action="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $selected_flower['name']))); ?>" method="post">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="product_id_review" value="<?php echo htmlspecialchars($selected_flower['id']); ?>">
                        <input type="hidden" name="product_name_for_review" value="<?php echo htmlspecialchars($selected_flower['name']); ?>">
                        
                        <div class="form-group">
                            <label for="rating">Rating:</label>
                            <select id="rating" name="rating" required>
                                <option value="">-- Pilih Rating --</option>
                                <option value="5">5 Bintang - Sangat Baik</option>
                                <option value="4">4 Bintang - Baik</option>
                                <option value="3">3 Bintang - Cukup</option>
                                <option value="2">2 Bintang - Buruk</option>
                                <option value="1">1 Bintang - Sangat Buruk</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="comment">Komentar (Opsional):</label>
                            <textarea id="comment" name="comment" rows="5" placeholder="Bagikan pengalaman Anda tentang produk ini..."></textarea>
                        </div>
                        <button type="submit" class="submit-btn">Kirim Ulasan</button>
                    </form>
                <?php else: ?>
                    <div class="info-message">
                        <?php if (!$user_has_bought_product_completed): ?>
                            <i class="fas fa-info-circle"></i> Untuk memberikan ulasan, Anda harus membeli produk ini terlebih dahulu dan pesanan harus berstatus 'completed'.
                        <?php elseif ($user_has_reviewed_product): ?>
                            <i class="fas fa-check-circle"></i> Anda sudah memberikan ulasan untuk produk ini.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <ul class="review-list">
                <?php if (empty($product_reviews)): ?>
                    <li class="no-reviews">Belum ada ulasan yang ditampilkan. Jadilah yang pertama!</li>
                <?php else: ?>
                    <?php foreach ($product_reviews as $review): ?>
                        <li>
                            <div class="review-header">
                                <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo ($i <= $review['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'] ?? 'Tidak ada komentar.')); ?></p>
                            <span class="review-date"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($review['created_at']))); ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($selected_flower && !empty($recommended_flowers)): ?>
        <div class="recommended-flowers-section-container">
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

    <div id="statusPopupOverlay" class="popup-overlay">
        <div class="popup-box">
            <span id="popupIcon" class="icon"></span>
            <h3 id="popupTitle"></h3>
            <p id="popupMessage"></p>
            <button id="popupCloseBtn" class="close-btn">Tutup</button>
        </div>
    </div>

    <script>
        function showPopup(message, status) {
            const overlay = document.getElementById('statusPopupOverlay');
            const popupBox = overlay.querySelector('.popup-box');
            const popupIcon = document.getElementById('popupIcon');
            const popupTitle = document.getElementById('popupTitle');
            const popupMessage = document.getElementById('popupMessage');
            const popupCloseBtn = document.getElementById('popupCloseBtn');

            popupMessage.textContent = message;
            popupIcon.className = 'icon';
            if (status === 'success') {
                popupIcon.classList.add('fas', 'fa-check-circle', 'success');
                popupTitle.textContent = 'Berhasil!';
            } else if (status === 'error') {
                popupIcon.classList.add('fas', 'fa-times-circle', 'error');
                popupTitle.textContent = 'Gagal!';
            } else {
                popupIcon.classList.add('fas', 'fa-info-circle');
                popupTitle.textContent = 'Informasi';
            }

            overlay.classList.add('active');

            popupCloseBtn.onclick = function() {
                overlay.classList.remove('active');
                const url = new URL(window.location.href);
                url.searchParams.delete('popup_message');
                url.searchParams.delete('popup_status');
                window.history.replaceState({}, document.title, url);
            };
        }

        <?php if (!empty($popup_message)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showPopup("<?php echo $popup_message; ?>", "<?php echo $popup_status; ?>");
            });
        <?php endif; ?>
    </script>
</body>
</html>