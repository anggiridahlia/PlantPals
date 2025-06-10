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
$user_id = $_SESSION['id']; // Ambil ID pengguna dari sesi

// Inisialisasi variabel untuk menyimpan item keranjang
$cart_items_for_checkout = [];
$total_checkout_amount = 0;

// Cek apakah data keranjang dikirimkan melalui POST dari dashboard.php?page=cart
if (isset($_POST['action']) && $_POST['action'] === 'checkout_cart' && isset($_POST['cart_items'])) {
    $cart_items_raw = $_POST['cart_items'];

    // Validasi dan proses setiap item dari keranjang
    foreach ($cart_items_raw as $id => $item_data) {
        $product_id = htmlspecialchars($item_data['id']);
        $product_name = htmlspecialchars($item_data['name']);
        $product_price = floatval($item_data['price']);
        $quantity = intval($item_data['quantity']);
        $store_id_string = htmlspecialchars($item_data['store_id_string']);
        $store_name = htmlspecialchars($item_data['store_name']);

        // Pastikan kuantitas positif dan harga valid
        if ($quantity > 0 && $product_price >= 0) {
            $subtotal = $product_price * $quantity;
            $total_checkout_amount += $subtotal;

            $cart_items_for_checkout[$product_id] = [
                'id' => $product_id,
                'name' => $product_name,
                'price' => $product_price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'store_id_string' => $store_id_string,
                'store_name' => $store_name
            ];
        }
    }

    // Jika keranjang kosong setelah validasi, redirect kembali
    if (empty($cart_items_for_checkout)) {
        echo "<script>alert('Keranjang pembayaran Anda kosong atau tidak valid.'); window.location.href='dashboard.php?page=cart';</script>";
        exit();
    }

} else {
    // Jika tidak ada data keranjang yang valid dari POST, redirect ke keranjang
    echo "<script>alert('Tidak ada item yang dipilih untuk pembayaran. Silakan pilih item dari keranjang.'); window.location.href='dashboard.php?page=cart';</script>";
    exit();
}

// Data pengguna yang akan diisi otomatis (jika ada di sesi atau DB)
$user_full_name = '';
$user_address = '';
$user_phone_number = '';
$user_email = '';

