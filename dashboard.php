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

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

include 'data.php'; // For initial product fallback data
require_once 'config.php'; // Database connection, now opened once.

// Variabel untuk pesan pop-up
$popup_message = "";
$popup_status = ""; // 'success' or 'error'

// --- Fetch Stores from Database and Organize by seller_user_id (moved up) ---
$stores_by_seller_id = [];
$sql_stores = "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, s.followers_count FROM stores s JOIN users u ON s.seller_user_id = u.id WHERE u.role = 'seller' ORDER BY s.name ASC"; // Get followers_count
$result_stores = mysqli_query($conn, $sql_stores);
if ($result_stores) {
    while ($row_store = mysqli_fetch_assoc($result_stores)) {
        $seller_id_for_store = $row_store['seller_user_id'];
        if (!isset($stores_by_seller_id[$seller_id_for_store])) {
            $stores_by_seller_id[$seller_id_for_store] = [];
        }
        $stores_by_seller_id[$seller_id_for_store][] = $row_store;
    }
}
// Fallback jika tidak ada toko yang terhubung ke seller di DB
if (empty($stores_by_seller_id) && isset($DEFAULT_FALLBACK_SELLER_ID)) {
    $stores_by_seller_id[$DEFAULT_FALLBACK_SELLER_ID] = [
        ["id" => 1, "store_id_string" => "toko_bunga_asri", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Raya Puputan No. 100, Denpasar", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID, "followers_count" => 0], // Add followers_count
    ];
}


// --- Handle Add to Cart Action ---
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = 1;

    if ($product_id) {
        $sql_product = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                        FROM products p
                        WHERE p.id = ?"; 
        
        if ($stmt_product = mysqli_prepare($conn, $sql_product)) {
            mysqli_stmt_bind_param($stmt_product, "i", $product_id);
            mysqli_stmt_execute($stmt_product);
            $result_product = mysqli_stmt_get_result($stmt_product);
            $product_details = mysqli_fetch_assoc($result_product);
            mysqli_stmt_close($stmt_product);

            if ($product_details) {
                $selling_store_for_cart = null;
                if (isset($product_details['seller_id']) && isset($stores_by_seller_id[$product_details['seller_id']])) {
                    $selling_store_for_cart = $stores_by_seller_id[$product_details['seller_id']][0] ?? null;
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
                    if ($_SESSION['cart'][$product_id]['quantity'] + $quantity > $product_details['stock']) {
                        $popup_message = "Tidak dapat menambahkan. Stok untuk " . htmlspecialchars($product_details['name']) . " tidak mencukupi. Tersedia: " . htmlspecialchars($product_details['stock']) . ".";
                        $popup_status = "error";
                    } else {
                        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                        $popup_message = "Produk '" . htmlspecialchars($product_details['name']) . "' berhasil ditambahkan ke keranjang!";
                        $popup_status = "success";
                    }
                } else {
                    if ($quantity > $product_details['stock']) {
                         $popup_message = "Tidak dapat menambahkan. Stok untuk " . htmlspecialchars($product_details['name']) . " tidak mencukupi. Tersedia: " . htmlspecialchars($product_details['stock']) . ".";
                         $popup_status = "error";
                    } else {
                        $_SESSION['cart'][$product_id] = [
                            'id' => $product_details['id'],
                            'name' => $product_details['name'],
                            'img' => $product_details['img'],
                            'price' => $product_details['price'],
                            'stock' => $product_details['stock'],
                            'seller_id' => $product_details['seller_id'],
                            'store_id_string' => $store_id_for_cart,
                            'store_name' => $store_name_for_cart,
                            'quantity' => $quantity,
                        ];
                        $popup_message = "Produk '" . htmlspecialchars($product_details['name']) . "' berhasil ditambahkan ke keranjang!";
                        $popup_status = "success";
                    }
                }
            } else {
                $popup_message = "Produk tidak ditemukan atau tidak tersedia.";
                $popup_status = "error";
            }
        } else {
            error_log("Error preparing product fetch for cart: " . mysqli_error($conn));
            $popup_message = "Terjadi kesalahan sistem. Mohon coba lagi.";
            $popup_status = "error";
        }
    }
    // Redirect after POST to prevent resubmission
    header('Location: dashboard.php?page=' . $page . '&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}

// --- Handle Update Cart Quantity Action ---
if (isset($_POST['action']) && $_POST['action'] === 'update_cart_quantity') {
    $product_id = $_POST['product_id'] ?? null;
    $new_quantity = intval($_POST['quantity'] ?? 0);

    if ($product_id && isset($_SESSION['cart'][$product_id])) {
        $current_db_stock = 0;
        $stmt_stock = mysqli_prepare($conn, "SELECT stock FROM products WHERE id = ?");
        if ($stmt_stock) {
            mysqli_stmt_bind_param($stmt_stock, "i", $product_id);
            mysqli_stmt_execute($stmt_stock);
            mysqli_stmt_bind_result($stmt_stock, $current_db_stock);
            mysqli_stmt_fetch($stmt_stock);
            mysqli_stmt_close($stmt_stock);
        }

        if ($new_quantity > 0) {
            if ($new_quantity <= $current_db_stock) {
                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                $popup_message = "Kuantitas diperbarui.";
                $popup_status = "success";
            } else {
                $_SESSION['cart'][$product_id]['quantity'] = $current_db_stock;
                $popup_message = "Stok tidak mencukupi untuk kuantitas yang diminta. Kuantitas diatur ke: " . $current_db_stock . ".";
                $popup_status = "error";
            }
        } else {
            unset($_SESSION['cart'][$product_id]);
            $popup_message = "Produk dihapus dari keranjang.";
            $popup_status = "success";
        }
    } else {
        $popup_message = "Gagal memperbarui keranjang.";
        $popup_status = "error";
    }
    header('Location: dashboard.php?page=cart&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}

// --- Handle Remove from Cart Action ---
if (isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $product_id = $_POST['product_id'] ?? null;
    if ($product_id && isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $popup_message = "Produk dihapus dari keranjang.";
        $popup_status = "success";
    } else {
        $popup_message = "Gagal menghapus produk dari keranjang.";
        $popup_status = "error";
    }
    header('Location: dashboard.php?page=cart&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}

// --- Handle Cancel Order Action ---
if (isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id_to_cancel = $_POST['order_id'] ?? null;
    $user_id_from_session = $_SESSION['id'];

    if ($order_id_to_cancel) {
        $order_to_check = [];
        $sql_check_order = "SELECT order_status, order_date FROM orders WHERE id = ? AND user_id = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check_order)) {
            mysqli_stmt_bind_param($stmt_check, "ii", $order_id_to_cancel, $user_id_from_session);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $order_to_check = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);
        }

        if ($order_to_check) {
            $order_timestamp = strtotime($order_to_check['order_date']);
            $current_timestamp = time();
            $one_hour_limit = 60 * 60; // 1 jam dalam detik

            if (($current_timestamp - $order_timestamp) <= $one_hour_limit && 
                ($order_to_check['order_status'] == 'pending' || $order_to_check['order_status'] == 'processing')) {
                
                mysqli_autocommit($conn, FALSE);
                $cancel_success = true;

                $sql_update_status = "UPDATE orders SET order_status = 'cancelled' WHERE id = ? AND user_id = ?";
                if ($stmt_update = mysqli_prepare($conn, $sql_update_status)) {
                    mysqli_stmt_bind_param($stmt_update, "ii", $order_id_to_cancel, $user_id_from_session);
                    if (!mysqli_stmt_execute($stmt_update)) {
                        $cancel_success = false;
                        error_log("Error updating order status to cancelled: " . mysqli_stmt_error($stmt_update));
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $cancel_success = false;
                    error_log("Error preparing cancel order status update: " . mysqli_error($conn));
                }

                if ($cancel_success) {
                    $sql_get_items = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
                    if ($stmt_get_items = mysqli_prepare($conn, $sql_get_items)) {
                        mysqli_stmt_bind_param($stmt_get_items, "i", $order_id_to_cancel);
                        mysqli_stmt_execute($stmt_get_items);
                        $result_items = mysqli_stmt_get_result($stmt_get_items);
                        
                        while ($item_row = mysqli_fetch_assoc($result_items)) {
                            $product_id_returned = $item_row['product_id'];
                            $quantity_returned = $item_row['quantity'];

                            $current_stock = 0;
                            $stmt_current_stock = mysqli_prepare($conn, "SELECT stock FROM products WHERE id = ?");
                            if ($stmt_current_stock) {
                                mysqli_stmt_bind_param($stmt_current_stock, "i", $product_id_returned);
                                mysqli_stmt_execute($stmt_current_stock);
                                mysqli_stmt_bind_result($stmt_current_stock, $current_stock);
                                mysqli_stmt_fetch($stmt_current_stock);
                                mysqli_stmt_close($stmt_current_stock);
                            }

                            $new_stock_value = $current_stock + $quantity_returned;
                            $sql_return_stock = "UPDATE products SET stock = ? WHERE id = ?";
                            if ($stmt_return_stock = mysqli_prepare($conn, $sql_return_stock)) {
                                mysqli_stmt_bind_param($stmt_return_stock, "ii", $new_stock_value, $product_id_returned);
                                if (!mysqli_stmt_execute($stmt_return_stock)) {
                                    $cancel_success = false;
                                    error_log("Error returning stock for product " . $product_id_returned . ": " . mysqli_stmt_error($stmt_return_stock));
                                    break;
                                }
                                mysqli_stmt_close($stmt_return_stock);
                            } else {
                                $cancel_success = false;
                                error_log("Error preparing return stock statement: " . mysqli_error($conn));
                                break;
                            }
                        }
                        mysqli_stmt_close($stmt_get_items);
                    } else {
                        $cancel_success = false;
                        error_log("Error preparing get order items for cancellation: " . mysqli_error($conn));
                    }
                }

                if ($cancel_success) {
                    mysqli_commit($conn);
                    $popup_message = "Pesanan #" . $order_id_to_cancel . " berhasil dibatalkan!";
                    $popup_status = "success";
                } else {
                    mysqli_rollback($conn);
                    $popup_message = "Gagal membatalkan pesanan #" . $order_id_to_cancel . ". Mohon coba lagi.";
                    $popup_status = "error";
                }
                mysqli_autocommit($conn, TRUE);

            } else {
                $popup_message = "Pesanan #" . $order_id_to_cancel . " tidak dapat dibatalkan. Mungkin sudah melewati batas waktu (1 jam) atau status tidak memungkinkan.";
                $popup_status = "error";
            }
        } else {
            $popup_message = "Pesanan tidak ditemukan atau Anda tidak memiliki izin untuk membatalkannya.";
            $popup_status = "error";
        }
    } else {
        $popup_message = "ID pesanan tidak valid untuk pembatalan.";
        $popup_status = "error";
    }
    header('Location: dashboard.php?page=orders&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}


// --- Handle Update Profile Action ---
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $user_id_to_update = $_SESSION['id'];
    $full_name_form = trim($_POST['full_name'] ?? '');
    $email_form = trim($_POST['email'] ?? '');
    $phone_number_form = trim($_POST['phone_number'] ?? '');
    $address_form = trim($_POST['address'] ?? '');

    $update_success = true;
    $update_errors = [];

    if (empty($full_name_form)) { $update_errors[] = "Nama Lengkap tidak boleh kosong."; }
    if (empty($email_form) || !filter_var($email_form, FILTER_VALIDATE_EMAIL)) { $update_errors[] = "Format Email tidak valid."; }

    if (empty($update_errors)) {
        $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        if ($stmt_check_email = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_check_email, "si", $email_form, $user_id_to_update);
            mysqli_stmt_execute($stmt_check_email);
            mysqli_stmt_store_result($stmt_check_email);
            if (mysqli_stmt_num_rows($stmt_check_email) > 0) {
                $update_errors[] = "Email ini sudah digunakan oleh akun lain.";
            }
            mysqli_stmt_close($stmt_check_email);
        } else {
            $update_errors[] = "Kesalahan sistem saat memverifikasi email.";
        }
    }

    if (!empty($update_errors)) {
        $popup_message = "Gagal memperbarui profil:\\n" . implode("\\n", $update_errors);
        $popup_status = "error";
        error_log("Profile update errors: " . $popup_message);
    } else {
        $sql_update_profile = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE id = ?";
        if ($stmt_update_profile = mysqli_prepare($conn, $sql_update_profile)) {
            mysqli_stmt_bind_param($stmt_update_profile, "ssssi", $full_name_form, $email_form, $phone_number_form, $address_form, $user_id_to_update);
            if (mysqli_stmt_execute($stmt_update_profile)) {
                $popup_message = "Profil Anda berhasil diperbarui!";
                $popup_status = "success";
                $_SESSION['username'] = $full_name_form; 
            } else {
                $popup_message = "Gagal memperbarui profil ke database. " . mysqli_stmt_error($stmt_update_profile);
                $popup_status = "error";
                error_log("Error updating profile in DB: " . mysqli_stmt_error($stmt_update_profile));
            }
            mysqli_stmt_close($stmt_update_profile);
        } else {
            $popup_message = "Kesalahan sistem saat menyiapkan pembaruan profil.";
            $popup_status = "error";
            error_log("Error preparing profile update statement: " . mysqli_error($conn));
        }
    }
    header('Location: dashboard.php?page=profile&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}

// --- Handle Send Message Action (NEW) ---
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0); // Seller User ID
    $store_id_msg = isset($_POST['store_id_msg']) ? intval($_POST['store_id_msg']) : NULL; // Store ID (can be NULL)
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message_content'] ?? '');

    if ($receiver_id <= 0 || empty($message_content)) {
        $popup_message = "Penerima atau isi pesan tidak valid.";
        $popup_status = "error";
    } else {
        // Basic validation: ensure receiver_id is a seller
        $is_receiver_seller = false;
        $stmt_check_role = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
        if ($stmt_check_role) {
            mysqli_stmt_bind_param($stmt_check_role, "i", $receiver_id);
            mysqli_stmt_execute($stmt_check_role);
            mysqli_stmt_bind_result($stmt_check_role, $role_check);
            if (mysqli_stmt_fetch($stmt_check_role) && $role_check == 'seller') {
                $is_receiver_seller = true;
            }
            mysqli_stmt_close($stmt_check_role);
        }

        if (!$is_receiver_seller) {
            $popup_message = "Penerima pesan bukan penjual yang valid.";
            $popup_status = "error";
        } else if ($receiver_id == $user_id) {
            $popup_message = "Anda tidak bisa mengirim pesan ke diri sendiri.";
            $popup_status = "error";
        } else {
            $sql_insert_message = "INSERT INTO messages (sender_id, receiver_id, store_id, subject, message) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_insert_message = mysqli_prepare($conn, $sql_insert_message)) {
                // If store_id_msg is 0 or not set, it should be NULL in DB
                $store_id_param = ($store_id_msg > 0) ? $store_id_msg : NULL; 
                mysqli_stmt_bind_param($stmt_insert_message, "iiiss", $user_id, $receiver_id, $store_id_param, $subject, $message_content);
                if (mysqli_stmt_execute($stmt_insert_message)) {
                    $popup_message = "Pesan berhasil dikirim!";
                    $popup_status = "success";
                } else {
                    $popup_message = "Gagal mengirim pesan: " . mysqli_stmt_error($stmt_insert_message);
                    $popup_status = "error";
                    error_log("Error sending message: " . mysqli_stmt_error($stmt_insert_message));
                }
                mysqli_stmt_close($stmt_insert_message);
            } else {
                $popup_message = "Terjadi kesalahan sistem saat menyiapkan pesan.";
                $popup_status = "error";
                error_log("Error preparing message insert statement: " . mysqli_error($conn));
            }
        }
    }
    header('Location: dashboard.php?page=chat&seller_id=' . urlencode($receiver_id) . '&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}


// --- Fetch Products from Database for all pages ---
$flowers_from_db = [];
$sql_products_all = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                 FROM products p
                 LEFT JOIN users u ON p.seller_id = u.id
                 WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                 ORDER BY p.name ASC";
$result_products_all = mysqli_query($conn, $sql_products_all);
if ($result_products_all) {
    while ($row = mysqli_fetch_assoc($result_products_all)) {
        $flowers_from_db[] = $row;
    }
}
$flowers_to_display = empty($flowers_from_db) ? $all_initial_products : $flowers_from_db;


// --- Fetch Featured Products (Only for home page) ---
$featured_products = [];
if ($page == 'home') { // Only fetch if on home page
    $sql_featured = "SELECT p.id, p.name, p.img, p.price, p.description, p.seller_id
                     FROM products p
                     LEFT JOIN users u ON p.seller_id = u.id
                     WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                     ORDER BY RAND() LIMIT 4";
    $result_featured = mysqli_query($conn, $sql_featured);
    if ($result_featured) {
        while ($row = mysqli_fetch_assoc($result_featured)) {
            $featured_products[] = $row;
        }
    }
}


// Check for popup messages from redirect
if (isset($_GET['popup_message']) && isset($_GET['popup_status'])) {
    $popup_message = urldecode($_GET['popup_message']);
    $popup_status = urldecode($_GET['popup_status']);
}

// All DB queries are now before HTML output.
// mysqli_close($conn) will be at the very end of the file.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <link rel="stylesheet" href="/PlantPals/css/dashboard_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya khusus untuk keranjang belanja */
        .cart-item {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 0;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fcfcfc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0;
            margin-right: 20px;
        }
        .cart-item-details {
            flex-grow: 1;
            text-align: left;
        }
        .cart-item-details h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: #3a5a20;
            font-size: 1.2rem;
        }
        .cart-item-details .price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #D60050;
            margin-bottom: 10px;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-item-actions input[type="number"] {
            width: 70px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 0;
            text-align: center;
            font-size: 1rem;
        }
        .cart-item-actions .remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 0;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9em;
        }
        .cart-item-actions .remove-btn:hover {
            background-color: #c82333;
        }
        .cart-summary {
            margin-top: 30px;
            border-top: 2px solid #e0e0e0;
            padding-top: 20px;
            text-align: right;
        }
        .cart-summary p {
            font-size: 1.4rem;
            font-weight: bold;
            color: #3a5a20;
            margin-bottom: 20px;
        }
        .cart-summary .checkout-btn {
            background-color: #000;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 0;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .cart-summary .checkout-btn:hover {
            background-color: #333;
        }
        .empty-cart-message {
            text-align: center;
            color: #777;
            font-size: 1.1em;
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 0;
            border: 1px dashed #bbb;
        }

        /* Gaya untuk tombol "Lihat Detail" dan "Tambah ke Keranjang" */
        .card-buttons-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 0 20px 20px;
        }
        .card-buttons-container .see-more-btn,
        .card-buttons-container .buy-button {
            flex: 1;
            width: auto;
            margin: 0;
            padding: 10px 15px;
            font-size: 0.95rem;
            border-radius: 0;
        }
        .card-buttons-container .see-more-btn {
            background-color: #333;
            color: white;
        }
        .card-buttons-container .see-more-btn:hover {
            background-color: #000;
        }
        .card-buttons-container .buy-button {
            background-color: #D60050;
            color: white;
        }
        .card-buttons-container .buy-button:hover {
            background-color: #A60040;
        }

        /* Penyesuaian untuk product-item-page di halaman products.php */
        .product-list-page {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            padding: 0 20px;
            width: 100%;
        }
        .product-list-page .product-item-page .card-buttons-container {
            flex-direction: column;
            gap: 10px;
            padding: 0 20px 20px;
        }
        .product-list-page .product-item-page .card-buttons-container .buy-button {
             width: 100%;
        }
        .product-list-page .product-item-page .card-buttons-container .see-more-btn {
            width: 100%;
        }


        /* Gaya baru untuk Banner Promosi */
        .promo-banner {
            background-color: #e0e0e0;
            border-radius: 0;
            padding: 30px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .promo-banner h3 {
            font-size: 2.2rem;
            color: #000;
            margin-bottom: 15px;
        }
        .promo-banner p {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 25px;
        }
        .promo-banner .btn-promo {
            background-color: #D60050;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 0;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-promo:hover {
            background-color: #A60040;
        }

        /* Gaya baru untuk Produk Unggulan */
        .featured-products-section {
            margin-bottom: 40px;
        }
        .featured-products-section .section-heading {
            font-size: 2rem;
            color: #000;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        .featured-products-section .section-heading::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background-color: #D60050;
            margin: 15px auto 0;
            border-radius: 0;
        }
        .featured-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        .featured-item.card {
            text-align: left;
        }
        .featured-item.card .card-content {
            padding-bottom: 0;
        }
        .featured-item.card .card-buttons-container {
             padding-top: 15px;
        }

        /* Gaya Pop-up Notifikasi */
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

        /* Responsive untuk pop-up */
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

        /* Gaya untuk tombol Batalkan Pesanan */
        .cancel-order-btn {
            background-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        .cancel-order-btn:hover {
            background-color: #c82333;
        }
        .order-summary-footer .status-badge {
            margin-right: 0;
        }
        @media (max-width: 768px) {
            .order-summary-footer .status-badge + .cancel-order-btn {
                margin-left: 0;
                margin-top: 10px;
            }
        }

        /* NEW: Chat Specific Styles (Buyer Dashboard) */
        .messages-page-layout {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .conversation-list-panel {
            flex-basis: 250px; /* Lebar panel kiri */
            flex-shrink: 0;
            max-height: 700px; /* Tinggi maksimum untuk scroll */
            overflow-y: auto;
            padding: 20px;
            background: #FFFFFF;
            border: 1px solid #ccc;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .conversation-list-panel h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #000;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .conversation-item {
            display: block;
            padding: 10px 15px;
            background-color: #fff;
            border: 1px solid #eee;
            text-decoration: none;
            color: #333;
            position: relative;
            margin-bottom: 5px;
        }
        .conversation-item.active {
            background-color: #f0f0f0 !important; /* Highlight active conversation */
            border-left: 3px solid #D60050;
        }
        .conversation-item:hover {
            background-color: #f5f5f5;
        }
        .conversation-item strong {
            font-size: 1rem;
        }
        .conversation-item span {
            font-size: 0.85em;
            color: #777;
            float: right;
        }
        .conversation-item .unread-count {
            background-color: #D60050;
            color: white;
            padding: 2px 7px;
            font-size: 0.75em;
            border-radius: 50%;
            margin-left: 10px;
        }

        .chat-area-panel {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: #FFFFFF;
            border: 1px solid #ccc;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .chat-area-panel h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #000;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .chat-messages-display {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px; /* Gap between message bubbles */
        }
        .chat-message-item {
            padding: 10px 15px;
            max-width: 70%; /* Limit message bubble width */
            border: 1px solid transparent; /* default border */
            word-wrap: break-word; /* Ensure long words break */
            position: relative; /* For triangle pointer */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Subtle shadow for bubbles */
            min-width: 120px; /* Ensure bubble is not too small */
        }
        /* Triangle pointer for chat bubbles */
        .chat-message-item::before {
            content: '';
            position: absolute;
            top: 10px;
            border: 10px solid transparent;
        }

        .chat-message-item.sent {
            align-self: flex-end;
            background-color: #D60050; /* Sent messages are pink */
            color: white;
            border-color: #D60050;
            margin-right: 10px; /* Space from edge */
        }
        .chat-message-item.sent::before {
            left: 100%; /* Position on the right */
            border-left-color: #D60050; /* Color matches bubble */
        }

        .chat-message-item.received {
            align-self: flex-start;
            background-color: #000000; /* Received messages are black */
            color: white;
            border-color: #000000;
            margin-left: 10px; /* Space from edge */
        }
        .chat-message-item.received::before {
            right: 100%; /* Position on the left */
            border-right-color: #000000; /* Color matches bubble */
        }

        .message-sender {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9em;
            color: rgba(255,255,255,0.8);
        }
        .chat-message-item.sent .message-sender {
             color: rgba(255,255,255,0.8);
        }
        .message-content {
            font-size: 1rem;
        }
        .message-date {
            font-size: 0.8em;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
            text-align: right;
        }
        .no-messages {
            text-align: center;
            color: #777;
            font-size: 1.1em;
            padding: 20px;
        }

        /* Style for form inputs within chat panel (send message form) */
        .reply-form-container form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .reply-form-container textarea {
            padding: 10px;
            border: 1px solid #ccc;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 60px; /* Adjusted min height */
            box-sizing: border-box;
        }
        .reply-form-container textarea:focus {
            border-color: #D60050;
            outline: none;
            box-shadow: 0 0 0 2px rgba(214,0,80,0.1);
        }
        .reply-form-container .btn {
            background-color: #D60050; /* Solid pink */
            color: white;
            padding: 8px 15px; /* Smaller padding */
            border: none;
            cursor: pointer;
            font-size: 0.95rem; /* Smaller font */
            align-self: flex-end; /* Align to right */
        }
        .reply-form-container .btn:hover {
            background-color: #A60040;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .messages-page-layout {
                flex-direction: column;
                gap: 15px;
            }
            .conversation-list-panel {
                flex-basis: auto;
                max-height: 300px; /* Shorter on mobile */
            }
            .chat-message-item {
                max-width: 90%; /* Wider bubbles on small screens */
            }
        }
        @media (max-width: 576px) {
            .conversation-list-panel {
                padding: 15px;
            }
            .conversation-list-panel h3 {
                font-size: 1.3rem;
            }
            .conversation-item {
                padding: 8px 12px;
                font-size: 0.95rem;
            }
            .chat-area-panel {
                padding: 15px;
            }
            .chat-area-panel h3 {
                font-size: 1.3rem;
            }
            .chat-message-item {
                font-size: 0.9rem;
            }
            .message-sender, .message-date {
                font-size: 0.75em;
            }
            .reply-form-container textarea {
                min-height: 50px;
                font-size: 0.9rem;
            }
            .reply-form-container .btn {
                padding: 6px 12px;
                font-size: 0.85rem;
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

    <div class="container">
        <nav class="sidebar">
            <a href="dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>"><i class="fas fa-seedling"></i> Produk</a>
            <a href="dashboard.php?page=cart" class="<?php echo ($page == 'cart') ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Keranjang (<?php echo count($_SESSION['cart']); ?>)</a>
            <a href="dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>"><i class="fas fa-box-open"></i> Pesanan Saya</a>
            <a href="dashboard.php?page=chat" class="<?php echo ($page == 'chat') ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Pesan Saya</a>
            <a href="dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> Profil</a>
            <a href="/PlantPals/dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Kontak</a>
        </nav>

        <main class="content">
            <?php
            // Fetch Products from Database for all pages (moved here to avoid re-fetching in each block)
            $flowers_from_db = [];
            $sql_products_all = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id
                             FROM products p
                             LEFT JOIN users u ON p.seller_id = u.id
                             WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                             ORDER BY p.name ASC";
            $result_products_all = mysqli_query($conn, $sql_products_all);
            if ($result_products_all) {
                while ($row = mysqli_fetch_assoc($result_products_all)) {
                    $flowers_from_db[] = $row;
                }
            }
            $flowers_to_display = empty($flowers_from_db) ? $all_initial_products : $flowers_from_db;

            // Fetch Featured Products (Only for home page)
            $featured_products = [];
            if ($page == 'home') { // Only fetch if on home page
                $sql_featured = "SELECT p.id, p.name, p.img, p.price, p.description, p.seller_id
                                 FROM products p
                                 LEFT JOIN users u ON p.seller_id = u.id
                                 WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                                 ORDER BY RAND() LIMIT 4";
                $result_featured = mysqli_query($conn, $sql_featured);
                if ($result_featured) {
                    while ($row = mysqli_fetch_assoc($result_featured)) {
                        $featured_products[] = $row;
                    }
                }
            }

            if ($page == 'home') {
                ?>
                <h2>Selamat Datang di PlantPals!</h2>
                <form class="search-bar" action="dashboard.php" method="get">
                    <input type="hidden" name="page" value="home" />
                    <input type="text" id="searchInput" name="q" placeholder="Cari tanaman hias atau kebutuhan kebun..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>

                <div class="promo-banner">
                    <h3>Diskon Spesial!</h3>
                    <p>Dapatkan Potongan Harga 20% untuk semua tanaman hias di bulan ini! Waktu Terbatas!</p>
                    <a href="dashboard.php?page=products" class="btn-promo"><i class="fas fa-tags"></i> Lihat Promosi Sekarang</a>
                </div>

                <?php if (!empty($featured_products)): ?>
                <div class="featured-products-section">
                    <h2 class="section-heading">Produk Unggulan Pilihan Kami</h2>
                    <div class="featured-products-grid grid"> <?php foreach ($featured_products as $f_product): ?>
                            <div class="featured-item card">
                                <img src="<?php echo htmlspecialchars($f_product['img']); ?>" alt="<?php echo htmlspecialchars($f_product['name']); ?>" />
                                <div class="card-content">
                                    <h3><?php echo htmlspecialchars($f_product['name']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($f_product['description'] ?? '', 0, 70)); ?>...</p>
                                    <p class="price">Rp <?php echo number_format($f_product['price'], 0, ',', '.'); ?></p>
                                </div>
                                <div class="card-buttons-container">
                                    <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $f_product['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                    <form action="dashboard.php" method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($f_product['id']); ?>">
                                        <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Add</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <h2 class="section-heading">Semua Produk</h2> <div id="flowerGrid" class="grid">
                <?php
                $filtered_flowers_for_display = [];
                $keyword = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

                if (!empty($keyword)) {
                    foreach ($flowers_to_display as $flower) {
                        if (
                            stripos($flower['name'], $keyword) !== false ||
                            stripos($flower['description'] ?? '', $keyword) !== false ||
                            stripos($flower['scientific_name'] ?? $flower['scientific'] ?? '', $keyword) !== false ||
                            stripos($flower['family'] ?? '', $keyword) !== false
                        ) {
                            $filtered_flowers_for_display[] = $flower;
                        }
                    }
                } else {
                    $filtered_flowers_for_display = $flowers_to_display;
                }

                if (empty($filtered_flowers_for_display)) {
                    echo "<p class='no-results'>Tidak ada hasil untuk pencarian Anda.</p>";
                } else {
                    foreach ($filtered_flowers_for_display as $flower) {
                        $card_id = htmlspecialchars($flower['id'] ?? 'fallback_' . uniqid());
                        ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($flower['name']); ?></h3>
                                <p><strong>Nama Ilmiah:</strong> <?php echo htmlspecialchars($flower['scientific_name'] ?? $flower['scientific'] ?? 'N/A'); ?></p>
                                <p><strong>Familia:</strong> <?php echo htmlspecialchars($flower['family'] ?? 'N/A'); ?></p>
                                <p><?php echo htmlspecialchars(substr($flower['description'] ?? '', 0, 80)); ?>...</p>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                            </div>
                            <div class="card-buttons-container">
                                <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
                </div> <?php
            } elseif ($page == 'products') {
                ?>
                <div class="page-content-panel">
                    <h2>Katalog Produk Kami</h2>
                    <p class="page-description">Temukan berbagai tanaman hias pilihan dari penjual terpercaya!</p>
                    <div class="product-list-page">
                        <?php foreach ($flowers_to_display as $flower):
                            $selling_store = null;
                            if (isset($flower['seller_id']) && isset($stores_by_seller_id[$flower['seller_id']])) {
                                $selling_store = $stores_by_seller_id[$flower['seller_id']][0] ?? null;
                            }
                            $store_link = "#";
                            $store_name_display = "Toko Tidak Dikenal";
                            $store_id_string_for_order = "";
                            $store_name_full_for_order = "";

                            if ($selling_store) {
                                $store_id_string_for_order = htmlspecialchars($selling_store['store_id_string'] ?? '');
                                if (!empty($store_id_string_for_order)) {
                                    $store_link = "store_profile_buyer.php?store_id_string=" . urlencode($store_id_string_for_order);
                                }
                                $store_name_display = htmlspecialchars($selling_store['name']);
                                $store_name_full_for_order = htmlspecialchars($selling_store['name'] . " - (" . ($selling_store['address'] ?? 'Alamat Tidak Diketahui') . ")");
                            }
                        ?>
                        <div class="product-item-page card">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h4><?php echo htmlspecialchars($flower['name']); ?></h4>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                                <div class="store-info-display">
                                    <span class="label"><i class="fas fa-store"></i> Dijual oleh:</span>
                                    <?php if ($selling_store && !empty($store_link) && $store_link !== "#"): ?>
                                        <a href="<?php echo $store_link; ?>" class="store-name-link">
                                            <?php echo $store_name_display; ?>
                                            <?php if (isset($selling_store['address']) && !empty($selling_store['address'])) echo " - (" . htmlspecialchars($selling_store['address']) . ")"; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="store-name-link"><?php echo $store_name_display; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-buttons-container">
                                <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Tambah ke Keranjang</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            } elseif ($page == 'cart') {
                $cart_items = $_SESSION['cart'] ?? [];
                $total_cart_amount = 0;
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-shopping-cart"></i> Keranjang Belanja Anda</h2>
                    <?php if (empty($cart_items)): ?>
                        <div class="empty-cart-message">
                            <i class="fas fa-box-open fa-3x"></i>
                            <p>Keranjang Anda masih kosong.</p>
                            <a href="dashboard.php?page=products" class="btn-primary" style="margin-top: 20px;">Mulai Belanja</a>
                        </div>
                    <?php else: 
                        $total_cart_amount = 0; 
                    ?>
                        <div class="cart-items-list">
                            <?php foreach ($cart_items as $product_id => $item):
                                $subtotal = $item['price'] * $item['quantity'];
                                $total_cart_amount += $subtotal;
                            ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                                        <p>Dari: <?php echo htmlspecialchars($item['store_name']); ?></p>
                                    </div>
                                    <div class="cart-item-actions">
                                        <form action="dashboard.php" method="post" style="display:flex; align-items:center; gap: 5px;">
                                            <input type="hidden" name="action" value="update_cart_quantity">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                            <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" onchange="this.form.submit()">
                                        </form>
                                        <form action="dashboard.php" method="post" style="margin:0;">
                                            <input type="hidden" name="action" value="remove_from_cart">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                            <button type="submit" class="remove-btn"><i class="fas fa-trash"></i> Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="cart-summary">
                            <p>Total Keranjang: Rp <?php echo number_format($total_cart_amount, 0, ',', '.'); ?></p>
                            <form action="order_form.php" method="post">
                                <input type="hidden" name="action" value="checkout_cart">
                                <?php foreach ($cart_items as $product_id => $item): ?>
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][id]" value="<?php echo htmlspecialchars($item['id']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][name]" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][price]" value="<?php echo htmlspecialchars($item['price']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_id_string]" value="<?php echo htmlspecialchars($item['store_id_string']); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_name]" value="<?php echo htmlspecialchars($item['store_name']); ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="checkout-btn"><i class="fas fa-money-check-alt"></i> Lanjutkan ke Pembayaran</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'profile') {
                // Fetch user data for profile page
                $user_data = [];
                $sql_user = "SELECT id, username, email, full_name, phone_number, address, created_at, role FROM users WHERE id = ?";
                if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
                    mysqli_stmt_bind_param($stmt_user, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_user);
                    $result_user = mysqli_stmt_get_result($stmt_user);
                    $user_data = mysqli_fetch_assoc($result_user);
                    mysqli_stmt_close($stmt_user);
                }
                $is_editing_profile = (isset($_GET['action']) && $_GET['action'] == 'edit_profile');
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-user-circle"></i> Profil Pengguna</h2>
                    <p class="page-description">Kelola informasi akun Anda di sini.</p>
                    
                    <?php if (!$is_editing_profile): ?>
                        <div class="profile-info">
                            <p><strong><i class="fas fa-user"></i> Username:</strong> <?php echo htmlspecialchars($user_data['username'] ?? $username); ?></p>
                            <p><strong><i class="fas fa-at"></i> Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? 'N/A'); ?></p>
                            <p><strong><i class="fas fa-id-card"></i> Nama Lengkap:</strong> <?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A'); ?></p>
                            <p><strong><i class="fas fa-phone"></i> Nomor Telepon:</strong> <?php echo htmlspecialchars($user_data['phone_number'] ?? 'N/A'); ?></p>
                            <p><strong><i class="fas fa-map-marker-alt"></i> Alamat:</strong> <?php echo htmlspecialchars($user_data['address'] ?? 'N/A'); ?></p>
                            <p><strong><i class="fas fa-calendar-alt"></i> Bergabung Sejak:</strong> <?php echo htmlspecialchars(date('d F Y', strtotime($user_data['created_at'] ?? 'now'))); ?></p>
                            <p><strong><i class="fas fa-user-tag"></i> Status Akun:</strong> Aktif (<?php echo htmlspecialchars($user_data['role'] ?? 'buyer'); ?>)</p>
                            <a href="dashboard.php?page=profile&action=edit_profile" class="profile-info-btn"><i class="fas fa-edit"></i> Edit Profil</a>
                        </div>
                    <?php else: ?>
                        <div class="profile-edit-form-container card-panel">
                            <h3><i class="fas fa-user-edit"></i> Edit Profil Anda</h3>
                            <form action="dashboard.php" method="post" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['id']); ?>">
                                
                                <div class="form-group">
                                    <label for="full_name" class="profile-form-label"><i class="fas fa-id-card"></i> Nama Lengkap:</label>
                                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required class="profile-form-input">
                                </div>
                                <div class="form-group">
                                    <label for="email" class="profile-form-label"><i class="fas fa-at"></i> Email:</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required class="profile-form-input">
                                </div>
                                <div class="form-group">
                                    <label for="phone_number" class="profile-form-label"><i class="fas fa-phone"></i> Nomor Telepon:</label>
                                    <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>" class="profile-form-input">
                                </div>
                                <div class="form-group">
                                    <label for="address" class="profile-form-label"><i class="fas fa-map-marker-alt"></i> Alamat:</label>
                                    <textarea id="address" name="address" rows="3" class="profile-form-textarea"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-action-btns profile-form-action-btns">
                                    <a href="dashboard.php?page=profile" class="cancel-btn btn-link"><i class="fas fa-times-circle"></i> Batal</a>
                                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'orders') {
                $user_orders = [];
                $sql_orders = "SELECT id, user_id, total_amount, order_status, payment_method, delivery_address, customer_name, customer_phone, customer_email, notes, order_date FROM orders WHERE user_id = ? ORDER BY order_date DESC";
                if ($stmt_orders = mysqli_prepare($conn, $sql_orders)) {
                    mysqli_stmt_bind_param($stmt_orders, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_orders);
                    $result_orders = mysqli_stmt_get_result($stmt_orders);
                    while ($row = mysqli_fetch_assoc($result_orders)) {
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
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-box-open"></i> Pesanan Anda</h2>
                    <p class="page-description">Berikut adalah daftar pesanan yang telah Anda lakukan.</p>
                    <?php if (empty($user_orders)): ?>
                        <p class="no-results" style="margin-top: 20px;">Tidak ada pesanan yang ditemukan.</p>
                    <?php else: ?>
                        <ul class="order-list">
                            <?php foreach ($user_orders as $order): ?>
                                <li>
                                    <div class="order-header">
                                        <span><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order['id']); ?></span>
                                        <span><strong>Tanggal:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order['order_date']))); ?></span>
                                    </div>
                                    <div class="order-summary-footer">
                                        <span><strong>Total:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                        <span><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'))); ?></span>
                                        <span><strong>Status:</strong> <span class="status-badge <?php echo htmlspecialchars($order['order_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span>
                                        
                                        <?php
                                        $order_timestamp = strtotime($order['order_date']);
                                        $current_timestamp = time();
                                        $one_hour_limit = 60 * 60;
                                        $is_cancel_enabled = (($current_timestamp - $order_timestamp) <= $one_hour_limit && 
                                            ($order['order_status'] == 'pending' || $order['order_status'] == 'processing'));
                                        if ($is_cancel_enabled):
                                        ?>
                                            <form action="dashboard.php" method="post" style="display:inline-block; margin-left: 10px;">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                                <button type="submit" class="cancel-order-btn" onclick="return confirm('Yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.');"><i class="fas fa-times-circle"></i> Batalkan</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        </span>
                                    </div>
                                    <div class="order-items-detail">
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
            } elseif ($page == 'chat') { // NEW CHAT PAGE
                $target_seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
                $target_store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
                $default_subject = isset($_GET['subject']) ? htmlspecialchars($_GET['subject']) : '';
                $default_message_content = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

                $seller_username_target = 'Pilih Penjual';
                $store_name_target = 'Tidak Dikenal';

                // Fetch unique sellers who sent messages to/received messages from this buyer
                $conversation_sellers = [];
                $sql_conversation_sellers = "SELECT DISTINCT u.id, u.username, s.id as store_db_id, s.name as store_name
                                            FROM messages m
                                            JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
                                            LEFT JOIN stores s ON u.id = s.seller_user_id
                                            WHERE (m.sender_id = ? AND u.role = 'seller') OR (m.receiver_id = ? AND u.role = 'seller')
                                            ORDER BY m.sent_at DESC";
                if ($stmt_conv_sellers = mysqli_prepare($conn, $sql_conversation_sellers)) {
                    mysqli_stmt_bind_param($stmt_conv_sellers, "ii", $user_id, $user_id);
                    mysqli_stmt_execute($stmt_conv_sellers);
                    $result_conv_sellers = mysqli_stmt_get_result($stmt_conv_sellers);
                    $seen_seller_ids = []; // To ensure unique sellers
                    while ($row_conv_seller = mysqli_fetch_assoc($result_conv_sellers)) {
                        if (!in_array($row_conv_seller['id'], $seen_seller_ids)) {
                            $conversation_sellers[] = $row_conv_seller;
                            $seen_seller_ids[] = $row_conv_seller['id'];
                        }
                    }
                    mysqli_stmt_close($stmt_conv_sellers);
                }

                // If a conversation seller is selected, fetch the messages for that conversation
                $messages = [];
                if ($target_seller_id > 0) {
                    // Get username and store name of the selected target seller
                    $stmt_get_seller_info = mysqli_prepare($conn, "SELECT u.username, s.name as store_name FROM users u LEFT JOIN stores s ON u.id = s.seller_user_id WHERE u.id = ? AND u.role = 'seller'");
                    if ($stmt_get_seller_info) {
                        mysqli_stmt_bind_param($stmt_get_seller_info, "i", $target_seller_id);
                        mysqli_stmt_execute($stmt_get_seller_info);
                        mysqli_stmt_bind_result($stmt_get_seller_info, $uname, $sname);
                        if (mysqli_stmt_fetch($stmt_get_seller_info)) {
                            $seller_username_target = htmlspecialchars($uname);
                            $store_name_target = htmlspecialchars($sname ?? 'Toko Tidak Dikenal');
                        }
                        mysqli_stmt_close($stmt_get_seller_info);
                    }

                    // Fetch messages between current user and target seller
                    $sql_conversation_messages = "SELECT m.*, s.username as sender_username_display, r.username as receiver_username_display, st.name as related_store_name
                                                FROM messages m
                                                JOIN users s ON m.sender_id = s.id
                                                JOIN users r ON m.receiver_id = r.id
                                                LEFT JOIN stores st ON m.store_id = st.id
                                                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                                                ORDER BY m.sent_at ASC";
                    if ($stmt_conv_messages = mysqli_prepare($conn, $sql_conversation_messages)) {
                        mysqli_stmt_bind_param($stmt_conv_messages, "iiii", $user_id, $target_seller_id, $target_seller_id, $user_id);
                        mysqli_stmt_execute($stmt_conv_messages);
                        $result_conv_messages = mysqli_stmt_get_result($stmt_conv_messages);
                        while ($row_msg = mysqli_fetch_assoc($result_conv_messages)) {
                            $messages[] = $row_msg;
                            // Update related store ID if found in any message
                            if ($row_msg['store_id']) {
                                $target_store_id = $row_msg['store_id'];
                            }
                        }
                        mysqli_stmt_close($stmt_conv_messages);
                    }
                }
            ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-comments"></i> Pesan Saya</h2>
                    <p class="page-description">Kirim pesan kepada penjual atau lihat riwayat percakapan Anda.</p>

                    <div class="messages-page-layout">
                        <div class="conversation-list-panel card-panel">
                            <h3>Percakapan</h3>
                            <?php if (empty($conversation_sellers)): ?>
                                <p style="color: #777;">Belum ada percakapan dengan penjual.</p>
                            <?php else: ?>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($conversation_sellers as $seller_conv):
                                        // Count unread messages for this seller (messages FROM seller to buyer)
                                        $unread_count = 0;
                                        $temp_conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                                        $stmt_unread = mysqli_prepare($temp_conn, "SELECT COUNT(id) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                                        if ($stmt_unread) {
                                            mysqli_stmt_bind_param($stmt_unread, "ii", $seller_conv['id'], $user_id);
                                            mysqli_stmt_execute($stmt_unread);
                                            mysqli_stmt_bind_result($stmt_unread, $count);
                                            mysqli_stmt_fetch($stmt_unread);
                                            $unread_count = $count;
                                            mysqli_stmt_close($stmt_unread);
                                        }
                                        mysqli_close($temp_conn);
                                    ?>
                                        <li style="margin-bottom: 5px;">
                                            <a href="dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($seller_conv['id']); ?>&store_id=<?php echo htmlspecialchars($seller_conv['store_db_id'] ?? 0); ?>" 
                                               class="conversation-item <?php echo ($seller_conv['id'] == $target_seller_id) ? 'active' : ''; ?>">
                                                <strong><?php echo htmlspecialchars($seller_conv['username']); ?> (<?php echo htmlspecialchars($seller_conv['store_name']); ?>)</strong>
                                                <?php if ($unread_count > 0): ?>
                                                    <span class="unread-count"><?php echo $unread_count; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div class="chat-area-panel card-panel">
                            <?php if ($target_seller_id > 0): ?>
                                <h3>Percakapan dengan <?php echo $seller_username_target; ?> (<?php echo $store_name_target; ?>)</h3>
                                
                                <div class="chat-messages-display">
                                    <?php if (empty($messages)): ?>
                                        <p class="no-messages">Belum ada pesan dalam percakapan ini.</p>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="chat-message-item <?php echo ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                                <span class="message-sender">
                                                    <?php echo ($msg['sender_id'] == $user_id) ? 'Anda' : htmlspecialchars($msg['sender_username_display']); ?> 
                                                    <?php if ($msg['related_store_name']): ?>
                                                        (Toko: <?php echo htmlspecialchars($msg['related_store_name']); ?>)
                                                    <?php endif; ?>
                                                </span>
                                                <p class="message-content"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                                <span class="message-date"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($msg['sent_at']))); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="reply-form-container">
                                    <form action="dashboard.php" method="post">
                                        <input type="hidden" name="action" value="send_message">
                                        <input type="hidden" name="receiver_id" value="<?php echo htmlspecialchars($target_seller_id); ?>">
                                        <input type="hidden" name="store_id_msg" value="<?php echo htmlspecialchars($target_store_id); ?>">
                                        <input type="hidden" name="subject" value="Balasan dari Pembeli">
                                        
                                        <textarea name="message_content" rows="3" placeholder="Tulis pesan Anda di sini..." required></textarea>
                                        <button type="submit" class="btn-primary">Kirim Pesan</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #777;">Pilih percakapan dari daftar di sebelah kiri atau kirim pesan baru.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php
            } elseif ($page == 'contact') {
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-envelope-open-text"></i> Hubungi Kami</h2>
                    <p class="page-description">Kami siap membantu Anda. Silakan hubungi kami melalui informasi di bawah ini:</p>
                    <div class="contact-info">
                        <p><i class="fas fa-at"></i> <strong>Email:</strong> info@plantpals.com</p>
                        <p><i class="fas fa-phone-alt"></i> <strong>Telepon:</strong> +62 812-3456-7890</p>
                        <p><i class="fas fa-map-marker-alt"></i> <strong>Alamat:</strong> Jl. Bunga Indah No. 123, Denpasar, Bali, Indonesia</p>
                    </div>
                    <h3 class="section-sub-title">Form Kontak</h3>
                    <form class="contact-form">
                        <div class="form-group">
                            <label for="contactName"><i class="fas fa-user"></i> Nama Anda:</label>
                            <input type="text" id="contactName" placeholder="Nama Lengkap Anda" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="contactEmail"><i class="fas fa-envelope"></i> Email Anda:</label>
                            <input type="email" id="contactEmail" placeholder="email@contoh.com" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="contactMessage"><i class="fas fa-comment-dots"></i> Pesan Anda:</label>
                            <textarea id="contactMessage" placeholder="Tulis pesan Anda di sini..." name="message" rows="6"></textarea>
                        </div>
                        <button type="submit" class="submit-button">
                            <i class="fas fa-paper-plane"></i> Kirim Pesan
                        </button>
                    </form>
                </div>
                <?php
            } else {
                ?>
                <div class="page-content-panel">
                    <h2><i class="fas fa-exclamation-triangle"></i> Halaman Tidak Ditemukan</h2>
                    <p class="no-results">Halaman yang Anda cari tidak tersedia. Silakan kembali ke <a href="dashboard.php?page=home">Home</a>.</p>
                </div>
                <?php
            }
            ?>
        </main>
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
<?php
// --- CLOSE DATABASE CONNECTION AT THE VERY END OF dashboard.php ---
mysqli_close($conn);
?>