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

// Include data.php to get store names if needed
include 'data.php';

// Get product data from URL parameters
$product_name = isset($_GET['product_name']) ? htmlspecialchars($_GET['product_name']) : 'Produk Tidak Dikenal';
$product_price_raw = isset($_GET['product_price']) ? floatval($_GET['product_price']) : 0; // Get raw price as float
$product_price_display = number_format($product_price_raw, 0, ',', '.'); // For display
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
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/order_form_styles.css">
    <style>
        .order-summary p strong {
            color: #3a5a20;
        }
        .total-price-display {
            font-size: 1.4em;
            font-weight: bold;
            color: #E5989B;
            margin-top: 20px;
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .form-group label {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <header>
        <h1><span class="emoji">🌿</span> PlantPals</h1>
        <form action="/PlantPals/logout.php" method="post" style="margin:0;">
            <button class="logout-btn" type="submit" name="logout">Logout (<?php echo htmlspecialchars($username); ?>)</button>
        </form>
    </header>

    <div class="main-content-wrapper-form">
        <div class="order-form-container">
            <h2>Lengkapi Detail Pemesanan</h2>
            <div class="order-summary">
                <p><strong>Produk:</strong> <?php echo htmlspecialchars($product_name); ?></p>
                <p><strong>Harga Satuan:</strong> Rp <span id="unitPrice"><?php echo $product_price_display; ?></span></p>
                <p><strong>Toko:</strong> <?php echo htmlspecialchars($store_display_name); ?></p>
            </div>

            <form action="process_order.php" method="post">
                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>">
                <input type="hidden" name="product_price" id="rawProductPrice" value="<?php echo $product_price_raw; ?>">
                <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($store_id); ?>">
                <input type="hidden" name="store_name_full" value="<?php echo htmlspecialchars($store_name_full); ?>">

                <div class="form-group">
                    <label for="quantity">Kuantitas:</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" required oninput="calculateTotal()">
                </div>

                <div class="total-price-display">
                    Total Harga: Rp <span id="totalPrice">0</span>
                </div>

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
        <a href="/PlantPals/dashboard.php?page=home" class="back-to-dashboard-btn">Kembali ke Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal(); // Calculate total on page load
        });

        function calculateTotal() {
            const unitPrice = parseFloat(document.getElementById('rawProductPrice').value);
            let quantity = parseInt(document.getElementById('quantity').value); // Use let for reassigning

            if (isNaN(quantity) || quantity < 1) {
                quantity = 1; // Set to 1 if invalid
                document.getElementById('quantity').value = quantity; // Update input field
            }

            const total = unitPrice * quantity;
            document.getElementById('totalPrice').innerText = formatRupiah(total);
        }

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID').format(amount);
        }
    </script>
</body>
</html>