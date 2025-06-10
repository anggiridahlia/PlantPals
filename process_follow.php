<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Respond with JSON

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk melakukan aksi ini.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

$user_id = $_SESSION['id'];
$store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'follow' or 'unfollow'

if ($store_id <= 0 || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit();
}

require_once 'config.php';

if ($conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit();
}

$response = ['success' => false, 'message' => '', 'is_following' => false, 'followers_count' => 0];

// Get the actual seller_user_id for the given store_id to ensure validity
$seller_user_id_from_store = 0;
$stmt_get_seller = mysqli_prepare($conn, "SELECT seller_user_id FROM stores WHERE id = ?");
if ($stmt_get_seller) {
    mysqli_stmt_bind_param($stmt_get_seller, "i", $store_id);
    mysqli_stmt_execute($stmt_get_seller);
    mysqli_stmt_bind_result($stmt_get_seller, $seller_user_id_from_store);
    mysqli_stmt_fetch($stmt_get_seller);
    mysqli_stmt_close($stmt_get_seller);
}

// Prevent a user from following their own store
if ($seller_user_id_from_store === $user_id) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak bisa mengikuti toko Anda sendiri.']);
    mysqli_close($conn);
    exit();
}


mysqli_autocommit($conn, FALSE); // Start transaction
$transaction_success = true;

try {
    if ($action === 'follow') {
        // Check if already following
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM store_followers WHERE store_id = ? AND user_id = ?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "ii", $store_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $response['message'] = 'Anda sudah mengikuti toko ini.';
                $response['is_following'] = true; // Already following
                $transaction_success = true; // No actual change needed for DB but not an error
            } else {
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO store_followers (store_id, user_id) VALUES (?, ?)");
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $store_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_insert)) {
                        $transaction_success = false;
                        $response['message'] = 'Gagal mengikuti toko: ' . mysqli_stmt_error($stmt_insert);
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    $transaction_success = false;
                    $response['message'] = 'Gagal menyiapkan query follow.';
                }
            }
            mysqli_stmt_close($stmt_check);
        } else {
            $transaction_success = false;
            $response['message'] = 'Gagal menyiapkan query cek follow.';
        }
        
        if ($transaction_success) {
            $response['is_following'] = true;
        }

    } elseif ($action === 'unfollow') {
        $stmt_delete = mysqli_prepare($conn, "DELETE FROM store_followers WHERE store_id = ? AND user_id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "ii", $store_id, $user_id);
            if (!mysqli_stmt_execute($stmt_delete)) {
                $transaction_success = false;
                $response['message'] = 'Gagal berhenti mengikuti toko: ' . mysqli_stmt_error($stmt_delete);
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $transaction_success = false;
            $response['message'] = 'Gagal menyiapkan query unfollow.';
        }
        
        if ($transaction_success) {
            $response['is_following'] = false;
        }
    }

    // Update followers_count in stores table (only if transaction was successful for follow/unfollow)
    if ($transaction_success) {
        $stmt_count = mysqli_prepare($conn, "SELECT COUNT(id) FROM store_followers WHERE store_id = ?");
        if ($stmt_count) {
            mysqli_stmt_bind_param($stmt_count, "i", $store_id);
            mysqli_stmt_execute($stmt_count);
            mysqli_stmt_bind_result($stmt_count, $current_followers_count);
            mysqli_stmt_fetch($stmt_count);
            mysqli_stmt_close($stmt_count);

            $stmt_update_store = mysqli_prepare($conn, "UPDATE stores SET followers_count = ? WHERE id = ?");
            if ($stmt_update_store) {
                mysqli_stmt_bind_param($stmt_update_store, "ii", $current_followers_count, $store_id);
                if (!mysqli_stmt_execute($stmt_update_store)) {
                    $transaction_success = false; // Mark overall transaction as failed
                    $response['message'] .= ' Gagal update jumlah pengikut di tabel stores.';
                }
                mysqli_stmt_close($stmt_update_store);
            } else {
                 $transaction_success = false;
                 $response['message'] .= ' Gagal menyiapkan query update followers_count.';
            }
            $response['followers_count'] = $current_followers_count; // Return updated count
        } else {
            $transaction_success = false;
            $response['message'] .= ' Gagal menghitung pengikut.';
        }
    }


    if ($transaction_success) {
        mysqli_commit($conn);
        $response['success'] = true;
        if (empty($response['message'])) { // If message not set by already following check
            $response['message'] = ($action === 'follow') ? 'Berhasil mengikuti toko!' : 'Berhasil berhenti mengikuti toko.';
        }
    } else {
        mysqli_rollback($conn);
        if (empty($response['message'])) { // Fallback error message
             $response['message'] = 'Terjadi kesalahan tidak dikenal saat memproses permintaan.';
        }
    }

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Respond with JSON

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk melakukan aksi ini.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit();
}

$user_id = $_SESSION['id']; // ID pembeli yang sedang login
$store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'follow' or 'unfollow'

if ($store_id <= 0 || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit();
}

require_once 'config.php';

if ($conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit();
}

$response = ['success' => false, 'message' => '', 'is_following' => false, 'followers_count' => 0];

