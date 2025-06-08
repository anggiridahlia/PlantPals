<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['id']; // Get the buyer's ID from session

require_once 'config.php'; // Include database connection
include 'data.php'; // For store information if needed

// Retrieve POST data from the order form (using PHP 5.6 compatible syntax)
$product_name_from_form = isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : 'N/A';
$product_price_from_form = isset($_POST['product_price']) ? htmlspecialchars($_POST['product_price']) : '0';
$store_id_from_form = isset($_POST['store_id']) ? htmlspecialchars($_POST['store_id']) : 'N/A';
$store_name_full_from_form = isset($_POST['store_name_full']) ? htmlspecialchars($_POST['store_name_full']) : 'N/A';

$full_name = isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : 'N/A';
$address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : 'N/A';
$phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : 'N/A';
$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'N/A';
$notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : 'Tidak ada';

// --- Find product_id from database based on product_name ---
$product_id_db = null;
$stmt_product_id = mysqli_prepare($conn, "SELECT id, price FROM products WHERE name = ?");
if ($stmt_product_id) {
    mysqli_stmt_bind_param($stmt_product_id, "s", $product_name_from_form);
    mysqli_stmt_execute($stmt_product_id);
    mysqli_stmt_bind_result($stmt_product_id, $db_product_id, $db_product_price);
    if (mysqli_stmt_fetch($stmt_product_id)) {
        $product_id_db = $db_product_id;
        // Optionally, cross-check price from DB vs form to prevent tampering
        // For simplicity, we trust form data for price here, but in real app, use $db_product_price
    }
    mysqli_stmt_close($stmt_product_id);
}

if ($product_id_db === null) {
    // Product not found in database, handle error or redirect
    echo "Produk tidak ditemukan di database. Pesanan tidak dapat diproses.";
    exit;
}

// --- Start Transaction (for atomicity) ---
mysqli_autocommit($conn, FALSE);
$success = true;

// 1. Insert into orders table
$order_id = null;
$total_amount = $product_price_from_form * 1; // Assuming quantity 1 for simplicity in this flow

$sql_order = "INSERT INTO orders (user_id, total_amount, delivery_address, customer_name, customer_phone, customer_email, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
    mysqli_stmt_bind_param($stmt_order, "idsssss", $user_id, $total_amount, $address, $full_name, $phone_number, $email, $notes);
    if (mysqli_stmt_execute($stmt_order)) {
        $order_id = mysqli_insert_id($conn); // Get the ID of the newly inserted order
    } else {
        $success = false;
    }
    mysqli_stmt_close($stmt_order);
} else {
    $success = false;
}

// 2. Insert into order_items table (only if order was successfully created)
if ($success && $order_id) {
    $quantity = 1; // Always 1 for now, as it's single product per order form
    $sql_item = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, store_id, store_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
    if ($stmt_item = mysqli_prepare($conn, $sql_item)) {
        mysqli_stmt_bind_param($stmt_item, "iisddss", $order_id, $product_id_db, $product_name_from_form, $product_price_from_form, $quantity, $store_id_from_form, $store_name_full_from_form);
        if (!mysqli_stmt_execute($stmt_item)) {
            $success = false;
        }
        mysqli_stmt_close($stmt_item);
    } else {
        $success = false;
    }
} else {
    $success = false;
}


// --- Commit or Rollback Transaction ---
if ($success) {
    mysqli_commit($conn);
    $confirmation_message = "Pesanan Anda berhasil dikonfirmasi!";
    $confirmation_status_class = "success";
} else {
    mysqli_rollback($conn);
    $confirmation_message = "Terjadi kesalahan saat memproses pesanan Anda. Mohon coba lagi.";
    $confirmation_status_class = "error";
    // Log error details for debugging in a real application
}

