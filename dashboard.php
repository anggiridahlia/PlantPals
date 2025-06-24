<?php

session_start();

//MASTIKAN SETUP DAN KONDISI LOGIN

// Mengaktifkan tampilan error untuk debugging. Hapus atau set ke 0 untuk produksi.
ini_set('display_errors', 0); // Ubah dari 1 menjadi 0 untuk menyembunyikan error di browser
ini_set('display_startup_errors', 0); // Ubah dari 1 menjadi 0
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
$order_filter_status = isset($_GET['order_status']) ? htmlspecialchars($_GET['order_status']) : 'all'; // New: Filter for orders page

include 'data.php'; // For initial product fallback data
require_once 'config.php'; // Database connection, now opened once.

//MASTIKAN SETUP DAN KONDISI LOGIN



// Variabel untuk pesan pop-up
$popup_message = "";
$popup_status = ""; // 'success' or 'error'



// MENGAMBIL DATA TOKO DARI DATABASE
// --- Fetch Stores from Database and Organize by seller_user_id ---
$stores_by_seller_id = [];
// Select store's actual ID (s.id) to pass to chat
$sql_stores = "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, s.followers_count FROM stores s JOIN users u ON s.seller_user_id = u.id WHERE u.role = 'seller' ORDER BY s.name ASC";
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
// MENGAMBIL DATA TOKO DARI DATABASE



// Fallback jika tidak ada toko yang terhubung ke seller di DB
// Pastikan ID default seller ada di array stores_by_seller_id
if (empty($stores_by_seller_id) && isset($DEFAULT_FALLBACK_SELLER_ID)) {
    // If no stores in DB, manually add a fallback store associated with DEFAULT_FALLBACK_SELLER_ID
    $stores_by_seller_id[$DEFAULT_FALLBACK_SELLER_ID] = [
        ["id" => 1, "store_id_string" => "toko_bunga_asri_default", "name" => "Toko Bunga Sejuk Asri", "address" => "Jl. Contoh No. 123", "seller_user_id" => $DEFAULT_FALLBACK_SELLER_ID, "followers_count" => 0],
    ];
}



