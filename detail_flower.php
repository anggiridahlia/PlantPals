<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);

// Include data.php once, assuming it's in the same directory
include 'data.php';
require_once 'config.php'; // Include database connection

$selected_flower = null;
if (isset($_GET['name'])) {
    $flower_param = strtolower(str_replace('_', ' ', trim($_GET['name'])));

    // Fetch product from database instead of hardcoded array
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE LOWER(name) = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $flower_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $selected_flower = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    // If product not found in DB, try initial data as fallback for display (not for purchase)
    if (!$selected_flower) {
        foreach ($all_initial_products as $flower) { // Using $all_initial_products from data.php
            if (strtolower($flower['name']) === $flower_param) {
                $selected_flower = $flower;
                break;
            }
        }
    }
}

// Generate recommendations (6 random unique flowers, excluding the selected one)
$recommended_flowers = [];
// Fetch all products from DB for recommendations
$all_products_db = [];
$sql_all = "SELECT * FROM products ORDER BY RAND()"; // Order by RAND() for random
$result_all = mysqli_query($conn, $sql_all);
if ($result_all) {
    while ($row = mysqli_fetch_assoc($result_all)) {
        $all_products_db[] = $row;
    }
}
// Fallback to initial data if no products in DB
if (empty($all_products_db) && isset($all_initial_products)) {
    $all_products_db = $all_initial_products;
}


if ($selected_flower) {
    $count = 0;
    foreach ($all_products_db as $f) {
        if ($f['name'] !== $selected_flower['name'] && !empty($f['img']) && $count < 6) {
            $recommended_flowers[] = $f;
            $count++;
        }
    }
}