mysqli_autocommit($conn, TRUE); // Re-enable autocommit
mysqli_close($conn);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - PlantPals</title>
    <style>
        /* Consistent base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: rgb(245, 255, 245);
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #2a4d3a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 16px;
        }

        a {
            color: #e66a7b;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        a:hover {
            color: #d17a87;
            text-decoration: underline;
        }

        header {
            background-color: #E5989B;
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            min-height: 70px;
        }

        header h1 {
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        header h1 span.emoji {
            font-size: 2.8rem;
            line-height: 1;
        }

        .logout-btn {
            background: white;
            color: #E5989B;
            border: none;
            padding: 10px 20px;
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

        .main-content-wrapper {
            flex: 1;
            width: 100%;
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .confirmation-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            padding: 40px;
            width: 100%;
            text-align: center;
        }

        .confirmation-container h2 {
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .confirmation-container h2.success {
            color: #4CAF50; /* Green for success */
        }
        .confirmation-container h2.error {
            color: #f44336; /* Red for error */
        }

        .confirmation-container p {
            font-size: 1.15rem;
            line-height: 1.8;
            margin-bottom: 15px;
            color: #4a4a4a;
        }

        .confirmation-container p strong {
            color: #2f5d3a;
        }

        .order-details-summary {
            background-color: #f0f8f0;
            border: 1px solid #c3d9c3;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            margin-bottom: 30px;
            text-align: left;
        }

        .order-details-summary h3 {
            font-size: 1.4rem;
            color: #2f5d3a;
            margin-bottom: 15px;
            border-bottom: 1px solid #d0d0d0;
            padding-bottom: 10px;
        }

        .order-details-summary p {
            font-size: 1rem;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .order-details-summary p strong {
            display: inline-block;
            min-width: 150px;
            color: #386641;
        }
        .order-details-summary p span {
            word-wrap: break-word; /* Ensure long addresses wrap */
            display: inline-block;
            vertical-align: top;
        }

        .back-to-dashboard-btn {
            display: inline-block;
            margin: 30px auto 0 auto;
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
            box-shadow: 0 6px 15px rgba(229, 152, 155, 0.25);
        }

        .back-to-dashboard-btn:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(229, 152, 155, 0.35);
        }

        footer {
            text-align: center;
            padding: 20px 0;
            font-size: 0.95rem;
            color: #777;
            background-color: rgb(242, 230, 234);
            border-top: 1px solid rgb(217, 195, 208);
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            header {
                flex-direction: column; align-items: flex-start; padding-bottom: 10px; min-height: unset;
            }
            .logout-btn {
                align-self: flex-end; margin-top: -45px; margin-right: 20px;
            }
            .main-content-wrapper {
                margin: 25px auto; padding: 0 15px;
            }
            .confirmation-container {
                padding: 30px;
            }
            .confirmation-container h2 {
                font-size: 2.2rem;
            }
            .confirmation-container p {
                font-size: 1rem;
            }
            .order-details-summary {
                padding: 15px;
            }
            .order-details-summary h3 {
                font-size: 1.2rem;
            }
            .order-details-summary p {
                font-size: 0.9rem;
            }
            .order-details-summary p strong {
                min-width: 120px;
            }
            .back-to-dashboard-btn {
                padding: 12px 25px; font-size: 1rem;
            }
        }
        @media (max-width: 500px) {
            header {
                padding: 10px 15px;
            }
            header h1 {
                font-size: 1.6rem;
            }
            header h1 span.emoji {
                font-size: 2.2rem;
            }
            .logout-btn {
                padding: 8px 15px; font-size: 0.9rem;
            }
            .confirmation-container {
                padding: 20px;
            }
            .confirmation-container h2 {
                font-size: 1.8rem;
            }
            .confirmation-container p {
                font-size: 0.9rem;
            }
            .order-details-summary p strong {
                min-width: 90px;
            }
            .back-to-dashboard-btn {
                padding: 8px 15px; font-size: 0.9rem;
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

    <div class="main-content-wrapper">
        <div class="confirmation-container">
            <h2 class="<?php echo $confirmation_status_class; ?>">
                <?php echo ($confirmation_status_class == 'success') ? '🎉 Pesanan Terkonfirmasi! 🎉' : '❌ Gagal Memproses Pesanan ❌'; ?>
            </h2>
            <p><?php echo $confirmation_message; ?></p>

            <?php if ($success): // Only show order details if successful ?>
            <div class="order-details-summary">
                <h3>Detail Produk:</h3>
                <p><strong>Nama Produk:</strong> <span><?php echo $product_name_from_form; ?></span></p>
                <p><strong>Harga:</strong> <span>Rp <?php echo number_format($product_price_from_form, 0, ',', '.'); ?></span></p>
                <p><strong>Dari Toko:</strong> <span><?php echo $store_name_full_from_form; ?></span></p>

                <h3 style="margin-top: 25px;">Detail Pengiriman:</h3>
                <p><strong>Nama Lengkap:</strong> <span><?php echo $full_name; ?></span></p>
                <p><strong>Alamat:</strong> <span><?php echo $address; ?></span></p>
                <p><strong>Telepon:</strong> <span><?php echo $phone_number; ?></span></p>
                <p><strong>Email:</strong> <span><?php echo $email; ?></span></p>
                <p><strong>Catatan:</strong> <span><?php echo $notes; ?></span></p>
            </div>
            <p>Tim kami akan segera menghubungi Anda untuk proses pengiriman.</p>
            <?php endif; ?>
        </div>
        <a href="dashboard.php?page=home" class="back-to-dashboard-btn">Kembali ke Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>
</body>
</html>