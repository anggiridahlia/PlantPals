<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
$role = $_SESSION['role'] ?? 'buyer'; // Get role from session

// Tentukan halaman yang akan dimuat berdasarkan parameter 'page' di URL
$page = isset($_GET['page']) ? $_GET['page'] : 'home'; // Default ke 'home' jika tidak ada parameter

// Include your data file
include 'data.php';

// Fetch products from database
require_once 'config.php';
$flowers = []; // This will hold products fetched from DB
$sql = "SELECT * FROM products ORDER BY name ASC"; // Fetch all products from DB
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $flowers[] = $row;
    }
}
// If database is empty, use initial data from data.php as a fallback
if (empty($flowers) && isset($all_initial_products)) {
    $flowers = $all_initial_products;
}
mysqli_close($conn); // Close connection after fetching products
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/admin_seller_styles.css">
    <style>
        /* Specific styles for dashboard.php beyond admin_seller_styles.css */
        /* These styles override or add to the general styles */

        /* --- Main Container & Sidebar Layout --- */
        /* This section overrides .main-content from admin_seller_styles.css */
        .dashboard-container-wrapper { /* New wrapper for dashboard layout */
            display: flex;
            flex: 1; /* Allows it to grow */
            width: 100%;
            max-width: 1300px; /* Max width for central content */
            margin: 40px auto; /* Centered with vertical margins */
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); /* Subtle shadow for the whole content area */
            border-radius: 18px;
            background-color: #fcfcfc; /* Slightly off-white content background */
            overflow: hidden; /* Ensures rounded corners */
        }

        nav.sidebar {
            width: 250px; /* Wider sidebar */
            background-color: rgb(228, 250, 228); /* Lighter green background */
            padding: 30px 20px;
            border-right: 1px solid rgb(217, 195, 206); /* Lighter border */
            display: flex;
            flex-direction: column;
            gap: 15px; /* Smaller gap for tighter menu */
            box-shadow: 2px 0 8px rgba(0,0,0,0.03); /* Subtle right shadow for sidebar */
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }

        nav.sidebar a {
            font-weight: 500;
            padding: 14px 18px; /* More padding */
            border-radius: 10px; /* Softer rounded corners */
            color: #2f5d3a;
            background-color: transparent;
            transition: background-color 0.25s ease, color 0.25s ease, transform 0.2s ease;
            display: flex; /* Make it a flex item for padding */
            align-items: center;
            font-size: 1rem;
        }

        nav.sidebar a.active,
        nav.sidebar a:hover {
            background-color: #E5989B;
            color: white;
            transform: translateX(5px); /* Slight slide on hover/active */
            text-decoration: none; /* Remove underline on hover */
        }

        main.dashboard-content { /* Renamed to avoid conflict with .main-content */
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            background: transparent; /* Background handled by wrapper */
        }

        main.dashboard-content h2 { /* Specific heading for dashboard content */
            font-size: 2.5rem;
            margin-bottom: 30px;
            font-weight: 700;
            color: #386641;
            text-align: center;
        }

        /* --- Search Bar --- */
        .search-bar {
            margin-bottom: 40px;
            display: flex;
            gap: 15px;
            align-items: center;
            max-width: 800px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-radius: 12px;
            overflow: hidden;
            background-color: white; /* Ensure search bar has white background */
            margin-left: auto; /* Center search bar */
            margin-right: auto;
        }

        .search-bar input {
            flex: 1;
            padding: 14px 20px;
            border: none;
            background-color: transparent; /* Remove default input background */
            font-size: 1.1rem;
            outline: none;
        }

        .search-bar button {
            padding: 14px 25px;
            background-color: #E5989B;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .search-bar button:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-2px);
        }

        /* --- Product Grid (Home Page) --- */
        .product-grid-home { /* Renamed from .grid to be specific */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card-home { /* Renamed from .card to be specific */
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .product-card-home:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .product-card-home img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 16px 16px 0 0;
        }

        .product-card-home .card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .product-card-home .card-content h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: #2f5d3a;
            font-weight: 700;
        }

        .product-card-home .card-content p {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .product-card-home .see-more-btn {
            background-color: #E5989B;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 0 20px 20px 20px; /* Adjusted margin to fit card */
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-align: center;
            display: block;
        }

        .product-card-home .see-more-btn:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-2px);
        }

        /* --- Product Listing (Product Page) --- */
        .product-listing-grid { /* Specific for product page */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-listing-card { /* Specific for product page */
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-align: center;
        }

        .product-listing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .product-listing-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 16px 16px 0 0;
        }

        .product-listing-card .card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-listing-card h4 {
            margin-top: 5px;
            margin-bottom: 10px;
            font-size: 1.4rem;
            color: #2f5d3a;
            font-weight: 700;
        }

        .product-listing-card .price {
            font-weight: bold;
            color: #e66a7b;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .product-listing-card .store-selection {
            margin-bottom: 15px;
            text-align: left;
        }

        .product-listing-card .store-selection label {
            display: block;
            font-size: 0.95em;
            color: #777;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .product-listing-card .store-selection select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fcfcfc;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%232f5d3a%22%20d%3D%22M287%2C197.3L159.2%2C69.5c-4.4-4.4-11.4-4.4-15.8%2C0L5.4%2C197.3c-4.4%2C4.4-4.4%2C11.4%2C0%2C15.8c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0l135.9-135.9l135.9%2C135.9c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0C291.4%2C208.7%2C291.4%2C201.7%2C287%2C197.3z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 12px top 50%;
            background-size: 10px auto;
            transition: border-color 0.3s ease, box-shadow 0.2s ease;
        }
        .product-listing-card .store-selection select:hover { border-color: #a8a8a8; }
        .product-listing-card .store-selection select:focus { border-color: #e66a7b; outline: none; box-shadow: 0 0 0 3px rgba(230, 106, 123, 0.2); }

        .product-listing-card .buy-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.05rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 6px rgba(76, 175, 80, 0.2);
            width: 100%; /* Ensure button takes full width */
        }

        .product-listing-card .buy-button:hover {
            background-color: #3b7d33;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
        }

        /* Page content for profile, orders, contact */
        .page-content-panel {
            padding: 30px;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 40px;
            width: 100%;
        }

        .page-content-panel h3 {
            color: #3a5a20;
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: 600;
        }

        .page-content-panel p {
            line-height: 1.7;
            margin-bottom: 15px;
            color: #555;
        }

        .order-list {
            list-style: none;
            padding: 0;
        }

        .order-list li {
            background-color: #f0f8f0;
            border: 1px solid #c3d9c3;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .order-list li span {
            flex: 1;
            min-width: 120px;
            margin: 5px 0;
            font-size: 1.05rem;
            color: #4a4a4a;
        }
        .order-list li span strong {
            color: #2f5d3a;
        }

        .profile-info p {
            font-size: 1.1rem;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .profile-info p strong {
            display: inline-block;
            width: 150px;
            vertical-align: top;
            color: #3a5a20;
            font-weight: 600;
        }
        .profile-info-btn { /* Specific class for this button */
            background-color: #E5989B;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
            box-shadow: 0 2px 6px rgba(229, 152, 155, 0.2);
        }
        .profile-info-btn:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(229, 152, 155, 0.3);
        }

        /* --- Footer (Common) --- */
        footer {
            text-align: center;
            padding: 20px 0;
            font-size: 0.95rem;
            color: #777;
            background-color: rgb(242, 230, 234);
            border-top: 1px solid rgb(217, 195, 208);
            width: 100%;
            margin-top: auto; /* Push footer to bottom */
        }

        /* --- Responsive Adjustments --- */
        /* Common adjustments for main content (for admin/seller dashboard) */
        @media (max-width: 1200px) {
            .main-content { padding: 30px; }
            h1 { font-size: 2.5rem; margin-bottom: 30px; }
            .section-header h2 { font-size: 2rem; }
            .add-new-btn { padding: 10px 20px; font-size: 0.95rem; }
            .card-panel { padding: 25px; margin-bottom: 30px; }
            .form-group input, .form-group textarea, .form-group select { padding: 12px; font-size: 0.95rem; }
            .form-action-btns button, .form-action-btns a.btn-link { padding: 10px 20px; font-size: 0.95rem; }
            .data-table th, .data-table td { padding: 15px 18px; font-size: 0.9rem; }
            .stats-grid { gap: 25px; }
            .stat-card h3 { font-size: 1.8rem; }
        }

        @media (max-width: 992px) { /* Tablet / Medium screens */
            header { flex-direction: column; align-items: flex-start; padding-bottom: 10px; min-height: unset; }
            header nav { width: 100%; flex-direction: row; overflow-x: auto; justify-content: flex-start; margin-top: 10px; padding-bottom: 5px; }
            header nav a { flex-shrink: 0; white-space: nowrap; padding: 8px 12px; font-size: 0.85rem; }
            .logout-btn { align-self: flex-end; margin-top: -45px; margin-right: 20px; }

            /* Dashboard Buyer Specific Layout - Stacking */
            .dashboard-container-wrapper { flex-direction: column; border-radius: 0; margin: 0; box-shadow: none; } /* No rounded corners, no shadow when stacked */
            nav.sidebar {
                width: 100%;
                flex-direction: row;
                overflow-x: auto;
                border-right: none;
                border-bottom: 1px solid rgb(217, 195, 206);
                padding: 15px 10px;
                justify-content: center;
                gap: 10px;
                box-shadow: none;
            }
            nav.sidebar a { transform: translateX(0); }
            nav.sidebar a.active, nav.sidebar a:hover { transform: translateX(0); }
            main.dashboard-content { padding: 20px; }
            main.dashboard-content h2 { font-size: 2rem; margin-bottom: 25px; }
            .search-bar { flex-direction: column; gap: 10px; box-shadow: none; }
            .search-bar input, .search-bar button { width: 100%; border-radius: 8px; }
            .search-bar button:hover { transform: translateY(0); }
            .product-grid-home, .product-listing-grid { grid-template-columns: 1fr; gap: 20px; } /* Single column on mobile */
            .product-card-home, .product-listing-card { max-width: none; }
            .product-card-home img { height: 200px; }
            .product-listing-card img { height: 160px; }
            .page-content-panel { padding: 20px; border-radius: 12px; }
            .order-list li { flex-direction: column; align-items: flex-start; padding: 15px; }
            .order-list li span { width: 100%; min-width: unset; margin-bottom: 5px; }
            .profile-info p strong { width: 100px; }

            /* Admin/Seller content when stacked (similar to dashboard-content) */
            .main-content { padding: 20px; margin: 25px auto; } /* Adjust margin for admin/seller content when stacked */
            .stats-grid { grid-template-columns: 1fr; gap: 20px; } /* Stack stats on medium screens */
            .stat-card h3 { font-size: 1.6rem; }
        }

        @media (max-width: 576px) { /* Small mobile devices */
            header { padding: 10px 15px; }
            header h1 { font-size: 1.8rem; }
            header h1 span.emoji { font-size: 2.4rem; }
            .logout-btn { padding: 8px 15px; font-size: 0.9rem; }
            .main-content, .dashboard-container-wrapper { padding: 15px; margin: 20px auto; }
            h1 { font-size: 2rem; margin-bottom: 20px; }
            .section-header { gap: 10px; margin-bottom: 15px; padding-bottom: 10px; }
            .section-header h2 { font-size: 1.6rem; }
            .add-new-btn { padding: 8px 15px; font-size: 0.9rem; }
            .card-panel { padding: 15px; border-radius: 12px; margin-bottom: 20px; }
            .form-group { margin-bottom: 15px; }
            .form-group label { font-size: 0.95rem; margin-bottom: 5px; }
            .form-group input, .form-group textarea, .form-group select { padding: 10px; font-size: 0.85rem; border-radius: 8px; }
            .form-action-btns button, .form-action-btns a.btn-link { padding: 10px 18px; font-size: 0.9rem; }
            .data-table th, .data-table td { padding: 10px; font-size: 0.8rem; }
            .status-badge { padding: 4px 8px; font-size: 0.75rem; }
            .stat-card { padding: 20px; }
            .stat-card h3 { font-size: 1.4rem; }
            .stat-card p { font-size: 1rem; }
            .product-card-home img { height: 180px; }
            .product-card-home .card-content h3 { font-size: 1.3rem; }
            .product-card-home .card-content p { font-size: 0.85rem; }
            .product-card-home .see-more-btn { padding: 10px 15px; font-size: 0.9rem; }
            .product-listing-card img { height: 140px; }
            .product-listing-card h4 { font-size: 1.2rem; }
            .product-listing-card .price { font-size: 1.1rem; }
            .product-listing-card .buy-button { padding: 10px 12px; font-size: 0.95rem; }
            .page-content-panel { padding: 15px; }
        }
    </style>
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <nav>
            <a href="/PlantPals/dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>">Home</a>
            <a href="/PlantPals/dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>">Product</a>
            <a href="/PlantPals/dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>">Profile</a>
            <a href="/PlantPals/dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>">Orders</a>
            <a href="/PlantPals/dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>">Contact</a>
        </nav>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <div class="dashboard-container-wrapper"> <nav class="sidebar">
            <a href="dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>">Home</a>
            <a href="dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>">Product</a>
            <a href="dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>">Profile</a>
            <a href="dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>">Orders</a>
            <a href="dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>">Contact</a>
        </nav>

        <main class="dashboard-content"> <?php
            if ($page == 'home') {
                ?>
                <h2>PlantPals</h2>
                <form class="search-bar" action="dashboard.php" method="get">
                    <input type="hidden" name="page" value="home" />
                    <input type="text" id="searchInput" name="q" placeholder="Search..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
                    <button type="submit">🔍</button>
                </form>

                <div id="flowerGrid" class="product-grid-home">
                <?php
                $filtered_flowers = [];
                $keyword = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

                // If DB query failed or no products, use initial data
                if (empty($flowers) && isset($all_initial_products)) {
                    $flowers_to_display = $all_initial_products;
                } else {
                    $flowers_to_display = $flowers; // Use data from DB
                }

                if (!empty($keyword)) {
                    foreach ($flowers_to_display as $flower) {
                        if (
                            stripos($flower['name'], $keyword) !== false ||
                            stripos($flower['description'], $keyword) !== false ||
                            stripos($flower['scientific_name'] ?? '', $keyword) !== false ||
                            stripos($flower['family'] ?? '', $keyword) !== false
                        ) {
                            $filtered_flowers[] = $flower;
                        }
                    }
                } else {
                    $filtered_flowers = $flowers_to_display;
                }

                if (empty($filtered_flowers)) {
                    echo "<p style='text-align: center; color: #777;'>Tidak ada hasil untuk pencarian Anda.</p>";
                } else {
                    foreach ($filtered_flowers as $flower) {
                        ?>
                        <div class="product-card-home">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($flower['name']); ?></h3>
                                <p><strong>Nama Ilmiah:</strong> <?php echo htmlspecialchars($flower['scientific_name'] ?? $flower['scientific'] ?? 'N/A'); ?></p>
                                <p><strong>Familia:</strong> <?php echo htmlspecialchars($flower['family'] ?? 'N/A'); ?></p>
                                <p><?php echo htmlspecialchars(substr($flower['description'] ?? '', 0, 80)); ?>...</p>
                            </div>
                            <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn">See more</a>
                        </div>
                        <?php
                    }
                }
                ?>
                </div> <?php
            } elseif ($page == 'products') {
                ?>
                <div class="page-content-panel">
                    <h2>Semua Produk Tanaman Hias</h2>
                    <p style="text-align: center; color: #555;">Pilih bunga favoritmu dan pesan dari toko terdekat!</p>
                    <div class="product-listing-grid">
                        <?php
                        // If DB query failed or no products, use initial data
                        if (empty($flowers) && isset($all_initial_products)) {
                            $flowers_to_display = $all_initial_products;
                        } else {
                            $flowers_to_display = $flowers; // Use data from DB
                        }
                        
                        foreach ($flowers_to_display as $flower):
                            // For products without a seller_id in DB, assign a dummy store for purchase form
                            $assigned_store_id = 'toko1'; // Default dummy store
                            $assigned_store_name = 'Toko Bunga Sejuk Asri - (Jl. Raya Puputan No. 100, Denpasar)';
                        ?>
                        <div class="product-listing-card">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h4><?php echo htmlspecialchars($flower['name']); ?></h4>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                                <div class="store-selection">
                                    <label for="store-<?php echo strtolower(str_replace(' ', '', $flower['name'])); ?>">Pilih Toko:</label>
                                    <select id="store-<?php echo strtolower(str_replace(' ', '', $flower['name'])); ?>" name="store">
                                        <?php foreach ($stores as $store_item): ?>
                                            <option value="<?php echo htmlspecialchars($store_item['id']); ?>"><?php echo htmlspecialchars($store_item['name']); ?> - (<?php echo htmlspecialchars($store_item['address']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="buy-button"
                                        onclick="handleOrder('<?php echo htmlspecialchars($flower['name']); ?>', '<?php echo htmlspecialchars($flower['price']); ?>', 'store-<?php echo strtolower(str_replace(' ', '', $flower['name'])); ?>');">
                                    Pesan Sekarang
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            } elseif ($page == 'profile') {
                // Fetch user data from DB for profile
                require_once 'config.php';
                $user_data = [];
                $sql_user = "SELECT * FROM users WHERE id = ?";
                if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
                    mysqli_stmt_bind_param($stmt_user, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_user);
                    $result_user = mysqli_stmt_get_result($stmt_user);
                    $user_data = mysqli_fetch_assoc($result_user);
                    mysqli_stmt_close($stmt_user);
                }
                mysqli_close($conn);
                ?>
                <div class="page-content-panel">
                    <h2>Profil Pengguna</h2>
                    <p style="text-align: center; color: #555;">Kelola informasi akun Anda di sini.</p>
                    <div class="profile-info">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username'] ?? $username); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? 'N/A'); ?></p>
                        <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A'); ?></p>
                        <p><strong>Nomor Telepon:</strong> <?php echo htmlspecialchars($user_data['phone_number'] ?? 'N/A'); ?></p>
                        <p><strong>Alamat:</strong> <?php echo htmlspecialchars($user_data['address'] ?? 'N/A'); ?></p>
                        <p><strong>Bergabung Sejak:</strong> <?php echo htmlspecialchars(date('d F Y', strtotime($user_data['created_at'] ?? 'now'))); ?></p>
                        <p><strong>Status Akun:</strong> Aktif (<?php echo htmlspecialchars($user_data['role'] ?? 'buyer'); ?>)</p>
                        <button class="profile-info-btn add-new-btn">Edit Profil</button>
                    </div>
                </div>
                <?php
            } elseif ($page == 'orders') {
                // Fetch orders for the logged-in user
                require_once 'config.php';
                $user_orders = [];
                $sql_orders = "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC";
                if ($stmt_orders = mysqli_prepare($conn, $sql_orders)) {
                    mysqli_stmt_bind_param($stmt_orders, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_orders);
                    $result_orders = mysqli_stmt_get_result($stmt_orders);
                    while ($row = mysqli_fetch_assoc($result_orders)) {
                        // Fetch order items for each order
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
                mysqli_close($conn);
                ?>
                <div class="page-content-panel">
                    <h2>Pesanan Anda</h2>
                    <p style="text-align: center; color: #555;">Berikut adalah daftar pesanan yang telah Anda lakukan.</p>
                    <?php if (empty($user_orders)): ?>
                        <p style="margin-top: 20px; text-align: center; color: #777;">Tidak ada pesanan yang ditemukan.</p>
                    <?php else: ?>
                        <ul class="order-list">
                            <?php foreach ($user_orders as $order): ?>
                                <li>
                                    <span><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order['id']); ?></span>
                                    <span><strong>Tanggal:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></span>
                                    <span><strong>Total:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                    <span><strong>Status:</strong> <span class="status-badge <?php echo htmlspecialchars($order['order_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span></span>
                                    <div style="width: 100%; margin-top: 10px; border-top: 1px dashed #eee; padding-top: 10px;">
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
                    <h2>Hubungi Kami</h2>
                    <p style="text-align: center; color: #555;">Kami siap membantu Anda. Silakan hubungi kami melalui informasi di bawah ini:</p>
                    <p><strong>Email:</strong> info@plantpals.com</p>
                    <p><strong>Telepon:</strong> +62 812-3456-7890</p>
                    <p><strong>Alamat:</strong> Jl. Bunga Indah No. 123, Denpasar, Bali, Indonesia</p>
                    <h3 style="margin-top: 30px;">Form Kontak</h3>
                    <form style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="text" placeholder="Nama Anda" style="padding: 12px; border: 1px solid #d0d0d0; border-radius: 8px; font-size: 1rem;">
                        <input type="email" placeholder="Email Anda" style="padding: 12px; border: 1px solid #d0d0d0; border-radius: 8px; font-size: 1rem;">
                        <textarea placeholder="Pesan Anda" rows="6" style="padding: 12px; border: 1px solid #d0d0d0; border-radius: 8px; resize: vertical; font-size: 1rem;"></textarea>
                        <button type="submit" class="buy-button add-new-btn" style="width: fit-content; align-self: flex-start;">Kirim Pesan</button>
                    </form>
                </div>
                <?php
            } else {
                ?>
                <div class="page-content-panel">
                    <h2>Halaman Tidak Ditemukan</h2>
                    <p style="text-align: center; color: #777;">Halaman yang Anda cari tidak tersedia. Silakan kembali ke <a href="dashboard.php?page=home">Home</a>.</p>
                </div>
                <?php
            }
            ?>
        </main>
    </div> <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        // Modified handleOrder function to redirect to order_form.php
        function handleOrder(productName, productPrice, storeSelectId) {
            const storeSelect = document.getElementById(storeSelectId);
            const selectedStoreOption = storeSelect.options[storeSelect.selectedIndex];
            const selectedStoreId = selectedStoreOption.value;
            const selectedStoreName = selectedStoreOption.text;

            // Encode product data to pass via URL (PHP 5.6 compatible)
            const urlParams = [];
            urlParams.push('product_name=' + encodeURIComponent(productName));
            urlParams.push('product_price=' + encodeURIComponent(productPrice));
            urlParams.push('store_id=' + encodeURIComponent(selectedStoreId));
            urlParams.push('store_name=' + encodeURIComponent(selectedStoreName));

            window.location.href = `order_form.php?${urlParams.join('&')}`;
        }
    </script>
</body>
</html>