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

require_once 'config.php'; // Database connection
include 'data.php'; // For initial product fallback data (if needed)

$store_details = null;
$store_products = [];
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

if (!empty($store_id_string_param)) {
    // Fetch store details
    $stmt_store = mysqli_prepare($conn, "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, u.username as seller_username, u.created_at as seller_join_date
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
        $store_join_date = date('Y', strtotime($store_details['seller_join_date'])); // Get year only

        // Fetch products sold by this store's seller
        // Only show products with stock > 0
        $stmt_products = mysqli_prepare($conn, "SELECT id, name, img, price, stock FROM products WHERE seller_id = ? AND stock > 0 ORDER BY name ASC");
        if ($stmt_products) {
            mysqli_stmt_bind_param($stmt_products, "i", $store_details['seller_user_id']);
            mysqli_stmt_execute($stmt_products);
            $result_products = mysqli_stmt_get_result($stmt_products);
            while ($row_product = mysqli_fetch_assoc($result_products)) {
                $store_products[] = $row_product;
            }
            $store_product_count = count($store_products); // Count actual products
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

mysqli_close($conn); // Close connection after all data fetching
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $store_details ? htmlspecialchars($store_details['name']) : 'Profil Toko'; ?> - PlantPals</title>
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
        }

        /* NEW: Store Header Area (top of the page, full width) */
        .store-header-area {
            background-color: #FFFFFF;
            width: 100%;
            padding: 20px 0; /* Vertical padding, horizontal handled by inner container */
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-bottom: 1px solid #ccc;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .store-header-content {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 1200px; /* Constrain content width */
            padding: 0 30px; /* Horizontal padding for content inside header */
            gap: 30px;
            flex-wrap: wrap; /* Allow wrapping on small screens */
            box-sizing: border-box;
        }

        .store-logo-wrapper {
            width: 100px; /* Fixed size for logo */
            height: 100px;
            background-color: #F8F8F8;
            border: 2px solid #D60050; /* Accent border */
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
            background-color: #28A745; /* Solid Green */
            color: white;
            border-color: #28A745;
        }

        .store-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); /* Tighter grid for stats */
            gap: 15px;
            margin-top: 20px;
            width: 100%; /* Ensure it takes full width of its parent flex item */
        }
        .store-stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem; /* Slightly smaller font */
            color: #333;
            font-weight: 500;
        }
        .store-stat-item i {
            font-size: 1.4rem; /* Adjusted icon size */
            color: #D60050;
            flex-shrink: 0;
        }
        .store-stat-value {
            font-size: 1.1rem; /* Adjusted value font size */
            font-weight: 700;
            color: #000;
        }

        .store-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            align-items: center;
            justify-content: flex-start; /* Align buttons to the start */
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
        .store-actions .btn:hover {
            background-color: #A60040;
            border-color: #A60040;
            color: white;
        }
        .store-actions .btn.outlined:hover {
            background-color: #F0F0F0;
            color: #D60050;
        }
        .store-actions .btn i {
            font-size: 1.1em;
        }

        /* NEW: Store Navigation Bar (below header) */
        .store-nav-bar {
            background-color: #FFFFFF;
            width: 100%;
            padding: 10px 0; /* Vertical padding, horizontal handled by inner container */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #ccc;
            box-sizing: border-box;
            display: flex;
            justify-content: center;
        }
        .store-nav-content {
            display: flex;
            max-width: 1200px;
            width: 100%;
            padding: 0 30px; /* Horizontal padding for nav items */
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

        /* Main Content Wrapper - for product display */
        .main-content-wrapper {
            flex-grow: 1;
            width: 100%;
            max-width: 1200px; /* Constrain content width */
            margin: 40px auto; /* Center horizontally, add top/bottom margin */
            padding: 0 20px; /* Horizontal padding */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        
        /* Card Panel - General style for sections */
        .card-panel-full-width {
            background: #FFFFFF;
            border-radius: 0; /* No border-radius */
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            border: 1px solid #888888;
            width: 100%;
            box-sizing: border-box;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-panel-full-width:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
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

        /* Products Grid (consistent with dashboard product grid) */
        .products-from-store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Smaller min-width for grid items */
            gap: 20px; /* Reduced gap */
            width: 100%;
            padding: 0;
            box-sizing: border-box;
        }

        /* Product Card Styling (inherited/consistent) */
        .products-from-store-grid .card {
            background-color: #FFFFFF;
            border: 1px solid #888888;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); /* Lighter shadow for smaller cards */
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
            height: 160px; /* Smaller image height */
            object-fit: cover;
            border-radius: 0;
            border-bottom: 1px solid #ccc;
        }
        .products-from-store-grid .card-content {
            padding: 12px; /* Smaller padding */
            text-align: left;
            flex-grow: 1;
        }
        .products-from-store-grid .card-content h4 {
            color: #000000;
            margin-top: 0;
            margin-bottom: 5px; /* Smaller margin */
            font-size: 1rem; /* Smaller font size */
            line-height: 1.3;
            font-weight: 600;
            white-space: nowrap; /* Prevent wrapping */
            overflow: hidden; /* Hide overflow text */
            text-overflow: ellipsis; /* Add ellipsis */
        }
        .products-from-store-grid .price {
            font-size: 1.1rem; /* Adjusted font size */
            font-weight: bold;
            color: #D60050;
            margin-bottom: 10px; /* Smaller margin */
            display: block;
        }
        .products-from-store-grid .buy-button {
            display: block;
            width: calc(100% - 24px); /* Account for 12px padding each side */
            padding: 8px 12px; /* Smaller padding */
            margin: 0 12px 12px 12px; /* Smaller margin */
            background-color: #000000;
            color: white;
            border: none;
            border-radius: 0;
            font-size: 0.85rem; /* Smaller font size */
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px; /* Smaller gap */
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
        @media (max-width: 992px) {
            .store-header-area { padding: 15px 0; }
            .store-header-content { flex-direction: column; text-align: center; gap: 20px; padding: 0 20px; }
            .store-logo-wrapper { margin-bottom: 10px; }
            .store-info-main { text-align: center; }
            .store-name-display { justify-content: center; }
            .store-slogan { margin-bottom: 10px; }
            .store-badges { justify-content: center; }
            .store-stats-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; } /* Adjust stat grid */
            .store-stat-item { justify-content: center; font-size: 0.9rem; } /* Adjusted font size */
            .store-stat-item i { font-size: 1.2rem; } /* Adjusted icon size */
            .store-stat-value { font-size: 1rem; } /* Adjusted value font size */
            .store-actions { flex-direction: column; gap: 10px; }
            .store-actions .btn { width: 100%; }
            .store-nav-bar { padding: 8px 0; }
            .store-nav-content { padding: 0 15px; justify-content: flex-start; }
            .store-nav-bar a { padding: 8px 15px; font-size: 0.9em; }

            .main-content-wrapper { margin: 30px auto; padding: 0 10px; }
            .card-panel-full-width { padding: 30px; margin-bottom: 30px; }
            .products-section-title { font-size: 2rem; margin-bottom: 25px; }
            .products-from-store-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; } /* Smaller min-width for grid */
            .products-from-store-grid .card img { height: 140px; } /* Smaller image height */
            .products-from-store-grid .card-content { padding: 10px; }
            .products-from-store-grid .card-content h4 { font-size: 0.95rem; }
            .products-from-store-grid .price { font-size: 1rem; }
            .products-from-store-grid .buy-button { padding: 6px 10px; font-size: 0.8rem; margin: 0 10px 10px 10px; }
        }

        @media (max-width: 576px) {
            .store-header-area { padding: 10px 0; }
            .store-header-content { gap: 15px; padding: 0 10px; }
            .store-logo-wrapper { width: 70px; height: 70px; font-size: 2rem; }
            .store-name-display { font-size: 2rem; }
            .store-slogan { font-size: 0.9em; margin-bottom: 10px; }
            .store-badge { font-size: 0.75em; padding: 3px 6px; }
            .store-stats-grid { grid-template-columns: 1fr; gap: 8px; }
            .store-stat-item { font-size: 0.9rem; }
            .store-stat-item i { font-size: 1.1rem; }
            .store-stat-value { font-size: 1rem; }
            .store-actions .btn { padding: 6px 12px; font-size: 0.8rem; }
            .store-nav-bar { padding: 5px 0; }
            .store-nav-content { padding: 0 8px; }
            .store-nav-bar a { padding: 6px 10px; font-size: 0.8em; }

            .main-content-wrapper { margin: 20px auto; padding: 0 5px; }
            .card-panel-full-width { padding: 20px; margin-bottom: 20px; }
            .products-section-title { font-size: 1.8rem; margin-bottom: 20px; }
            .products-from-store-grid { grid-template-columns: 1fr; gap: 10px; } /* Single column on smallest screens */
            .products-from-store-grid .card img { height: 120px; } /* Even smaller image height */
            .products-from-store-grid .card-content { padding: 8px; }
            .products-from-store-grid .card-content h4 { font-size: 0.9rem; }
            .products-from-store-grid .price { font-size: 0.85rem; }
            .products-from-store-grid .buy-button { padding: 5px 8px; font-size: 0.75rem; margin: 0 8px 8px 8px; }
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
        <div class="store-header-content">
            <div class="store-logo-wrapper">
                <span class="store-logo-initials">
                    <?php echo htmlspecialchars(substr($store_details['name'], 0, 2)); ?>
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
                        <div class="store-stat-value">N/A</div> <div>Pengikut</div>
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
                <button class="btn outlined"><i class="fas fa-user-plus"></i> Ikuti</button>
                <button class="btn"><i class="fas fa-comment-dots"></i> Chat Sekarang</button>
            </div>
        </div>
    </div>
    <div class="store-nav-bar">
        <div class="store-nav-content">
            <a href="store_profile_buyer.php?store_id_string=<?php echo urlencode($store_details['store_id_string']); ?>" class="active">Semua Produk</a>
            <a href="#">Bunga Potong</a>
            <a href="#">Tanaman Indoor</a>
            <a href="#">Aksesoris</a>
            <a href="#">Terlaris</a>
            <a href="#">Terbaru</a>
            <a href="#">Diskon</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content-wrapper">
        <?php if ($store_details): ?>
            <div class="card-panel-full-width">
                <h3 class="products-section-title" style="margin-top: 0; margin-bottom: 30px;"><i class="fas fa-seedling"></i> Produk dari Toko Ini</h3>
                <?php if (empty($store_products)): ?>
                    <div class="no-products-message">
                        <i class="fas fa-box-open"></i>
                        <h2>Oops!</h2>
                        <p>Toko ini belum memiliki produk yang tersedia saat ini.</p>
                    </div>
                <?php else: ?>
                    <div class="products-from-store-grid">
                        <?php foreach ($store_products as $product): ?>
                            <div class="card">
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
    </script>
</body>
</html>