// Ambil data profil pengguna dari database untuk mengisi form otomatis
require_once 'config.php'; // Koneksi database
$sql_user_profile = "SELECT full_name, address, phone_number, email FROM users WHERE id = ?";
if ($stmt_profile = mysqli_prepare($conn, $sql_user_profile)) {
    mysqli_stmt_bind_param($stmt_profile, "i", $user_id);
    mysqli_stmt_execute($stmt_profile);
    mysqli_stmt_bind_result($stmt_profile, $db_full_name, $db_address, $db_phone_number, $db_email);
    if (mysqli_stmt_fetch($stmt_profile)) {
        $user_full_name = htmlspecialchars($db_full_name ?? '');
        $user_address = htmlspecialchars($db_address ?? '');
        $user_phone_number = htmlspecialchars($db_phone_number ?? '');
        $user_email = htmlspecialchars($db_email ?? '');
    }
    mysqli_stmt_close($stmt_profile);
}
mysqli_close($conn); // Tutup koneksi di sini setelah mengambil data profil
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pembayaran - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/order_form_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .order-summary-items {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            background-color: #f9fdf9;
            text-align: left;
        }
        .order-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        .order-summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .order-summary-item span {
            color: #555;
        }
        .order-summary-item strong {
            color: #3a5a20;
        }
        .total-price-final {
            font-size: 1.6em;
            font-weight: bold;
            color: #E5989B;
            margin-top: 20px;
            text-align: right;
            border-top: 2px solid #e0e0e0;
            padding-top: 15px;
        }
        .form-group label i {
            margin-right: 8px;
            color: #E5989B;
        }

        /* Gaya tambahan untuk metode pembayaran */
        #paymentMethodInfo {
            background-color: #e6f7e6;
            border: 1px solid #c3d9c3;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: left;
            font-size: 0.95em;
            color: #3a5a20;
        }
        #paymentMethodInfo p {
            margin-bottom: 5px;
        }
        #paymentMethodInfo strong {
            color: #E5989B;
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

    <div class="main-content-wrapper-form">
        <div class="order-form-container">
            <h2><i class="fas fa-file-invoice-dollar"></i> Detail Pembayaran</h2>
            <p class="page-description">Silakan periksa kembali pesanan Anda dan lengkapi detail pengiriman.</p>

            <div class="order-summary-items">
                <h3>Ringkasan Pesanan Anda:</h3>
                <?php foreach ($cart_items_for_checkout as $item): ?>
                    <div class="order-summary-item">
                        <span><strong><?php echo htmlspecialchars($item['name']); ?></strong> (<?php echo htmlspecialchars($item['quantity']); ?>x)</span>
                        <span>Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="total-price-final">
                    Total Keseluruhan: Rp <?php echo number_format($total_checkout_amount, 0, ',', '.'); ?>
                </div>
            </div>

            <form action="process_order.php" method="post">
                <?php foreach ($cart_items_for_checkout as $product_id => $item): ?>
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][id]" value="<?php echo htmlspecialchars($item['id']); ?>">
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][name]" value="<?php echo htmlspecialchars($item['name']); ?>">
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][price]" value="<?php echo htmlspecialchars($item['price']); ?>">
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>">
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][store_id_string]" value="<?php echo htmlspecialchars($item['store_id_string']); ?>">
                    <input type="hidden" name="checkout_items[<?php echo $product_id; ?>][store_name]" value="<?php echo htmlspecialchars($item['store_name']); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="total_checkout_amount" value="<?php echo htmlspecialchars($total_checkout_amount); ?>">
                <input type="hidden" name="action" value="checkout_cart">

                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Nama Lengkap:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo $user_full_name; ?>" required>
                </div>

                <div class="form-group">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Alamat Lengkap Pengiriman:</label>
                    <textarea id="address" name="address" rows="4" required><?php echo $user_address; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="phone_number"><i class="fas fa-phone"></i> Nomor Telepon (WhatsApp):</label>
                    <input type="tel" id="phone_number" name="phone_number" value="<?php echo $user_phone_number; ?>" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-at"></i> Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo $user_email; ?>">
                </div>

                <div class="form-group">
                    <label for="notes"><i class="fas fa-clipboard"></i> Catatan Tambahan (Opsional):</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="payment_method"><i class="fas fa-money-check-alt"></i> Metode Pembayaran:</label>
                    <select id="payment_method" name="payment_method" required onchange="showPaymentMethodInfo()">
                        <option value="">Pilih Metode Pembayaran</option>
                        <option value="bank_transfer">Transfer Bank</option>
                        <option value="cod">Cash on Delivery (COD)</option>
                    </select>
                </div>

                <div id="paymentMethodInfo" style="display: none;">
                    </div>

                <button type="submit" class="submit-btn"><i class="fas fa-cash-register"></i> Konfirmasi Pembayaran</button>
            </form>
        </div>
        <a href="/PlantPals/dashboard.php?page=cart" class="back-to-dashboard-btn"><i class="fas fa-arrow-left"></i> Kembali ke Keranjang</a>
    </div>

    <footer>
        <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showPaymentMethodInfo(); // Tampilkan info saat halaman dimuat (jika ada nilai default/terpilih)
        });

        function showPaymentMethodInfo() {
            const selectElement = document.getElementById('payment_method');
            const infoDiv = document.getElementById('paymentMethodInfo');
            const selectedValue = selectElement.value;

            // Hapus semua konten sebelumnya
            infoDiv.innerHTML = '';
            infoDiv.style.display = 'none';

            if (selectedValue === 'bank_transfer') {
                infoDiv.style.display = 'block';
                infoDiv.innerHTML = `
                    <p><strong>Transfer Bank:</strong></p>
                    <p>Silakan lakukan pembayaran ke rekening berikut:</p>
                    <p>Bank: <strong>BCA</strong></p>
                    <p>Nomor Rekening: <strong>123-456-7890</strong></p>
                    <p>Atas Nama: <strong>PT. PlantPals Indonesia</strong></p>
                    <p>Mohon konfirmasi pembayaran Anda setelah transfer selesai.</p>
                `;
            } else if (selectedValue === 'cod') {
                infoDiv.style.display = 'block';
                infoDiv.innerHTML = `
                    <p><strong>Cash on Delivery (COD):</strong></p>
                    <p>Pembayaran akan dilakukan secara tunai kepada kurir saat pesanan Anda tiba di alamat tujuan.</p>
                    <p>Mohon siapkan uang tunai sesuai total pembayaran.</p>
                `;
            }
        }
    </script>
</body>
</html>