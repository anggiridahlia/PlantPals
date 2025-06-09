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

if (!empty($store_id_string_param)) {
    // Fetch store details
    $stmt_store = mysqli_prepare($conn, "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, u.username as seller_username
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
        // Fetch products sold by this store's seller
        $stmt_products = mysqli_prepare($conn, "SELECT * FROM products WHERE seller_id = ? ORDER BY name ASC");
        if ($stmt_products) {
            mysqli_stmt_bind_param($stmt_products, "i", $store_details['seller_user_id']);
            mysqli_stmt_execute($stmt_products);
            $result_products = mysqli_stmt_get_result($stmt_products);
            while ($row_product = mysqli_fetch_assoc($result_products)) {
                $store_products[] = $row_product;
            }
            mysqli_stmt_close($stmt_products);
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
    <link rel="stylesheet" href="/PlantPals/css/dashboard_styles.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Layout & Background */
        body {
            background: linear-gradient(to right, rgb(228, 250, 228), rgb(242, 230, 234));
            font-family: 'Poppins', sans-serif; /* Menggunakan Poppins untuk teks */
            color: #3a5a20; /* Warna teks utama hijau tua */
        }
        
        /* Container untuk Profil Toko */
        .store-profile-container {
            max-width: 1000px; /* Lebar maksimum yang lebih besar */
            margin: 40px auto;
            background: #ffffff; /* Latar belakang putih */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); /* Bayangan yang lebih lembut */
            text-align: center;
            animation: fadeIn 0.8s ease-out; /* Animasi fade-in */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Judul Halaman */
        .store-profile-container h2 {
            color: #E5989B; /* Warna pinkish untuk judul */
            font-size: 2.8rem; /* Ukuran font lebih besar */
            margin-bottom: 25px;
            font-family: 'Montserrat', sans-serif; /* Montserrat untuk judul */
            position: relative;
            display: flex; /* Untuk ikon */
            align-items: center;
            justify-content: center;
            gap: 15px; /* Jarak antara ikon dan teks */
        }
        .store-profile-container h2::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #E5989B;
            margin: 15px auto 0;
            border-radius: 2px;
        }
        .store-profile-container h2 i {
            font-size: 1.2em; /* Ukuran ikon di judul */
            color: #3a5a20;
        }

        /* Detail Toko */
        .store-details {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px; /* Jarak antar item detail */
            margin-bottom: 40px;
            padding: 20px;
            background-color: #f9fdf9; /* Latar belakang sedikit berbeda */
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .store-details p {
            margin: 0;
            font-size: 1.15rem;
            color: #555;
            display: flex;
            align-items: center;
            gap: 10px; /* Jarak antara ikon dan teks */
        }
        .store-details strong {
            color: #3a5a20;
        }
        .store-details i { /* Gaya untuk ikon Font Awesome */
            color: #E5989B;
            font-size: 1.3rem;
        }

        /* Judul Bagian Produk */
        .products-section-title {
            color: #3a5a20;
            font-size: 2rem;
            margin-top: 40px;
            margin-bottom: 25px;
            font-family: 'Montserrat', sans-serif;
            position: relative;
            display: flex; /* Untuk ikon */
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .products-section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #3a5a20;
            margin: 10px auto 0;
            border-radius: 2px;
        }
        .products-section-title i {
            font-size: 1.1em;
            color: #E5989B;
        }

        /* Grid Produk (Menggunakan .grid dari dashboard_styles.css, disesuaikan) */
        .products-from-store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Ukuran kartu lebih besar */
            gap: 30px; /* Jarak antar kartu lebih besar */
            margin-top: 30px;
            padding: 0 20px; /* Padding sisi untuk grid */
        }

        /* Gaya Kartu Produk (Diambil dari dashboard_styles.css tapi disesuaikan) */
        .products-from-store-grid .card {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08); /* Bayangan lebih jelas */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .products-from-store-grid .card:hover {
            transform: translateY(-8px); /* Efek melayang lebih dramatis */
            box-shadow: 0 12px 25px rgba(0,0,0,0.15); /* Bayangan lebih kuat */
        }
        .products-from-store-grid .card img {
            width: 100%;
            height: 200px; /* Tinggi gambar lebih seragam */
            object-fit: cover;
            border-bottom: 1px solid #f0f0f0;
        }
        .products-from-store-grid .card-content {
            padding: 20px; /* Padding konten lebih besar */
            text-align: left;
            flex-grow: 1; /* Konten mengisi ruang yang tersedia */
        }
        .products-from-store-grid .card-content h4 {
            color: #3a5a20;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.4rem; /* Ukuran judul produk lebih besar */
            line-height: 1.3;
        }
        .products-from-store-grid .price {
            font-size: 1.3rem; /* Ukuran harga lebih besar */
            font-weight: bold;
            color: #E5989B; /* Warna harga pinkish */
            margin-bottom: 15px;
            display: block; /* Pastikan harga di baris baru */
        }
        .products-from-store-grid .buy-button {
            display: block;
            width: calc(100% - 40px); /* Sesuaikan dengan padding */
            padding: 12px 15px;
            margin: 0 20px 20px 20px; /* Margin bawah dan samping */
            background-color: #E5989B;
            color: white;
            border: none;
            border-radius: 10px; /* Border radius lebih lembut */
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            text-align: center;
            display: flex; /* Untuk ikon */
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .products-from-store-grid .buy-button:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-3px); /* Efek tombol terangkat */
            box-shadow: 0 5px 10px rgba(182, 88, 117, 0.4);
        }
        
        .no-products-message {
            margin-top: 30px;
            padding: 20px;
            background-color: #f0f0f0;
            border-radius: 8px;
            color: #777;
            font-size: 1.1rem;
        }
        .back-btn-container {
            margin-top: 40px;
            text-align: center;
        }
        .back-btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #3a5a20; /* Warna hijau tua */
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: flex; /* Untuk ikon */
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .back-btn:hover {
            background-color: #2f4d3a; /* Sedikit lebih gelap */
            transform: translateY(-2px);
        }

        /* Media Queries for Responsiveness */
        @media (max-width: 768px) {
            .store-profile-container {
                margin: 20px auto;
                padding: 25px;
            }
            .store-profile-container h2 {
                font-size: 2.2rem;
                flex-direction: column; /* Stack icon and text */
                gap: 5px;
            }
            .store-profile-container h2 i {
                font-size: 1.5em; /* Larger icon on small screens */
            }
            .products-section-title {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 5px;
            }
            .products-section-title i {
                font-size: 1.3em;
            }
            .products-from-store-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
                padding: 0 10px;
            }
            .products-from-store-grid .card img {
                height: 160px;
            }
            .products-from-store-grid .card-content h4 {
                font-size: 1.1rem;
            }
            .products-from-store-grid .price {
                font-size: 1.1rem;
            }
            .products-from-store-grid .buy-button {
                font-size: 0.95rem;
                padding: 10px;
            }
            .store-details {
                flex-direction: column; /* Ubah ke kolom di layar kecil */
                align-items: flex-start;
                padding: 15px;
            }
            .store-details p {
                font-size: 1rem;
                margin-bottom: 8px;
            }
        }
        @media (max-width: 480px) {
            .store-profile-container {
                padding: 15px;
            }
            .store-profile-container h2 {
                font-size: 1.8rem;
            }
            .products-from-store-grid {
                grid-template-columns: 1fr; /* Satu kolom di layar sangat kecil */
                padding: 0;
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

    <div class="store-profile-container">
        <?php if ($store_details): ?>
            <h2><i class="fas fa-store"></i> Profil Toko: <?php echo htmlspecialchars($store_details['name']); ?></h2>
            <div class="store-details">
                <p><i class="fas fa-user"></i> <strong>Penjual:</strong> <?php echo htmlspecialchars($store_details['seller_username']); ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <strong>Alamat:</strong> <?php echo htmlspecialchars($store_details['address'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-phone"></i> <strong>Telepon:</strong> <?php echo htmlspecialchars($store_details['phone_number'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($store_details['email'] ?? 'N/A'); ?></p>
            </div>

            <h3 class="products-section-title"><i class="fas fa-seedling"></i> Produk dari Toko Ini</h3>
            <?php if (empty($store_products)): ?>
                <p class="no-products-message">Toko ini belum memiliki produk.</p>
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
                                    onclick="handleOrder('<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['price']); ?>', '<?php echo htmlspecialchars($store_details['store_id_string']); ?>', '<?php echo htmlspecialchars($store_details['name'] . " - (" . $store_details['address'] . ")"); ?>');">
                                <i class="fas fa-shopping-cart"></i> Pesan Sekarang
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-products-message" style="margin-top: 50px; padding: 40px; background-color: #fdf3f3; border: 1px solid #f4c4c4;">
                <h2><i class="fas fa-exclamation-triangle"></i> Toko Tidak Ditemukan</h2>
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

    <script>
        function handleOrder(productName, productPrice, storeIdString, storeNameFull) {
            if (storeIdString === "" || storeNameFull === "") {
                alert("Informasi toko tidak lengkap untuk produk ini.");
                return;
            }

            const urlParams = [];
            urlParams.push('product_name=' + encodeURIComponent(productName));
            urlParams.push('product_price=' + encodeURIComponent(productPrice));
            urlParams.push('store_id=' + encodeURIComponent(storeIdString));
            urlParams.push('store_name=' + encodeURIComponent(storeNameFull));

            window.location.href = `/PlantPals/order_form.php?${urlParams.join('&')}`;
        }
    </script>
</body>
</html>