// Get the actual seller_user_id for the given store_id to ensure validity
$seller_user_id_from_store = 0;
$stmt_get_seller = mysqli_prepare($conn, "SELECT seller_user_id FROM stores WHERE id = ?");
if ($stmt_get_seller) {
    mysqli_stmt_bind_param($stmt_get_seller, "i", $store_id);
    mysqli_stmt_execute($stmt_get_seller);
    mysqli_stmt_bind_result($stmt_get_seller, $seller_user_id_from_store);
    mysqli_stmt_fetch($stmt_get_seller);
    mysqli_stmt_close($stmt_get_seller);
} else {
    $response['message'] = 'Gagal menyiapkan query untuk mendapatkan ID penjual.';
    echo json_encode($response);
    mysqli_close($conn);
    exit();
}

// Prevent a user from following their own store
if ($seller_user_id_from_store === $user_id) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak bisa mengikuti toko Anda sendiri.']);
    mysqli_close($conn);
    exit();
}

mysqli_autocommit($conn, FALSE); // Start transaction
$transaction_success = true;

try {
    if ($action === 'follow') {
        // Check if already following to prevent duplicate entry errors
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM store_followers WHERE store_id = ? AND user_id = ?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "ii", $store_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                // Already following, no action needed but success
                $response['message'] = 'Anda sudah mengikuti toko ini.';
                $response['is_following'] = true;
            } else {
                // Not following, insert new record
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO store_followers (store_id, user_id) VALUES (?, ?)");
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $store_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_insert)) {
                        $transaction_success = false;
                        $response['message'] = 'Gagal mengikuti toko: ' . mysqli_stmt_error($stmt_insert);
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    $transaction_success = false;
                    $response['message'] = 'Gagal menyiapkan query follow.';
                }
            }
            mysqli_stmt_close($stmt_check);
        } else {
            $transaction_success = false;
            $response['message'] = 'Gagal menyiapkan query cek follow.';
        }
        
        if ($transaction_success) {
            $response['is_following'] = true; // Set to true if follow action was successful or already following
        }

    } elseif ($action === 'unfollow') {
        $stmt_delete = mysqli_prepare($conn, "DELETE FROM store_followers WHERE store_id = ? AND user_id = ?");
        if ($stmt_delete) {
            mysqli_stmt_bind_param($stmt_delete, "ii", $store_id, $user_id);
            if (!mysqli_stmt_execute($stmt_delete)) {
                $transaction_success = false;
                $response['message'] = 'Gagal berhenti mengikuti toko: ' . mysqli_stmt_error($stmt_delete);
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            $transaction_success = false;
            $response['message'] = 'Gagal menyiapkan query unfollow.';
        }
        
        if ($transaction_success) {
            $response['is_following'] = false; // Set to false if unfollow action was successful
        }
    }

    // Always re-calculate and update followers_count in stores table if transaction has been successful so far
    if ($transaction_success) {
        $stmt_count = mysqli_prepare($conn, "SELECT COUNT(id) FROM store_followers WHERE store_id = ?");
        if ($stmt_count) {
            mysqli_stmt_bind_param($stmt_count, "i", $store_id);
            mysqli_stmt_execute($stmt_count);
            mysqli_stmt_bind_result($stmt_count, $current_followers_count);
            mysqli_stmt_fetch($stmt_count);
            mysqli_stmt_close($stmt_count);

            $stmt_update_store = mysqli_prepare($conn, "UPDATE stores SET followers_count = ? WHERE id = ?");
            if ($stmt_update_store) {
                mysqli_stmt_bind_param($stmt_update_store, "ii", $current_followers_count, $store_id);
                if (!mysqli_stmt_execute($stmt_update_store)) {
                    $transaction_success = false; // Mark overall transaction as failed
                    $response['message'] .= ' Gagal update jumlah pengikut di tabel stores.';
                }
                mysqli_stmt_close($stmt_update_store);
            } else {
                 $transaction_success = false;
                 $response['message'] .= ' Gagal menyiapkan query update followers_count.';
            }
            $response['followers_count'] = $current_followers_count; // Return updated count
        } else {
            $transaction_success = false;
            $response['message'] .= ' Gagal menghitung pengikut.';
        }
    }


    if ($transaction_success) {
        mysqli_commit($conn);
        $response['success'] = true;
        if (empty($response['message'])) { // If message not set by already following check
            $response['message'] = ($action === 'follow') ? 'Berhasil mengikuti toko!' : 'Berhasil berhenti mengikuti toko.';
        }
    } else {
        mysqli_rollback($conn);
        if (empty($response['message'])) { // Fallback error message
             $response['message'] = 'Terjadi kesalahan tidak dikenal saat memproses permintaan.';
        }
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    $response['success'] = false;
    $response['message'] = 'Exception: ' . $e->getMessage();
    error_log('Process Follow Exception: ' . $e->getMessage());
}

mysqli_close($conn);
echo json_encode($response);
exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $response['success'] = false;
    $response['message'] = 'Exception: ' . $e->getMessage();
    error_log('Process Follow Exception: ' . $e->getMessage());
}

mysqli_close($conn);
echo json_encode($response);
exit();