mysqli_close($conn); // Close connection after all DB operations
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $selected_flower ? htmlspecialchars($selected_flower['name']) : 'Detail Bunga'; ?> - PlantPals</title>
    <style>
        /* --- Base & Typography --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: rgb(245, 255, 245); /* Lighter, softer background */
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #2a4d3a; /* Darker, more professional green */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 16px;
        }

        a {
            color: #e66a7b; /* Consistent accent pink for links */
            text-decoration: none;
            transition: color 0.3s ease;
        }

        a:hover {
            color: #d17a87;
            text-decoration: underline;
        }

        /* --- Header --- */
        header {
            background-color: #E5989B; /* Pink header */
            color: white;
            padding: 15px 40px; /* More padding */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); /* Softer shadow */
            width: 100%; /* Full width header */
            min-height: 70px; /* Fixed minimum height */
        }

        header h1 {
            font-size: 2rem; /* Larger title */
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        header h1 span.emoji {
            font-size: 2.8rem; /* Larger emoji */
            line-height: 1; /* Align emoji properly */
        }

        .logout-btn {
            background: white;
            color: #E5989B;
            border: none;
            padding: 10px 20px; /* More padding */
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .logout-btn:hover {
            background-color: rgb(182, 88, 117);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        /* --- Page Title (under header, specific for detail_flower.php) --- */
        .page-main-title {
            font-size: 3rem; /* Prominent title */
            margin: 40px auto; /* Centered with vertical spacing */
            color: #386641; /* Deeper green for title */
            font-weight: 700;
            text-align: center;
            letter-spacing: -0.02em;
            width: 100%; /* Take full width */
            padding: 0 20px; /* Padding for smaller screens */
        }

        /* --- Main Content Area (Full Width, Two Columns) --- */
        .main-content-area {
            flex: 1; /* Allows it to grow and push footer down */
            display: flex;
            width: 100%; /* Takes full viewport width */
            padding: 0 40px; /* Horizontal padding from edges */
            gap: 40px; /* Space between columns */
            margin-bottom: 40px; /* Space before back button/footer */
            box-sizing: border-box; /* Include padding in width */
            align-items: flex-start; /* Align columns to the top */
        }

        /* --- Card Styling (General for all panels) --- */
        .card-panel {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            padding: 30px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column; /* Ensure content stacks inside */
        }

        /* --- Left Column: Flower Details (Takes 60% of available space) --- */
        .flower-details-column {
            flex: 3; /* Takes 3 parts, roughly 60% with flex:2 */
            min-width: 500px; /* Minimum width to prevent squishing content */
            max-width: 65%; /* Max width for this column */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .flower-details-column img {
            width: 100%;
            max-width: 600px; /* Max width of image within its column */
            height: 400px; /* Taller, prominent image */
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 35px; /* More space below image */
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .flower-details-column h3 {
            font-size: 3rem; /* Even larger, more impactful heading */
            margin-bottom: 30px;
            color: #2a4d3a;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
        }

        .flower-details-column .detail-item {
            display: flex;
            justify-content: flex-start;
            align-items: baseline;
            width: 100%;
            margin-bottom: 18px;
            font-size: 1.2rem; /* Slightly larger text */
            color: #4a4a4a;
        }

        .flower-details-column .detail-label {
            font-weight: 600;
            color: #386641;
            min-width: 180px; /* Consistent width for labels */
            text-align: left;
            padding-right: 20px;
            flex-shrink: 0; /* Prevent label from shrinking */
        }

        .flower-details-column .detail-value {
            flex: 1; /* Allows value to take remaining space */
            text-align: left;
            line-height: 1.7;
        }

        /* --- Right Column: Purchase (Takes 40% of available space) --- */
        .purchase-column { /* Renamed from sidebar-column */
            flex: 2; /* Takes 2 parts, roughly 40% with flex:3 */
            min-width: 400px; /* Minimum width for purchase content */
            max-width: 35%; /* Max width for this column */
            display: flex;
            flex-direction: column;
            gap: 40px; /* Space between sections in sidebar */
        }
        
        /* Ensure purchase-column uses card-panel styling */
        .purchase-column.card-panel { /* Added for explicit styling */
            /* No changes needed here, just ensuring it applies the base card-panel */
        }

        /* --- Section Titles within Panels --- */
        .card-panel .section-heading {
            font-size: 2.2rem; /* Larger headings for sections */
            color: #386641;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
        }

        /* --- Purchase Section Specific Styles --- */
        .purchase-section .price-display {
            font-size: 2.8rem; /* Larger, bolder price */
            font-weight: 700;
            color: #e66a7b;
            margin-bottom: 35px;
            text-align: center;
            letter-spacing: -0.03em;
        }

        .store-selection-detail label {
            display: block;
            font-size: 1.2rem; /* Larger label */
            color: #4a4a4a;
            margin-bottom: 15px;
            font-weight: 500;
            text-align: center;
        }

        .store-selection-detail select {
            width: 100%; /* Full width within panel */
            padding: 18px; /* More padding */
            border: 1px solid #d0d0d0;
            border-radius: 12px;
            font-size: 1.15rem; /* Larger font */
            background-color: #fcfcfc;
            cursor: pointer;
            margin-bottom: 30px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%232f5d3a%22%20d%3D%22M287%2C197.3L159.2%2C69.5c-4.4-4.4-11.4-4.4-15.8%2C0L5.4%2C197.3c-4.4%2C4.4-4.4%2C11.4%2C0%2C15.8c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0l135.9-135.9l135.9%2C135.9c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0C291.4%2C208.7%2C291.4%2C201.7%2C287%2C197.3z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 20px top 50%;
            background-size: 16px auto;
            transition: border-color 0.3s ease, box-shadow 0.2s ease;
        }

        .store-selection-detail select:hover {
            border-color: #a8a8a8;
        }

        .store-selection-detail select:focus {
            border-color: #e66a7b;
            outline: none;
            box-shadow: 0 0 0 4px rgba(230, 106, 123, 0.25);
        }

        .buy-button-detail {
            width: 100%; /* Full width within panel */
            padding: 20px 30px; /* More padding */
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.45rem; /* Larger font */
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.25);
        }

        .buy-button-detail:hover {
            background-color: #3b7d33;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.35);
        }

        /* --- Recommended Flowers Section (Now full-width below columns) --- */
        .recommended-flowers-section-container { /* Renamed for clarity and full width */
            width: 100%; /* Take full width of its parent */
            padding: 0 40px; /* Match padding of main-content-area */
            margin-top: 40px; /* Space from above columns */
            margin-bottom: 40px; /* Space before back button */
            box-sizing: border-box;
            max-width: 1300px; /* Match max-width of main content area */
            margin-left: auto; /* Center the container */
            margin-right: auto;
        }
        
        /* Apply card-panel styles to the container itself */
        .recommended-flowers-section-container.card-panel {
            /* Inherits card-panel styles like background, shadow, border-radius, padding */
        }

        .recommended-flowers-section-container .section-heading {
            margin-bottom: 30px; /* More space below heading */
        }

        .recommended-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); /* Increased min-width for larger cards */
            gap: 30px; /* More space between items */
            justify-content: center; /* Center grid items horizontally */
        }

        .recommended-item {
            background: #f8fdf8;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.07);
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            width: 100%; /* Ensure item takes full width of its grid cell */
        }

        .recommended-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .recommended-item img {
            width: 100%;
            height: 160px; /* Consistent height for recommended images */
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .recommended-item h4 {
            font-size: 1.3rem;
            color: #2a4d3a;
            margin-bottom: 10px;
            font-weight: 600;
            line-height: 1.3;
        }

        .recommended-item .price {
            font-weight: 700;
            color: #e66a7b;
            font-size: 1.15rem;
            margin-bottom: 18px;
        }

        .recommended-item .view-button {
            background-color: #E5989B;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1.05rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            width: 95%; /* Adjusted to be almost full width of item */
            display: block;
        }

        .recommended-item .view-button:hover {
            background-color: #d17a87;
        }

        /* --- Back Button --- */
        .back-btn-container {
            width: 100%;
            display: flex;
            justify-content: center;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .back-btn {
            padding: 15px 30px;
            background-color: #E5989B;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.15rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 6px 15px rgba(229, 152, 155, 0.25);
        }
        .back-btn:hover {
            background-color: #d17a87;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(229, 152, 155, 0.35);
        }

        /* --- Footer --- */
        footer {
            text-align: center;
            padding: 20px 0;
            font-size: 0.95rem;
            color: #777;
            background-color: rgb(242, 230, 234);
            border-top: 1px solid rgb(217, 195, 208);
            width: 100%;
        }

        /* --- Responsive Adjustments --- */
        @media (max-width: 1200px) {
            .page-main-title {
                font-size: 2.8rem;
            }
            .main-content-area {
                padding: 0 30px;
                gap: 30px;
            }
            .flower-details-column {
                min-width: 450px;
            }
            .purchase-column {
                min-width: 350px;
            }
            .flower-details-column img {
                height: 350px;
            }
            .flower-details-column h3 {
                font-size: 2.5rem;
            }
            .flower-details-column .detail-item {
                font-size: 1.1rem;
            }
            .flower-details-column .detail-label {
                min-width: 170px;
            }
            .card-panel {
                padding: 25px;
            }
            .section-heading {
                font-size: 2rem;
            }
            .purchase-section .price-display {
                font-size: 2.5rem;
            }
            .store-selection-detail select {
                padding: 16px;
                font-size: 1.1rem;
            }
            .buy-button-detail {
                padding: 18px;
                font-size: 1.3rem;
            }
            /* Adjusted recommended-grid for this breakpoint */
            .recommended-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Allow slightly smaller min for more columns */
                gap: 25px;
            }
            .recommended-item img {
                height: 120px;
            }
            .recommended-item h4 {
                font-size: 1.2rem;
            }
            .recommended-item .price {
                font-size: 1.1rem;
            }
            .recommended-item .view-button {
                padding: 10px 18px;
                font-size: 1rem;
            }
            .back-btn-container {
                margin-top: 30px;
                margin-bottom: 30px;
            }
        }

        @media (max-width: 992px) {
            .main-content-area {
                flex-direction: column; /* Stack columns on medium screens */
                align-items: center;
                padding: 0 20px;
                gap: 30px;
            }
            .flower-details-column, .purchase-column {
                flex: none; /* Remove flex sizing */
                width: 100%; /* Take full width for stacked layout */
                max-width: unset; /* Remove max-width constraint */
                min-width: unset; /* Remove min-width constraint */
            }
            .flower-details-column img {
                height: 300px;
            }
            .flower-details-column h3 {
                font-size: 2.2rem;
            }
            .flower-details-column .detail-label {
                min-width: 150px; /* Adjust label width for stacked */
            }
            .purchase-section .price-display {
                font-size: 2.2rem;
            }
            .store-selection-detail select,
            .buy-button-detail {
                max-width: 500px; /* Constrain width when stacked */
                margin-left: auto;
                margin-right: auto;
            }
            .recommended-flowers-section-container {
                /* When stacked, this section will also adopt card-panel styling */
                padding: 25px;
                border-radius: 16px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            }
            .recommended-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); /* Allow more columns */
                gap: 20px;
            }
            .recommended-item img {
                height: 100px;
            }
            .recommended-item h4 {
                font-size: 1.1rem;
            }
            .recommended-item .price {
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                padding-bottom: 10px;
                min-height: unset;
            }
            .logout-btn {
                align-self: flex-end;
                margin-top: -45px;
                margin-right: 20px;
            }
            .page-main-title {
                font-size: 2rem;
                margin-top: 25px;
                margin-bottom: 25px;
            }
            .main-content-area {
                padding: 0 15px; /* Smaller horizontal padding */
                gap: 25px;
            }
            .card-panel {
                padding: 20px;
                border-radius: 12px;
            }
            .flower-details-column img {
                height: 250px;
                margin-bottom: 25px;
            }
            .flower-details-column h3 {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }
            .flower-details-column .detail-item {
                font-size: 1rem;
                flex-direction: column; /* Stack label and value */
                align-items: flex-start;
                margin-bottom: 10px;
            }
            .flower-details-column .detail-label {
                min-width: unset;
                padding-right: 0;
                margin-bottom: 5px;
                font-size: 0.95rem;
                text-align: left;
            }
            .flower-details-column .detail-value {
                font-size: 0.95rem;
            }
            .section-heading {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }
            .purchase-section .price-display {
                font-size: 2rem;
                margin-bottom: 25px;
            }
            .store-selection-detail label {
                font-size: 1rem;
                margin-bottom: 10px;
            }
            .store-selection-detail select {
                padding: 12px;
                font-size: 1rem;
                margin-bottom: 20px;
                background-position: right 15px top 50%;
                background-size: 12px auto;
            }
            .buy-button-detail {
                padding: 16px;
                font-size: 1.15rem;
            }
            .recommended-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
            }
            .recommended-item {
                padding: 15px;
            }
            .recommended-item img {
                height: 100px;
                margin-bottom: 15px;
            }
            .recommended-item h4 {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            .recommended-item .price {
                font-size: 1rem;
                margin-bottom: 18px;
            }
            .recommended-item .view-button {
                padding: 10px 18px;
                font-size: 0.95rem;
            }
            .back-btn-container {
                margin-top: 25px;
                margin-bottom: 25px;
            }
            .back-btn {
                padding: 12px 25px;
                font-size: 1.05rem;
            }
        }

        @media (max-width: 500px) {
            .page-main-title {
                font-size: 1.8rem;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .main-content-area {
                padding: 0 10px;
                gap: 20px;
            }
            .card-panel {
                padding: 15px;
            }
            .flower-details-column img {
                height: 180px;
                margin-bottom: 20px;
            }
            .flower-details-column h3 {
                font-size: 1.5rem;
                margin-bottom: 15px;
            }
            .flower-details-column .detail-item {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            .flower-details-column .detail-label {
                font-size: 0.85rem;
            }
            .purchase-section .price-display {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }
            .store-selection-detail select {
                padding: 10px;
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
            .buy-button-detail {
                padding: 14px;
                font-size: 1rem;
            }
            .recommended-grid {
                grid-template-columns: 1fr; /* Force single column on very small screens */
                gap: 20px;
            }
            .recommended-item img {
                height: 100px;
            }
            .recommended-item h4 {
                font-size: 1rem;
            }
            .recommended-item .price {
                font-size: 0.95rem;
            }
            .recommended-item .view-button {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            .back-btn-container {
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .back-btn {
                padding: 10px 18px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <form action="logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo $username; ?>)</button>
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
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo htmlspecialchars($store['id']); ?>"><?php echo htmlspecialchars($store['name']); ?> - (<?php echo htmlspecialchars($store['address']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="buy-button-detail"
                        onclick="handleOrder('<?php echo htmlspecialchars($selected_flower['name']); ?>', '<?php echo htmlspecialchars($selected_flower['price']); ?>', 'store-select-detail');">
                    Pesan Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="flower-details-column card-panel" style="text-align: center; width: 100%;">
                <h3>Bunga Tidak Ditemukan</h3>
                <p>Informasi bunga yang Anda cari tidak tersedia. Pastikan nama bunga yang Anda masukkan benar.</p>
            </div>
        <?php endif; ?>
    </div> <?php if ($selected_flower && !empty($recommended_flowers)): ?>
        <div class="recommended-flowers-section-container card-panel">
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
        <a href="dashboard.php?page=home" class="back-btn">Kembali ke Home</a>
    </div>

    <footer>
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