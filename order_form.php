<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);

// Include data.php to get store names if needed
include 'data.php';

// Get product data from URL parameters
// --- PERBAIKAN DI SINI UNTUK KOMPATIBILITAS PHP 5.6 ---
$product_name = isset($_GET['product_name']) ? htmlspecialchars($_GET['product_name']) : 'Produk Tidak Dikenal';
$product_price = isset($_GET['product_price']) ? htmlspecialchars($_GET['product_price']) : '0';
$store_id = isset($_GET['store_id']) ? htmlspecialchars($_GET['store_id']) : '';
$store_name_full = isset($_GET['store_name']) ? htmlspecialchars($_GET['store_name']) : 'Toko Tidak Dikenal'; // Full name from URL

// You might want to strip the address from store_name_full for display if needed
$store_display_name = $store_name_full;
preg_match('/^(.*?) - \(.*\)$/', $store_name_full, $matches);
if (isset($matches[1])) {
    $store_display_name = trim($matches[1]);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pemesanan - PlantPals</title>
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

        /* --- Header (Consistent with dashboard/detail_flower) --- */
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

        /* --- Main Content Area --- */
        .main-content-wrapper {
            flex: 1;
            width: 100%;
            max-width: 800px; /* Constrain form width */
            margin: 40px auto; /* Center with vertical spacing */
            padding: 0 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .order-form-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            padding: 40px; /* Generous padding */
            width: 100%;
        }

        .order-form-container h2 {
            font-size: 2.5rem;
            color: #386641;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
        }

        .order-summary {
            background-color: #f0f8f0; /* Light green background */
            border: 1px solid #c3d9c3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .order-summary p {
            margin-bottom: 8px;
        }

        .order-summary p strong {
            color: #2f5d3a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 1.1rem;
            color: #4a4a4a;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #d0d0d0;
            border-radius: 10px;
            font-size: 1rem;
            color: #333;
            background-color: #fcfcfc;
            transition: border-color 0.3s ease, box-shadow 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #e66a7b;
            outline: none;
            box-shadow: 0 0 0 3px rgba(230, 106, 123, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            width: 100%;
            padding: 18px 30px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.35rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.25);
        }

        .submit-btn:hover {
            background-color: #3b7d33;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.35);
        }

        .back-to-dashboard-btn {
            display: block;
            width: fit-content;
            margin: 30px auto 0 auto;
            padding: 12px 25px;
            background-color: #E5989B;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(229, 152, 155, 0.2);
        }

        .back-to-dashboard-btn:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(229, 152, 155, 0.3);
        }

        /* --- Footer (Consistent with dashboard/detail_flower) --- */
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
        @media (max-width: 768px) {
            header {
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
            .main-content-wrapper {
                margin: 25px auto;
                padding: 0 15px;
            }
            .order-form-container {
                padding: 30px;
            }
            .order-form-container h2 {
                font-size: 2rem;
                margin-bottom: 25px;
            }
            .order-summary {
                font-size: 1rem;
                padding: 15px;
            }
            .form-group label {
                font-size: 1rem;
            }
            .form-group input, .form-group textarea {
                padding: 12px;
                font-size: 0.95rem;
            }
            .submit-btn {
                padding: 16px;
                font-size: 1.2rem;
            }
            .back-to-dashboard-btn {
                padding: 10px 20px;
                font-size: 1rem;
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
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            .order-form-container {
                padding: 20px;
            }
            .order-form-container h2 {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }
            .order-summary {
                font-size: 0.9rem;
            }
            .form-group label {
                font-size: 0.9rem;
            }
            .form-group input, .form-group textarea {
                padding: 10px;
                font-size: 0.9rem;
            }
            .submit-btn {
                padding: 14px;
                font-size: 1.1rem;
            }
            .back-to-dashboard-btn {
                padding: 8px 15px;
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

    <div class="main-content-wrapper">
        <div class="order-form-container">
            <h2>Lengkapi Detail Pemesanan</h2>
            <div class="order-summary">
                <p><strong>Produk:</strong> <?php echo $product_name; ?></p>
                <p><strong>Harga:</strong> Rp <?php echo number_format($product_price, 0, ',', '.'); ?></p>
                <p><strong>Toko:</strong> <?php echo $store_display_name; ?></p>
            </div>

            <form action="process_order.php" method="post">
                <input type="hidden" name="product_name" value="<?php echo $product_name; ?>">
                <input type="hidden" name="product_price" value="<?php echo $product_price; ?>">
                <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
                <input type="hidden" name="store_name_full" value="<?php echo $store_name_full; ?>">

                <div class="form-group">
                    <label for="full_name">Nama Lengkap:</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="address">Alamat Lengkap Pengiriman:</label>
                    <textarea id="address" name="address" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="phone_number">Nomor Telepon (WhatsApp):</label>
                    <input type="tel" id="phone_number" name="phone_number" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="notes">Catatan Tambahan (Opsional):</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>

                <button type="submit" class="submit-btn">Konfirmasi Pesanan</button>
            </form>
        </div>
        <a href="dashboard.php?page=home" class="back-to-dashboard-btn">Kembali ke Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>
</body>
</html>