# MENANGANI PENAMBAHAN PRODUK KE KERANJANG
// --- Handle Add to Cart Action ---
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = 1;

    if ($product_id) {
        $sql_product = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id, c.name as category_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.id = ?";
        
        if ($stmt_product = mysqli_prepare($conn, $sql_product)) {
            mysqli_stmt_bind_param($stmt_product, "i", $product_id);
            mysqli_stmt_execute($stmt_product);
            $result_product = mysqli_stmt_get_result($stmt_product);
            $product_details = mysqli_fetch_assoc($result_product);
            mysqli_stmt_close($stmt_product);

            if ($product_details) {
                // Fetch store details for the product
                $selling_store_for_cart = null;
                // Use the already fetched $stores_by_seller_id
                if (isset($product_details['seller_id']) && isset($stores_by_seller_id[$product_details['seller_id']])) {
                    $selling_store_for_cart = $stores_by_seller_id[$product_details['seller_id']][0] ?? null;
                }

                $store_id_for_cart = $selling_store_for_cart['id'] ?? 'N/A'; // Use actual store_id (int) not store_id_string
                $store_name_for_cart = $selling_store_for_cart['name'] ?? 'Toko Tidak Dikenal';
                if ($selling_store_for_cart && ($selling_store_for_cart['address'] ?? '') !== '') { // Perbaikan di sini
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
                            'store_id' => $store_id_for_cart, // Pass actual store_id (int)
                            'store_name' => $store_name_for_cart,
                            'quantity' => $quantity,
                            'category' => $product_details['category_name'] ?? 'Lain-lain', // Use category_name
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
    header('Location: dashboard.php?page=' . $page . '&popup_message=' . urlencode($popup_message) . '&popup_status=' . urlencode($popup_status));
    exit();
}
# MENANGANI PENAMBAHAN PRODUK KE KERANJANG
# MENANGANI PENAMBAHAN PRODUK KE KERANJANG






# MENANGANI UPDATE PRODUK KE KERANJANG
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
                $_SESSION['cart'][$product_id]['quantity'] = $current_db_stock; // Set to max available stock
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
# MENANGANI UPDATE PRODUK KE KERANJANG
# MENANGANI UPDATE PRODUK KE KERANJANG



#  MENANGANI HAPUS PRODUK DI KERANJANG
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
# MENANGANI HAPUS PRODUK DI KERANJANG





// MENANGANI CANCEL ORDER
// MENANGANI CANCEL ORDER
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
                                    error_log("Error returning stock for product " . mysqli_stmt_error($stmt_return_stock));
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
# MENANGANI CANCEL ORDER
// MENANGANI CANCEL ORDER




// UPDATE PROFIL
// UPDATE PROFIL
// --- Handle Update Profile Action ---
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $user_id_to_update = $_SESSION['id'];
    $full_name_form = trim($_POST['full_name'] ?? '');
    $email_form = trim($_POST['email'] ?? '');
    $phone_number_form = trim($_POST['phone_number'] ?? '');
    $address_form = trim($_POST['address'] ?? '');

    $update_success = true;
    $update_errors = [];

    // Basic validation
    if (empty($full_name_form)) { $update_errors[] = "Nama Lengkap tidak boleh kosong."; }
    if (empty($email_form) || !filter_var($email_form, FILTER_VALIDATE_EMAIL)) { $update_errors[] = "Format Email tidak valid."; }
    // Phone and address can be empty

    // Check if email already exists for another user
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
                // Update session username (if using full_name as display username)
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
// UPDATE PROFIL
// UPDATE PROFIL




# MENANGNANI PENGIRIMAN PESAN
# MENANGNANI PENGIRIMAN PESAN
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
# MENANGNANI PENGIRIMAN PESAN
# MENANGNANI PENGIRIMAN PESAN




# MENGAMBIL SEMUA DAFTAR PRODUK UNTUK TAMPILAN HALAMAN
# MENGAMBIL SEMUA DAFTAR PRODUK UNTUK TAMPILAN HALAMAN
// --- Fetch Products from Database for all pages ---
$flowers_from_db = [];
// NEW: Select 'category_name' from products joined with categories
$sql_products_all = "SELECT p.id, p.name, p.img, p.scientific_name, p.family, p.description, p.habitat, p.care_instructions, p.unique_fact, p.price, p.stock, p.seller_id, c.name as category_name
                 FROM products p
                 LEFT JOIN users u ON p.seller_id = u.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                 ORDER BY p.name ASC";
$result_products_all = mysqli_query($conn, $sql_products_all);
if ($result_products_all) {
    while ($row = mysqli_fetch_assoc($result_products_all)) {
        // If product from DB has no category_name (category_id is NULL or invalid), assign 'Lain-lain'
        if (empty($row['category_name'])) {
            $row['category_name'] = 'Lain-lain';
        }
        $flowers_from_db[] = $row;
    }
}
/* No direct modification to the original logic of data.php, just ensuring it's loaded before $flowers_to_display is populated */
// Fallback for initial data from data.php if no products in DB
// Ensure fallback products also have category_name for display and filtering
$flowers_to_display = [];
if (empty($flowers_from_db)) {
    // Populate with data from data.php and ensure 'category_name' is set
    foreach ($all_initial_products as $p_fallback) {
        $p_fallback['category_name'] = 'Lain-lain'; // Default if not found
        // Attempt to map based on 'category_id' from data.php if it exists
        // This requires 'data.php' to have 'category_id' in its arrays
        if (isset($p_fallback['category_id'])) {
            $found_category_name = null;
            $stmt_get_cat_name = mysqli_prepare($conn, "SELECT name FROM categories WHERE id = ?");
            if ($stmt_get_cat_name) {
                mysqli_stmt_bind_param($stmt_get_cat_name, "i", $p_fallback['category_id']);
                mysqli_stmt_execute($stmt_get_cat_name);
                mysqli_stmt_bind_result($stmt_get_cat_name, $cat_name);
                if (mysqli_stmt_fetch($stmt_get_cat_name)) {
                    $found_category_name = $cat_name;
                }
                mysqli_stmt_close($stmt_get_cat_name);
            }
            if ($found_category_name) {
                $p_fallback['category_name'] = $found_category_name;
            }
        }
        $flowers_to_display[] = $p_fallback;
    }
} else {
    $flowers_to_display = $flowers_from_db;
}
# MENGAMBIL SEMUA DAFTAR PRODUK UNTUK TAMPILAN HALAMAN
# MENGAMBIL SEMUA DAFTAR PRODUK UNTUK TAMPILAN HALAMAN




# MENGAMBIL DAFTAR PRODUK UNGGULAN UNTUK TAMPILAN HALAMAN
// --- Fetch Featured Products (Only for home page) ---
$featured_products = [];
if ($page == 'home') { // Only fetch if on home page
    $sql_featured = "SELECT p.id, p.name, p.img, p.price, p.description, p.seller_id, c.name as category_name
                     FROM products p
                     LEFT JOIN users u ON p.seller_id = u.id
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE p.seller_id IS NOT NULL AND u.role = 'seller' AND p.stock > 0
                     ORDER BY RAND() LIMIT 4";
    $result_featured = mysqli_query($conn, $sql_featured);
    if ($result_featured) {
        while ($row = mysqli_fetch_assoc($result_featured)) {
            if (empty($row['category_name'])) {
                $row['category_name'] = 'Lain-lain';
            }
            $featured_products[] = $row;
        }
    }
}
# MENGAMBIL DAFTAR PRODUK UNGGULAN UNTUK TAMPILAN HALAMAN



# MENGAMBIL DAFTAR KATEGORI UNTUK FILTERING
// NEW: Get all unique categories for filtering
$all_categories = [];
$sql_all_categories = "SELECT name FROM categories ORDER BY name ASC";
$result_all_categories = mysqli_query($conn, $sql_all_categories);
if ($result_all_categories) {
    while ($row_cat = mysqli_fetch_assoc($result_all_categories)) {
        $all_categories[] = $row_cat['name'];
    }
}
# MENGAMBIL DAFTAR KATEGORI UNTUK FILTERING



// Check for popup messages from redirect
if (isset($_GET['popup_message']) && isset($_GET['popup_status'])) {
    $popup_message = urldecode($_GET['popup_message']);
    $popup_status = urldecode($_GET['popup_status']);
}



# HITUNG TOKO YANG DIIKUTI PEMBELI
// NEW: Fetch number of followed stores for profile page
$total_followed_stores = 0;
if ($page == 'profile') {
    $sql_followed_stores = "SELECT COUNT(id) FROM store_followers WHERE user_id = ?";
    if ($stmt_followed = mysqli_prepare($conn, $sql_followed_stores)) {
        mysqli_stmt_bind_param($stmt_followed, "i", $user_id);
        mysqli_stmt_execute($stmt_followed);
        mysqli_stmt_bind_result($stmt_followed, $count_followed);
        mysqli_stmt_fetch($stmt_followed);
        $total_followed_stores = $count_followed;
        mysqli_stmt_close($stmt_followed);
    }
}
# HITUNG TOKO YANG DIIKUTI PEMBELI


# HITUNG PESAN BELUM DIBACA
// NEW: Fetch unread messages count for sidebar badge
$unread_messages_count = 0;
$sql_unread = "SELECT COUNT(id) FROM messages WHERE receiver_id = ? AND is_read = 0";
if ($stmt_unread = mysqli_prepare($conn, $sql_unread)) {
    mysqli_stmt_bind_param($stmt_unread, "i", $user_id);
    mysqli_stmt_execute($stmt_unread);
    mysqli_stmt_bind_result($stmt_unread, $count_unread);
    mysqli_stmt_fetch($stmt_unread);
    $unread_messages_count = $count_unread;
    mysqli_stmt_close($stmt_unread);
}
# HITUNG PESAN BELUM DIBACA


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
            <a href="dashboard.php?page=chat" class="<?php echo ($page == 'chat') ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Pesan Saya <?php if ($unread_messages_count > 0): ?><span class="badge"><?php echo $unread_messages_count; ?></span><?php endif; ?></a>
            <a href="dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> Profil</a>
            <a href="/PlantPals/dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Kontak</a>
        </nav>

        <main class="content">
            <?php
            if ($page == 'home') {
                ?>
                <h2>Selamat Datang di PlantPals!</h2>
                <form class="search-bar" action="dashboard.php" method="get">
                    <input type="hidden" name="page" value="home" />
                    <input type="text" id="searchInput" name="q" placeholder="Cari tanaman, alat, atau pupuk..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>

                <div class="category-filter-bar">
                    <button class="filter-btn active" data-category="all"><i class="fas fa-layer-group"></i> Semua</button>
                    <?php foreach ($all_categories as $cat): ?>
                        <button class="filter-btn" data-category="<?php echo htmlspecialchars($cat); ?>"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat); ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="flowerGrid" class="grid">
                <?php
                $filtered_flowers_for_display = [];
                $keyword = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

                if (!empty($keyword)) {
                    foreach ($flowers_to_display as $flower) {
                        if (
                            stripos($flower['name'], $keyword) !== false ||
                            stripos($flower['description'] ?? '', $keyword) !== false ||
                            stripos($flower['scientific_name'] ?? $flower['scientific'] ?? '', $keyword) !== false ||
                            stripos($flower['family'] ?? '', $keyword) !== false ||
                            stripos($flower['category_name'] ?? '', $keyword) !== false
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
                    foreach ($filtered_flowers_for_display as $flower):
                        $selling_store_home = null;
                        if (isset($flower['seller_id'])) {
                            if (!isset($stores_by_seller_id[$flower['seller_id']])) {
                                $sql_temp_store = "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, s.followers_count FROM stores s WHERE s.seller_user_id = ? LIMIT 1";
                                if ($stmt_temp_store = mysqli_prepare($conn, $sql_temp_store)) {
                                    mysqli_stmt_bind_param($stmt_temp_store, "i", $flower['seller_id']);
                                    mysqli_stmt_execute($stmt_temp_store);
                                    $result_temp_store = mysqli_stmt_get_result($stmt_temp_store);
                                    if ($temp_store_data = mysqli_fetch_assoc($result_temp_store)) {
                                        $stores_by_seller_id[$flower['seller_id']][] = $temp_store_data;
                                    }
                                    mysqli_stmt_close($stmt_temp_store);
                                }
                            }
                            if (isset($stores_by_seller_id[$flower['seller_id']])) {
                                $selling_store_home = $stores_by_seller_id[$flower['seller_id']][0] ?? null;
                            }
                        }
                        // Default store name/link if no actual seller or store is found for the product
                        $store_name_display_home = $selling_store_home['name'] ?? 'Toko Tidak Dikenal';
                        $store_link_home = $selling_store_home['store_id_string'] ? "store_profile_buyer.php?store_id_string=" . urlencode($selling_store_home['store_id_string']) : "#";
                        ?>
                        <div class="card" data-category="<?php echo htmlspecialchars($flower['category_name'] ?? ($flower['family'] ?? 'Lain-lain')); ?>">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($flower['name']); ?></h3>
                                <p class="category-display"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($flower['category_name'] ?? ($flower['family'] ?? 'Lain-lain')); ?></p>
                                <p><strong>Nama Ilmiah:</strong> <?php echo htmlspecialchars($flower['scientific_name'] ?? $flower['scientific'] ?? 'N/A'); ?></p>
                                <p><strong>Familia:</strong> <?php echo htmlspecialchars($flower['family'] ?? 'N/A'); ?></p>
                                <p><?php echo htmlspecialchars(substr($flower['description'] ?? '', 0, 80)); ?>...</p>
                                <p class="price">Rp <?php echo number_format($flower['price'], 0, ',', '.'); ?></p>
                                <div class="store-info-display">
                                    <span class="label"><i class="fas fa-store"></i> Dijual oleh:</span>
                                    <?php if ($selling_store_home && !empty($store_link_home) && $store_link_home !== "#"): ?>
                                        <a href="<?php echo $store_link_home; ?>" class="store-name-link">
                                            <?php echo htmlspecialchars($store_name_display_home); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="store-name-link"><?php echo htmlspecialchars($store_name_display_home); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-buttons-container">
                                <a href="detail_flower.php?name=<?php echo urlencode(strtolower(str_replace(' ', '_', $flower['name']))); ?>" class="see-more-btn"><i class="fas fa-info-circle"></i> Detail</a>
                                <?php if ($flower['seller_id'] && $flower['seller_id'] != $user_id && $selling_store_home): // Show chat if seller_id valid, not own product, and store data exists ?>
                                <a href="/PlantPals/dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($flower['seller_id']); ?>&store_id=<?php echo htmlspecialchars($selling_store_home['id'] ?? ''); ?>&subject=Pertanyaan Produk: <?php echo urlencode($flower['name']); ?>" class="chat-product-btn">
                                    <i class="fas fa-comment-dots"></i> Chat
                                </a>
                                <?php endif; ?>
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    endforeach;
                }
                ?>
                </div>
                <div id="noFilteredProductsHome" class="no-filtered-products">
                    <i class="fas fa-filter"></i>
                    <p>Tidak ada produk ditemukan dalam kategori yang dipilih.</p>
                </div>
                <?php
            } elseif ($page == 'products') {
                ?>
                <div class="page-content-panel">
                    <h2>Katalog Produk Kami</h2>
                    <p class="page-description">Temukan berbagai tanaman, alat, dan kebutuhan kebun dari penjual terpercaya!</p>

                    <div class="category-filter-bar">
                        <button class="filter-btn active" data-category="all"><i class="fas fa-layer-group"></i> Semua</button>
                        <?php foreach ($all_categories as $cat): ?>
                        <button class="filter-btn" data-category="<?php echo htmlspecialchars($cat); ?>"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="product-list-page grid" id="productListGrid">
                        <?php foreach ($flowers_to_display as $flower):
                            $selling_store = null;
                            if (isset($flower['seller_id'])) {
                                if (!isset($stores_by_seller_id[$flower['seller_id']])) {
                                    $sql_temp_store = "SELECT s.id, s.store_id_string, s.name, s.address, s.phone_number, s.email, s.seller_user_id, s.followers_count FROM stores s WHERE s.seller_user_id = ? LIMIT 1";
                                    if ($stmt_temp_store = mysqli_prepare($conn, $sql_temp_store)) {
                                        mysqli_stmt_bind_param($stmt_temp_store, "i", $flower['seller_id']);
                                        mysqli_stmt_execute($stmt_temp_store);
                                        $result_temp_store = mysqli_stmt_get_result($stmt_temp_store);
                                        if ($temp_store_data = mysqli_fetch_assoc($result_temp_store)) {
                                            $stores_by_seller_id[$flower['seller_id']][] = $temp_store_data;
                                        }
                                        mysqli_stmt_close($stmt_temp_store);
                                    }
                                }
                                if (isset($stores_by_seller_id[$flower['seller_id']])) {
                                    $selling_store = $stores_by_seller_id[$flower['seller_id']][0] ?? null;
                                }
                            }
                            $store_link = "#";
                            $store_name_display = "Toko Tidak Dikenal";
                            $store_db_id_product_list = null;

                            if ($selling_store) {
                                $store_link = "store_profile_buyer.php?store_id_string=" . urlencode($selling_store['store_id_string']);
                                $store_name_display = htmlspecialchars($selling_store['name']);
                                $store_db_id_product_list = htmlspecialchars($selling_store['id']);
                            }
                        ?>
                        <div class="product-item-page card" data-category="<?php echo htmlspecialchars($flower['category_name'] ?? ($flower['family'] ?? 'Lain-lain')); ?>">
                            <img src="<?php echo htmlspecialchars($flower['img']); ?>" alt="<?php echo htmlspecialchars($flower['name']); ?>" />
                            <div class="card-content">
                                <h4><?php echo htmlspecialchars($flower['name']); ?></h4>
                                <p class="category-display"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($flower['category_name'] ?? ($flower['family'] ?? 'Lain-lain')); ?></p>
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
                                <?php if ($flower['seller_id'] && $flower['seller_id'] != $user_id): // Don't show chat/buy if it's their own product ?>
                                <a href="/PlantPals/dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($flower['seller_id']); ?>&store_id=<?php echo htmlspecialchars($store_db_id_product_list); ?>&subject=Pertanyaan Produk: <?php echo urlencode($flower['name']); ?>" class="chat-product-btn">
                                    <i class="fas fa-comment-dots"></i> Chat Penjual
                                </a>
                                <?php endif; ?>
                                <form action="dashboard.php" method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($flower['id']); ?>">
                                    <button type="submit" class="buy-button"><i class="fas fa-cart-plus"></i> Tambah ke Keranjang</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="noFilteredProductsList" class="no-filtered-products">
                        <i class="fas fa-filter"></i>
                        <p>Tidak ada produk ditemukan dalam kategori yang dipilih.</p>
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
                    <?php else: ?>
                        <div class="cart-items-list">
                            <?php foreach ($cart_items as $product_id => $item):
                                $subtotal = $item['price'] * $item['quantity'];
                                $total_cart_amount += $subtotal;
                            ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="category-display"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category'] ?? 'Lain-lain'); ?></p>
                                        <p class="price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                                        <p>Dari: <?php echo htmlspecialchars($item['store_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="cart-item-actions">
                                        <form action="dashboard.php" method="post" style="display:flex; align-items:center;">
                                            <input type="hidden" name="action" value="update_cart_quantity">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                            <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" max="<?php echo htmlspecialchars($item['stock']); ?>" onchange="this.form.submit()">
                                            <button type="submit" class="buy-button" style="display:none;">Update</button> </form>
                                        <form action="dashboard.php" method="post">
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
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_id_string]" value="<?php echo htmlspecialchars($item['store_id_string'] ?? ''); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][store_name]" value="<?php echo htmlspecialchars($item['store_name'] ?? ''); ?>">
                                    <input type="hidden" name="cart_items[<?php echo $product_id; ?>][category]" value="<?php echo htmlspecialchars($item['category'] ?? 'Lain-lain'); ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="checkout-btn"><i class="fas fa-money-check-alt"></i> Lanjutkan ke Pembayaran</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'profile') {
                $user_data = [];
                $sql_user = "SELECT id, username, email, full_name, phone_number, address, created_at, role FROM users WHERE id = ?";
                if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
                    mysqli_stmt_bind_param($stmt_user, "i", $_SESSION['id']);
                    mysqli_stmt_execute($stmt_user);
                    $result_user = mysqli_stmt_get_result($stmt_user);
                    $user_data = mysqli_fetch_assoc($result_user);
                    mysqli_stmt_close($stmt_user);
                }

                // Get count of stores followed by this user
                $stores_followed_count = 0;
                $sql_followed_count = "SELECT COUNT(id) FROM store_followers WHERE user_id = ?";
                if ($stmt_followed = mysqli_prepare($conn, $sql_followed_count)) {
                    mysqli_stmt_bind_param($stmt_followed, "i", $user_id);
                    mysqli_stmt_execute($stmt_followed);
                    mysqli_stmt_bind_result($stmt_followed, $count_followed);
                    mysqli_stmt_fetch($stmt_followed);
                    $stores_followed_count = $count_followed;
                    mysqli_stmt_close($stmt_followed);
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
                            <p><strong><i class="fas fa-store"></i> Toko Diikuti:</strong> <?php echo htmlspecialchars($stores_followed_count); ?></p>
                            <p><strong><i class="fas fa-users"></i> Pengikut (anda):</strong> N/A</p> <a href="dashboard.php?page=profile&action=edit_profile" class="profile-info-btn"><i class="fas fa-edit"></i> Edit Profil</a>
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
                
                // Base SQL query for orders
                $sql_orders_base = "SELECT id, user_id, total_amount, order_status, payment_method, delivery_address, customer_name, customer_phone, customer_email, notes, order_date FROM orders WHERE user_id = ?";
                $sql_orders_params = [$_SESSION['id']];
                $sql_orders_types = "i";

                // Add filter for order status if not 'all'
                if ($order_filter_status !== 'all') {
                    $sql_orders_base .= " AND order_status = ?";
                    $sql_orders_params[] = $order_filter_status;
                    $sql_orders_types .= "s";
                }
                $sql_orders_base .= " ORDER BY order_date DESC";

                if ($stmt_orders = mysqli_prepare($conn, $sql_orders_base)) {
                    mysqli_stmt_bind_param($stmt_orders, $sql_orders_types, ...$sql_orders_params);
                    mysqli_stmt_execute($stmt_orders);
                    $result_orders = mysqli_stmt_get_result($stmt_orders);
                    while ($row = mysqli_fetch_assoc($result_orders)) {
                        $order_items = [];
                        // Ambil seller_id dari produk untuk keperluan chat
                        $item_sql = "SELECT oi.*, c.name as category_name, p.seller_id as product_seller_user_id FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE order_id = ?";
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
                    <div class="order-page-header">
                        <h2><i class="fas fa-box-open"></i> Pesanan Anda</h2>
                        <a href="dashboard.php?page=orders&order_status=history" class="view-history-btn">Lihat Riwayat Pesanan <i class="fas fa-chevron-right"></i></a>
                    </div>
                    
                    <div class="order-status-filter-bar">
                        <a href="dashboard.php?page=orders&order_status=all" class="filter-status-btn <?php echo ($order_filter_status == 'all' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-clipboard-list"></i></div>
                            <span>Semua</span>
                        </a>
                        <a href="dashboard.php?page=orders&order_status=pending" class="filter-status-btn <?php echo ($order_filter_status == 'pending' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-wallet"></i></div>
                            <span>Belum Bayar</span>
                        </a>
                        <a href="dashboard.php?page=orders&order_status=processing" class="filter-status-btn <?php echo ($order_filter_status == 'processing' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-box"></i></div>
                            <span>Dikemas</span>
                        </a>
                        <a href="dashboard.php?page=orders&order_status=shipped" class="filter-status-btn <?php echo ($order_filter_status == 'shipped' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-truck"></i></div>
                            <span>Dikirim</span>
                        </a>
                        <a href="dashboard.php?page=orders&order_status=completed" class="filter-status-btn <?php echo ($order_filter_status == 'completed' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-check-circle"></i></div>
                            <span>Selesai</span>
                        </a>
                        <a href="dashboard.php?page=orders&order_status=cancelled" class="filter-status-btn <?php echo ($order_filter_status == 'cancelled' ? 'active' : ''); ?>">
                            <div class="icon-wrapper"><i class="fas fa-times-circle"></i></div>
                            <span>Dibatalkan</span>
                        </a>
                    </div>

                    <?php if (empty($user_orders)): ?>
                        <p class="no-results">Tidak ada pesanan yang ditemukan untuk status ini.</p>
                    <?php else: ?>
                        <ul class="order-list">
                            <?php foreach ($user_orders as $order): ?>
                                <li class="order-item-card"> <div class="order-header">
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

                                        if (($current_timestamp - $order_timestamp) <= $one_hour_limit &&
                                            ($order['order_status'] == 'pending' || $order['order_status'] == 'processing')):
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
                                        <?php
                                        // Group items by store for better readability within THIS order
                                        $grouped_items = [];
                                        foreach ($order['items'] as $item) {
                                            $store_key = $item['store_id']; // Use store_id from order_items for grouping
                                            if (!isset($grouped_items[$store_key])) {
                                                $grouped_items[$store_key] = [
                                                    'store_name' => $item['store_name'],
                                                    'store_id' => $item['store_id'], // actual store_id (int)
                                                    'seller_user_id' => $item['product_seller_user_id'], // seller_user_id for chat
                                                    'products' => []
                                                ];
                                            }
                                            $grouped_items[$store_key]['products'][] = $item;
                                        }
                                        ?>
                                        <?php if (!empty($grouped_items)): ?>
                                            <?php foreach ($grouped_items as $store_group_id => $store_group): ?>
                                                <div class="order-store-group">
                                                    <div class="store-group-header">
                                                        <strong><i class="fas fa-store"></i> Toko:
                                                            <?php echo htmlspecialchars($store_group['store_name']); ?>
                                                        </strong>
                                                        <?php if ($store_group['seller_user_id'] && $store_group['seller_user_id'] != $user_id): ?>
                                                            <a href="/PlantPals/dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($store_group['seller_user_id']); ?>&store_id=<?php echo htmlspecialchars($store_group['store_id']); ?>&subject=Pertanyaan Pesanan #<?php echo htmlspecialchars($order['id']); ?>" class="chat-seller-btn">
                                                                <i class="fas fa-comment-dots"></i> Chat Penjual
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <ul>
                                                        <?php foreach ($store_group['products'] as $item): ?>
                                                            <li>- <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['quantity']); ?>x) @ Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?> (Kategori: <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>)</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>Tidak ada item produk untuk pesanan ini.</p>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($page == 'chat') { // CHAT PAGE
                $target_seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
                $target_store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
                $default_subject = isset($_GET['subject']) ? htmlspecialchars($_GET['subject']) : '';
                $default_message_content = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

                $seller_username_target = 'Pilih Penjual';
                $store_name_target = 'Tidak Dikenal';
                $store_profile_url_target = '#'; // Default empty URL

                // Fetch unique sellers who sent messages to/received messages from this buyer
                $conversation_sellers = [];
                // Update the query to also select store_id_string for conversation list
                $sql_conversation_sellers = "SELECT DISTINCT u.id, u.username, s.id as store_db_id, s.name as store_name, s.store_id_string
                                            FROM messages m
                                            JOIN users u ON (m.sender_id = u.id AND m.receiver_id = ?) OR (m.receiver_id = u.id AND m.sender_id = ?)
                                            LEFT JOIN stores s ON u.id = s.seller_user_id
                                            WHERE u.role = 'seller'
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
                    // Get username, store name, and store_id_string of the selected target seller
                    $stmt_get_seller_info = mysqli_prepare($conn, "SELECT u.username, s.name as store_name, s.store_id_string FROM users u LEFT JOIN stores s ON u.id = s.seller_user_id WHERE u.id = ? AND u.role = 'seller'");
                    if ($stmt_get_seller_info) {
                        mysqli_stmt_bind_param($stmt_get_seller_info, "i", $target_seller_id);
                        mysqli_stmt_execute($stmt_get_seller_info);
                        mysqli_stmt_bind_result($stmt_get_seller_info, $uname, $sname, $s_id_string);
                        if (mysqli_stmt_fetch($stmt_get_seller_info)) {
                            $seller_username_target = htmlspecialchars($uname);
                            $store_name_target = htmlspecialchars($sname ?? 'Toko Tidak Dikenal');
                            if (!empty($s_id_string)) {
                                $store_profile_url_target = 'store_profile_buyer.php?store_id_string=' . urlencode($s_id_string);
                            }
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
                            // Mark messages from this sender as read when conversation is opened
                            if ($row_msg['sender_id'] == $target_seller_id && $row_msg['receiver_id'] == $user_id && $row_msg['is_read'] == 0) {
                                $stmt_mark_read = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 WHERE id = ?");
                                if ($stmt_mark_read) {
                                    mysqli_stmt_bind_param($stmt_mark_read, "i", $row_msg['id']);
                                    mysqli_stmt_execute($stmt_mark_read);
                                    mysqli_stmt_close($stmt_mark_read);
                                }
                            }
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
                                <p class="no-messages">Belum ada percakapan dengan penjual.</p>
                            <?php else: ?>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($conversation_sellers as $seller_conv):
                                        // Count unread messages for this seller (messages FROM seller to buyer)
                                        $unread_count = 0;
                                        // Use the existing $conn for unread count
                                        $stmt_unread = mysqli_prepare($conn, "SELECT COUNT(id) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                                        if ($stmt_unread) {
                                            mysqli_stmt_bind_param($stmt_unread, "ii", $seller_conv['id'], $user_id);
                                            mysqli_stmt_execute($stmt_unread);
                                            mysqli_stmt_bind_result($stmt_unread, $count);
                                            mysqli_stmt_fetch($stmt_unread);
                                            $unread_count = $count;
                                            mysqli_stmt_close($stmt_unread);
                                        }
                                        $conv_store_profile_url = !empty($seller_conv['store_id_string']) ? 'store_profile_buyer.php?store_id_string=' . urlencode($seller_conv['store_id_string']) : '#';
                                    ?>
                                        <li style="margin-bottom: 5px;">
                                            <a href="dashboard.php?page=chat&seller_id=<?php echo htmlspecialchars($seller_conv['id']); ?>&store_id=<?php echo htmlspecialchars($seller_conv['store_db_id'] ?? 0); ?>"
                                               class="conversation-item <?php echo ($seller_conv['id'] == $target_seller_id) ? 'active' : ''; ?>">
                                                <strong><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($seller_conv['username']); ?> (
                                                    <?php if ($conv_store_profile_url != '#'): ?>
                                                        <a href="<?php echo $conv_store_profile_url; ?>" class="chat-store-link" onclick="event.stopPropagation();">
                                                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($seller_conv['store_name']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="chat-store-name-plain"><i class="fas fa-store"></i> <?php echo htmlspecialchars($seller_conv['store_name']); ?></span>
                                                    <?php endif; ?>
                                                )</strong>
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
                                <h3>
                                    <?php if (!empty($store_profile_url_target)): ?>
                                        <a href="<?php echo htmlspecialchars($store_profile_url_target); ?>" class="chat-header-store-link">
                                            <i class="fas fa-store"></i> <?php echo $seller_username_target; ?> (<span class="store-name-in-chat"><?php echo $store_name_target; ?></span>)
                                        </a>
                                    <?php else: ?>
                                        <span class="chat-header-store-name-plain"><i class="fas fa-store"></i> <?php echo $seller_username_target; ?> (<?php echo $store_name_target; ?>)</span>
                                    <?php endif; ?>
                                </h3>
                                
                                <div class="chat-messages-display" id="chat-messages-display">
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
                                <p class="no-messages">Pilih percakapan dari daftar di sebelah kiri atau kirim pesan baru.</p>
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

            // Replace \n with <br> for multiline messages
            popupMessage.innerHTML = message.replace(/\\n/g, '<br>');
            popupIcon.className = 'icon'; // Reset classes
            popupCloseBtn.className = 'close-btn'; // Reset classes for button

            if (status === 'success') {
                popupIcon.classList.add('fas', 'fa-check-circle', 'success');
                popupTitle.textContent = 'Berhasil!';
                popupBox.style.borderColor = '#4CAF50'; // Set border color for success
            } else if (status === 'error') {
                popupIcon.classList.add('fas', 'fa-times-circle', 'error');
                popupTitle.textContent = 'Gagal!';
                popupBox.style.borderColor = '#f44336'; // Set border color for error
                popupCloseBtn.classList.add('error-btn'); // Add error button style
            } else {
                popupIcon.classList.add('fas', 'fa-info-circle');
                popupTitle.textContent = 'Informasi';
                popupBox.style.borderColor = '#2196F3'; // Default info color
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

        // JavaScript for Category Filtering on Home & Products pages
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.category-filter-bar .filter-btn');
            const productCardsHome = document.querySelectorAll('#flowerGrid .card'); // Target for home page
            const productCardsList = document.querySelectorAll('#productListGrid .card'); // Target for products page
            const noFilteredProductsMessageHome = document.getElementById('noFilteredProductsHome'); // For home page
            const noFilteredProductsMessageList = document.getElementById('noFilteredProductsList'); // For products page

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const selectedCategory = this.dataset.category;
                    let productsShownHome = 0;
                    let productsShownList = 0;

                    // Filter for Home page grid
                    productCardsHome.forEach(card => {
                        const cardCategory = card.dataset.category;
                        if (selectedCategory === 'all' || cardCategory === selectedCategory) {
                            card.style.display = 'flex';
                            productsShownHome++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Filter for Products page grid
                    productCardsList.forEach(card => {
                        const cardCategory = card.dataset.category;
                        if (selectedCategory === 'all' || cardCategory === selectedCategory) {
                            card.style.display = 'flex';
                            productsShownList++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Update no products message based on current page
                    if (window.location.search.includes('page=home')) {
                        if (productsShownHome === 0) {
                            noFilteredProductsMessageHome.style.display = 'block';
                        } else {
                            noFilteredProductsMessageHome.style.display = 'none';
                        }
                    } else if (window.location.search.includes('page=products')) {
                         if (productsShownList === 0) {
                            noFilteredProductsMessageList.style.display = 'block';
                        } else {
                            noFilteredProductsMessageList.style.display = 'none';
                        }
                    }
                });
            });

            // Initial filter application on page load (e.g., if a category is passed via URL)
            // Ensure the 'all' button is clicked by default
            document.querySelector('.category-filter-bar button[data-category="all"]').click();

            // Auto-scroll chat messages to bottom on load/update
            const chatMessagesDisplay = document.getElementById('chat-messages-display');
            if (chatMessagesDisplay) {
                chatMessagesDisplay.scrollTop = chatMessagesDisplay.scrollHeight;
            }
        });

    </script>
</body>
</html>