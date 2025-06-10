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
$user_id = $_SESSION['id']; // Ambil user_id untuk fungsionalitas seperti 'Ikuti'

require_once 'config.php'; // Database connection
include 'data.php'; // For initial product fallback data (if needed)

$store_details = null;
$all_store_products = []; // Menyimpan semua produk toko untuk filtering
$filtered_store_products = []; // Produk yang akan ditampilkan setelah filtering
$store_id_string_param = $_GET['store_id_string'] ?? '';

// Check for popup messages from redirect (from dashboard.php or detail_flower.php)
$popup_message = "";
$popup_status = "";
if (isset($_GET['popup_message']) && isset($_GET['popup_status'])) {
    $popup_message = urldecode($_GET['popup_message']);
    $popup_status = urldecode($_GET['popup_status']);
}

// Variables for store's average rating and total reviews
$store_average_rating = 0;
$store_total_reviews_count = 0;
$store_product_count = 0; // New: To count products in store
$store_join_date = 'N/A'; // New: Store join date (from seller user created_at)

// NEW: Check if current user is following this store
$is_following = false;
$store_followers_count = 0; // Initialize followers count from DB
$store_db_id = 0; // Store's internal database ID

if (!empty($store_id_string_param)) {
    // Fetch store details
    $stmt_store = mysqli_prepare($conn, "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, u.username as seller_username, u.created_at as seller_join_date, s.followers_count
                                        FROM stores s
                                        JOIN users u ON s.seller_user_id = u.id
                                        WHERE s.store_id_string = ? AND u.role = 'seller'");
    if ($stmt_store) {
        mysqli_stmt_bind_param($stmt_store, "s", $store_id_string_param);
        mysqli_stmt_execute($stmt_store);
        $result_store = mysqli_stmt_get_result($stmt_store);
        $store_details = mysqli_fetch_assoc($result_store);
        mysqli_stmt_close($stmt_store);
    }

    if ($store_details) {
        $store_db_id = $store_details['id']; // Get store's actual DB ID
        $store_join_date = date('Y', strtotime($store_details['seller_join_date'])); // Get year only
        $store_followers_count = $store_details['followers_count']; // Get followers count from DB

        // Check if the current user is following this store
        // Only if the store is not their own store
        if ($store_details['seller_user_id'] != $user_id) {
            $stmt_is_following = mysqli_prepare($conn, "SELECT id FROM store_followers WHERE store_id = ? AND user_id = ?");
            if ($stmt_is_following) {
                mysqli_stmt_bind_param($stmt_is_following, "ii", $store_db_id, $user_id);
                mysqli_stmt_execute($stmt_is_following);
                mysqli_stmt_store_result($stmt_is_following);
                if (mysqli_stmt_num_rows($stmt_is_following) > 0) {
                    $is_following = true;
                }
                mysqli_stmt_close($stmt_is_following);
            }
        }

        // Fetch ALL products sold by this store's seller for filtering
        $stmt_products = mysqli_prepare($conn, "SELECT id, name, img, price, stock, family FROM products WHERE seller_id = ? AND stock > 0 ORDER BY name ASC");
        if ($stmt_products) {
            mysqli_stmt_bind_param($stmt_products, "i", $store_details['seller_user_id']);
            mysqli_stmt_execute($stmt_products);
            $result_products = mysqli_stmt_get_result($stmt_products);
            while ($row_product = mysqli_fetch_assoc($result_products)) {
                $all_store_products[] = $row_product;
            }
            $store_product_count = count($all_store_products); // Count actual products
            mysqli_stmt_close($stmt_products);
        }

        // Calculate overall average rating and total reviews for this store (from all its products)
        $sql_store_reviews_stats = "SELECT COUNT(pr.id) AS total_reviews, AVG(pr.rating) AS avg_rating
                                    FROM product_reviews pr
                                    JOIN products p ON pr.product_id = p.id
                                    WHERE p.seller_id = ?";
        if ($stmt_store_reviews_stats = mysqli_prepare($conn, $sql_store_reviews_stats)) {
            mysqli_stmt_bind_param($stmt_store_reviews_stats, "i", $store_details['seller_user_id']);
            mysqli_stmt_execute($stmt_store_reviews_stats);
            mysqli_stmt_bind_result($stmt_store_reviews_stats, $total_reviews, $avg_rating);
            mysqli_stmt_fetch($stmt_store_reviews_stats);
            $store_total_reviews_count = $total_reviews ?? 0;
            $store_average_rating = round($avg_rating ?? 0, 1);
            mysqli_stmt_close($stmt_store_reviews_stats);
        } else {
            error_log("Error preparing store reviews stats statement: " . mysqli_error($conn));
        }
    }
}

// Default filter to all products
$filtered_store_products = $all_store_products;

mysqli_close($conn); // Close connection after all data fetching
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($store_details['name'] ?? 'Profil Toko'); ?> - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/admin_seller_styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Layout & Background (Override from main_styles for this specific page) */
        body {
            background-color: #f0f0f0; /* Solid light gray background */
            font-family: 'Poppins', sans-serif;
            color: #222;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden; /* Prevent horizontal scroll */
            padding: 0; /* NEW: Remove padding from body */
            margin: 0; /* NEW: Remove margin from body */
        }

        /* NEW: Store Header Area (top of the page, truly full width) */
        .store-header-area {
            background-color: #FFFFFF;
            width: 100%; /* FULL WIDTH */
            padding: 20px 0; /* Vertical padding, horizontal handled by inner content */
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-bottom: 1px solid #ccc;
            box-sizing: border-box;
            display: flex;
            justify-content: center; /* Center the store-header-content */
        }

        .store-header-content {
            display: flex;
            align-items: flex-start; /* Align items to top, especially for logo+info */
            width: 100%; /* Take full width of parent (store-header-area) */
            max-width: 1200px; /* Limit content width for readability */
            padding: 0 30px; /* NEW: Horizontal padding for content inside this wrapper */
            gap: 30px;
            flex-wrap: wrap;
            box-sizing: border-box;
        }

        .store-logo-wrapper {
            width: 100px;
            height: 100px;
            background-color: #F8F8F8;
            border: 2px solid #D60050;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #D60050;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .store-logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .store-logo-initials {
            text-transform: uppercase;
        }

        .store-info-main {
            flex-grow: 1;
            text-align: left;
            padding-right: 20px;
        }
        .store-name-display {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: #000000;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .store-name-display i {
            color: #D60050;
            font-size: 1em;
        }
        .store-slogan {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 15px;
        }
        .store-badges {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .store-badge {
            background-color: #eee;
            padding: 5px 10px;
            font-size: 0.9em;
            color: #333;
            border: 1px solid #ccc;
            font-weight: 600;
        }
        .store-badge.active {
            background-color: #28A745;
            color: white;
            border-color: #28A745;
        }

        .store-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
            min-width: 300px;
        }
        .store-stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        .store-stat-item i {
            font-size: 1.4rem;
            color: #D60050;
            flex-shrink: 0;
        }
        .store-stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #000;
        }

        .store-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
        }
        .store-actions .btn {
            padding: 10px 20px;
            border: 1px solid #D60050;
            background-color: #D60050;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .store-actions .btn.outlined {
            background-color: transparent;
            color: #D60050;
        }
        .store-actions .btn.outlined:hover { /* NEW: Hover for outlined */
            background-color: #F8F8F8;
            color: #D60050;
        }
        .store-actions .btn.followed { /* NEW: Style for followed state */
            background-color: #A8A8A8; /* Gray when followed */
            border-color: #A8A8A8;
            color: white;
            cursor: default; /* Not clickable for a moment */
        }
        .store-actions .btn.followed:hover { /* NEW: Hover for followed */
            background-color: #A8A8A8;
            border-color: #A8A8A8;
            transform: none;
            box-shadow: none;
            color: white;
        }
        .store-actions .btn:hover {
            background-color: #A60040;
            border-color: #A60040;
            color: white;
        }
        .store-actions .btn i {
            font-size: 1.1em;
        }

        /* NEW: Store Navigation Bar (below header) */
        .store-nav-bar {
            background-color: #FFFFFF;
            width: 100%; /* FULL WIDTH */
            padding: 10px 0; /* Vertical padding, horizontal handled by inner content */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #ccc;
            box-sizing: border-box;
            display: flex;
            justify-content: center; /* Center the store-nav-content */
        }
        .store-nav-content {
            display: flex;
            max-width: 1200px; /* Limit nav items width for readability */
            width: 100%; /* Take full width of parent */
            padding: 0 30px; /* NEW: Horizontal padding for nav items */
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            box-sizing: border-box;
        }
        .store-nav-content::-webkit-scrollbar {
            display: none;
        }
        .store-nav-bar a {
            flex-shrink: 0;
            padding: 10px 20px;
            text-decoration: none;
            color: #555;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        .store-nav-bar a:hover,
        .store-nav-bar a.active {
            color: #D60050;
            border-bottom-color: #D60050;
            background-color: #F8F8F8;
        }

        /* Main Content Area - for product display (THIS IS THE KEY FOR PRODUCTS SECTION) */
        .main-content-area {
            flex-grow: 1;
            width: 100%; /* FULL WIDTH */
            margin: 40px auto; /* Top/bottom margin, horizontal auto to center if parent has max-width */
            padding: 0; /* NO PADDING ON THIS CONTAINER. Padding goes on inner elements */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center; /* Center internal content that might have max-width */
        }
        
        /* Inner Container for Products - now truly full-width with internal padding */
        .products-section-container {
            width: 100%; /* Take full width of main-content-area */
            /* REMOVED: max-width here, the padding below handles the margin from edges */
            padding: 40px; /* Padding for content inside this container */
            background-color: #FFFFFF;
            border: 1px solid #888888;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            box-sizing: border-box;
            margin-bottom: 40px; /* Space before back button */
        }

        /* Products Section Title */
        .products-section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            color: #000000;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .products-section-title i {
            color: #D60050;
            font-size: 1.1em;
        }
        .products-section-title::after {
            content: '';
            display: block;
            width: 100px;
            height: 4px;
            background-color: #D60050;
            margin: 20px auto 0;
            border-radius: 0;
        }

        /* Products Grid */
        .products-from-store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            width: 100%;
            padding: 0;
            box-sizing: border-box;
        }

        /* Product Card Styling */
        .products-from-store-grid .card {
            background-color: #FFFFFF;
            border: 1px solid #888888;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .products-from-store-grid .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        .products-from-store-grid .card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-bottom: 1px solid #ccc;
        }
        .products-from-store-grid .card-content {
            padding: 12px;
            text-align: left;
            flex-grow: 1;
        }
        .products-from-store-grid .card-content h4 {
            color: #000000;
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1rem;
            line-height: 1.3;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .products-from-store-grid .price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #D60050;
            margin-bottom: 10px;
            display: block;
        }
        .products-from-store-grid .buy-button {
            display: block;
            width: calc(100% - 24px); /* Account for 12px padding each side */
            padding: 8px 12px;
            margin: 0 12px 12px 12px;
            background-color: #000000;
            color: white;
            border: none;
            border-radius: 0;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .products-from-store-grid .buy-button:hover {
            background-color: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        .products-from-store-grid .buy-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }
        .products-from-store-grid .buy-button:disabled:hover {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .no-products-message {
            padding: 30px;
            background-color: #fcfcfc;
            border-radius: 0;
            color: #777;
            font-size: 1.2rem;
            border: 1px solid #aaa;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 700px;
            margin: 40px auto;
        }
        .no-products-message h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            color: #D60050;
            margin-bottom: 15px;
        }
        .no-products-message i {
            font-size: 3.5em;
            color: #999;
            margin-bottom: 20px;
        }

        .back-btn-container {
            margin-top: 50px;
            text-align: center;
            width: 100%;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 35px;
            background-color: #000000;
            color: white;
            border-radius: 0;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.3s ease;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
        .back-btn:hover {
            background-color: #333333;
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.6);
        }

        /* Gaya Pop-up Notifikasi (dari dashboard.php) */
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

        /* Responsive specific for Shopee-like layout */
        @media (min-width: 1201px) { /* NEW: For very wide screens, make content slightly narrower for readability */
            .store-header-content, .store-nav-content, .products-section-container {
                padding: 0 60px; /* More padding on very wide screens */
            }
            .products-from-store-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Allow slightly larger cards */
                gap: 25px;
            }
            .products-from-store-grid .card img { height: 180px; }
        }

        @media (max-width: 992px) {
            .store-header-content { flex-direction: column; text-align: center; align-items: center; gap: 20px; padding: 0 20px; }
            .store-logo-wrapper { margin-bottom: 10px; }
            .store-info-main { text-align: center; padding-right: 0; }
            .store-name-display { justify-content: center; }
            .store-slogan { margin-bottom: 10px; }
            .store-badges { justify-content: center; }
            .store-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .store-stat-item { justify-content: center; font-size: 0.95rem; }
            .store-stat-item i { font-size: 1.2rem; }
            .store-stat-value { font-size: 1rem; }
            .store-actions { flex-direction: column; gap: 10px; }
            .store-actions .btn { width: 100%; }
            .store-nav-bar { padding: 8px 0; }
            .store-nav-content { padding: 0 15px; justify-content: flex-start; }
            .store-nav-bar a { padding: 8px 15px; font-size: 0.9em; }

            /* Products Section Container */
            .products-section-container { padding: 30px; margin-bottom: 30px; } /* Tetap full width dari parent */
            .products-section-title { font-size: 2rem; margin-bottom: 25px; }
            .products-from-store-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
            .products-from-store-grid .card img { height: 140px; }
            .products-from-store-grid .card-content { padding: 10px; }
            .products-from-store-grid .card-content h4 { font-size: 0.95rem; }
            .products-from-store-grid .price { font-size: 1rem; }
            .products-from-store-grid .buy-button { padding: 6px 10px; font-size: 0.85rem; margin: 0 10px 10px 10px; }
        }

        @media (max-width: 768px) {
            .store-header-area { padding: 10px 0; }
            .store-header-content { gap: 15px; padding: 0 15px; }
            .store-logo-wrapper { width: 80px; height: 80px; font-size: 2rem; }
            .store-name-display { font-size: 2rem; }
            .store-slogan { font-size: 1em; margin-bottom: 10px; }
            .store-badge { font-size: 0.8em; padding: 4px 8px; }
            .store-stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .store-stat-item { justify-content: flex-start; }
            .store-actions .btn { padding: 8px 15px; font-size: 0.9rem; }
            .store-nav-bar { padding: 5px 0; }
            .store-nav-content { padding: 0 10px; }
            .store-nav-bar a { padding: 6px 12px; font-size: 0.85em; }

            .main-content-area { margin: 20px auto; padding: 0; }
            .products-section-container { padding: 25px; margin-bottom: 25px; }
            .products-section-title { font-size: 1.8rem; margin-bottom: 20px; }
            .products-from-store-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
            .products-from-store-grid .card img { height: 120px; }
            .products-from-store-grid .card-content { padding: 8px; }
            .products-from-store-grid .card-content h4 { font-size: 0.85rem; }
            .products-from-store-grid .price { font-size: 0.9rem; }
            .products-from-store-grid .buy-button { padding: 5px 8px; font-size: 0.75rem; margin: 0 8px 8px 8px; }
        }

        @media (max-width: 480px) {
            .store-header-area { padding: 10px 0; }
            .store-header-content { gap: 10px; padding: 0 10px; }
            .store-logo-wrapper { width: 60px; height: 60px; font-size: 1.8rem; }
            .store-name-display { font-size: 1.8rem; }
            .store-slogan { font-size: 0.8em; }
            .store-badge { font-size: 0.7em; padding: 2px 5px; }
            .store-stats-grid { grid-template-columns: 1fr; }
            .store-actions .btn { padding: 6px 12px; font-size: 0.8rem; }
            .store-nav-bar { padding: 5px 0; }
            .store-nav-content { padding: 0 5px; }
            .store-nav-bar a { padding: 5px 10px; font-size: 0.8em; }

            .main-content-area { margin: 15px auto; padding: 0; }
            .products-section-container { padding: 15px; margin-bottom: 15px; }
            .products-section-title { font-size: 1.5rem; margin-bottom: 15px; }
            .products-from-store-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; }
            .products-from-store-grid .card img { height: 100px; }
            .products-from-store-grid .card-content h4 { font-size: 0.75rem; }
            .products-from-store-grid .price { font-size: 0.8rem; }
            .products-from-store-grid .buy-button { padding: 4px 6px; font-size: 0.65rem; margin: 0 6px 6px 6px; }
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

    <?php if ($store_details): ?>
    <div class="store-header-area">
        <div class="store-header-content"> <div class="store-logo-wrapper">
                <span class="store-logo-initials">
                    <?php echo htmlspecialchars(strtoupper(substr($store_details['name'], 0, 2))); ?>
                </span>
                </div>
            <div class="store-info-main">
                <h1 class="store-name-display"><i class="fas fa-store"></i> <?php echo htmlspecialchars($store_details['name']); ?></h1>
                <p class="store-slogan">Menyediakan tanaman hias berkualitas untuk keindahan rumah Anda!</p>
                <div class="store-badges">
                    <span class="store-badge active"><i class="fas fa-check-circle"></i> Aktif</span>
                    <span class="store-badge"><i class="fas fa-star"></i> Star Seller</span> </div>
            </div>
            <div class="store-stats-grid">
                <div class="store-stat-item">
                    <i class="fas fa-box-open"></i>
                    <div>
                        <div class="store-stat-value"><?php echo htmlspecialchars($store_product_count); ?></div>
                        <div>Produk</div>
                    </div>
                </div>
                <div class="store-stat-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <div class="store-stat-value"><?php echo htmlspecialchars($store_followers_count); ?></div> <div>Pengikut</div>
                    </div>
                </div>
                <div class="store-stat-item">
                    <i class="fas fa-star"></i>
                    <div>
                        <div class="store-stat-value"><?php echo htmlspecialchars($store_average_rating); ?></div>
                        <div>Penilaian (<?php echo htmlspecialchars($store_total_reviews_count); ?>)</div>
                    </div>
                </div>
                <div class="store-stat-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <div class="store-stat-value"><?php echo htmlspecialchars($store_join_date); ?></div>
                        <div>Bergabung</div>
                    </div>
                </div>
            </div>
            <div class="store-actions">
                <?php if ($store_details['seller_user_id'] != $user_id): // Jangan tampilkan tombol follow jika itu toko sendiri ?>
                <button class="btn outlined" id="followBtn" data-store-id="<?php echo htmlspecialchars($store_details['id']); ?>">
                    <i class="fas fa-user-plus"></i> <span id="followText"><?php echo $is_following ? 'Diikuti' : 'Ikuti'; ?></span>
                </button>
                <?php else: ?>
                <span class="btn followed" style="cursor: default;"><i class="fas fa-store"></i> Ini Toko Anda</span>
                <?php endif; ?>
                <a href="/PlantPals/dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($store_details['seller_user_id']); ?>&store_id=<?php echo htmlspecialchars($store_details['id']); ?>&subject=Pesan untuk Toko <?php echo urlencode($store_details['name']); ?>" class="btn">
                    <i class="fas fa-comment-dots"></i> Chat Sekarang
                </a>
            </div>
        </div>
    </div>

    <div class="store-nav-bar" style="margin-bottom: 40px;"> <div class="store-nav-content"> <a href="javascript:void(0)" class="active" data-filter="all">Semua Produk</a>
            <a href="javascript:void(0)" data-filter="Rosaceae">Bunga Potong</a> 
            <a href="javascript:void(0)" data-filter="Asparagaceae">Tanaman Indoor</a> 
            <a href="javascript:void(0)" data-filter="Aksesoris">Aksesoris</a> <a href="javascript:void(0)" data-filter="Terlaris">Terlaris</a> <a href="javascript:void(0)" data-filter="Terbaru">Terbaru</a> <a href="javascript:void(0)" data-filter="Diskon">Diskon</a> </div>
    </div>
    <?php endif; ?>

    <div class="main-content-area" style="padding: 0; margin-top: 0;"> 
        <?php if ($store_details): ?>
            <div class="products-section-container" style="max-width: none; border: none; box-shadow: none; background-color: transparent; padding: 0 30px;"> <h3 class="products-section-title" style="margin-top: 0; margin-bottom: 30px;"><i class="fas fa-seedling"></i> Produk dari Toko Ini</h3>
                <?php if (empty($all_store_products)): /* Check all_store_products not filtered */ ?>
                    <div class="no-products-message">
                        <i class="fas fa-box-open"></i>
                        <h2>Oops!</h2>
                        <p>Toko ini belum memiliki produk yang tersedia saat ini.</p>
                    </div>
                <?php else: ?>
                    <div class="products-from-store-grid">
                        <?php foreach ($all_store_products as $product): /* Loop all_store_products for JS filter */ ?>
                            <div class="card" data-category="<?php echo htmlspecialchars($product['family'] ?? 'Lainnya'); /* Use family as category example */ ?>">
                                <img src="<?php echo htmlspecialchars($product['img']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" />
                                <div class="card-content">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p class="price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                                </div>
                                <button class="buy-button"
                                        onclick="window.location.href='order_form.php?action=buy_now_single_product&product_id=<?php echo htmlspecialchars($product['id']); ?>&product_name=<?php echo urlencode(htmlspecialchars($product['name'])); ?>&product_price=<?php echo htmlspecialchars($product['price']); ?>&store_id_string=<?php echo htmlspecialchars($store_details['store_id_string']); ?>&store_name=<?php echo urlencode(htmlspecialchars($store_details['name'] . ' - (' . ($store_details['address'] ?? 'Alamat Tidak Diketahui') . ')')); ?>&quantity=1';"
                                        <?php echo ($product['stock'] <= 0 ? 'disabled' : ''); ?>>
                                    <i class="fas fa-shopping-cart"></i> <?php echo ($product['stock'] <= 0 ? 'Stok Habis' : 'Pesan Sekarang'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="noFilteredProducts" class="no-products-message" style="display: none; margin-top: 40px; background-color: #f0f0f0; border: 1px solid #ccc;">
                        <i class="fas fa-filter"></i>
                        <h2>Tidak Ada Produk Ditemukan!</h2>
                        <p>Tidak ada produk dalam kategori yang dipilih.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="no-products-message" style="background-color: #fff9f9; border: 1px solid #f9d9d9;">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Toko Tidak Ditemukan!</h2>
                <p>Informasi toko yang Anda cari tidak tersedia. Mohon periksa kembali tautan Anda.</p>
            </div>
        <?php endif; ?>

        <div class="back-btn-container">
            <a href="/PlantPals/dashboard.php?page=home" class="back-btn"><i class="fas fa-arrow-left"></i> Kembali ke Home</a>
        </div>
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

        // JavaScript for Follow Button (AJAX Integration)
        document.addEventListener('DOMContentLoaded', function() {
            const followBtn = document.getElementById('followBtn');
            const followText = document.getElementById('followText');
            const followersCountDisplay = document.querySelector('.store-stat-item .store-stat-value'); // Display element for followers

            if (followBtn) {
                // Initial state is set by PHP
                followBtn.addEventListener('click', function() {
                    const storeId = this.dataset.storeId; // Get store ID from data attribute
                    const isFollowingNow = followText.textContent === 'Diikuti'; // Check current state of button text
                    const action = isFollowingNow ? 'unfollow' : 'follow';

                    // Disable button to prevent multiple clicks
                    followBtn.disabled = true;
                    followBtn.style.cursor = 'wait';

                    // Send AJAX request
                    fetch('process_follow.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'store_id=' + storeId + '&action=' + action
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI based on server response
                            if (data.is_following) {
                                followBtn.classList.remove('outlined');
                                followBtn.classList.add('followed');
                                followBtn.innerHTML = '<i class="fas fa-check"></i> <span id="followText">Diikuti</span>';
                            } else {
                                followBtn.classList.remove('followed');
                                followBtn.classList.add('outlined');
                                followBtn.innerHTML = '<i class="fas fa-user-plus"></i> <span id="followText">Ikuti</span>';
                            }
                            // Update followers count
                            if (followersCountDisplay) {
                                followersCountDisplay.textContent = data.followers_count;
                            }
                            showPopup(data.message, 'success');
                        } else {
                            showPopup(data.message, 'error');
                            // If failed, revert button state (important for UX)
                            // Re-enable button after error
                            if (!isFollowingNow) { // If it was supposed to follow, but failed
                                followBtn.classList.add('outlined');
                                followBtn.classList.remove('followed');
                                followBtn.innerHTML = '<i class="fas fa-user-plus"></i> <span id="followText">Ikuti</span>';
                            } else { // If it was supposed to unfollow, but failed
                                followBtn.classList.remove('outlined');
                                followBtn.classList.add('followed');
                                followBtn.innerHTML = '<i class="fas fa-check"></i> <span id="followText">Diikuti</span>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showPopup('Terjadi kesalahan koneksi. Mohon coba lagi.', 'error');
                        // Revert button state on network error
                        if (!isFollowingNow) {
                            followBtn.classList.add('outlined');
                            followBtn.classList.remove('followed');
                            followBtn.innerHTML = '<i class="fas fa-user-plus"></i> <span id="followText">Ikuti</span>';
                        } else {
                            followBtn.classList.remove('outlined');
                            followBtn.classList.add('followed');
                            followBtn.innerHTML = '<i class="fas fa-check"></i> <span id="followText">Diikuti</span>';
                        }
                    })
                    .finally(() => {
                        followBtn.disabled = false;
                        followBtn.style.cursor = 'pointer';
                    });
                });
            }

            // JavaScript for Category Filtering
            const filterLinks = document.querySelectorAll('.store-nav-bar a[data-filter]');
            const productCards = document.querySelectorAll('.products-from-store-grid .card');
            const noFilteredProductsMessage = document.getElementById('noFilteredProducts');

            filterLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault(); // Prevent default link behavior
                    
                    // Remove active class from all links
                    filterLinks.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');

                    const filterCategory = this.dataset.filter; // Get category from data-filter attribute
                    let productsShownCount = 0;

                    productCards.forEach(card => {
                        const productCategory = card.dataset.category; // Get category from product card
                        
                        if (filterCategory === 'all' || productCategory === filterCategory) {
                            card.style.display = 'flex'; // Show card
                            productsShownCount++;
                        } else {
                            card.style.display = 'none'; // Hide card
                        }
                    });

                    // Show/hide "No products found" message
                    if (productsShownCount === 0) {
                        noFilteredProductsMessage.style.display = 'block';
                    } else {
                        noFilteredProductsMessage.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>