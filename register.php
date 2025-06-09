<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

require_once 'config.php';

$username = $email = $password = $confirm_password = $full_name = $phone_number = $address = $role = "";
$username_err = $email_err = $password_err = $confirm_password_err = $role_err = "";
$full_name_err = $phone_number_err = $address_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Silakan masukkan username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "Username ini sudah digunakan.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                error_log("Error check username existence: " . mysqli_error($conn));
                echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Silakan masukkan email.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)){
        $email_err = "Format email tidak valid.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = trim($_POST["email"]);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $email_err = "Email ini sudah terdaftar.";
                } else{
                    $email = trim($_POST["email"]);
                }
            } else{
                error_log("Error check email existence: " . mysqli_error($conn));
                echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Silakan masukkan password.";
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password harus memiliki setidaknya 6 karakter.";
    } else{
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Silakan konfirmasi password.";
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password tidak cocok.";
        }
    }

    // Validate role selection
    if(empty(trim($_POST["role"])) || (trim($_POST["role"]) != 'buyer' && trim($_POST["role"]) != 'seller')){
        $role_err = "Silakan pilih peran yang valid.";
    } else {
        $role = trim($_POST["role"]);
    }

    // Get and validate other optional fields
    // Menggunakan operator null coalescing (??) untuk PHP 7+ atau isset() untuk PHP 5.6
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Basic validation for optional fields if needed for sellers
    if ($role == 'seller') {
        if (empty($full_name)) {
            $full_name_err = "Nama lengkap sangat disarankan untuk Penjual.";
        }
        if (empty($phone_number)) {
            $phone_number_err = "Nomor telepon sangat disarankan untuk Penjual.";
        }
        // Address can be optional, as store might have a specific address later
    }


    // Check input errors before inserting in database
    if(empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($full_name_err) && empty($phone_number_err) && empty($address_err)){

        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, email, full_name, phone_number, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "sssssss", $param_username, $param_password, $param_email, $param_full_name, $param_phone_number, $param_address, $param_role);

            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // MENGGUNAKAN HASHING PASSWORD
            $param_email = $email;
            $param_full_name = $full_name;
            $param_phone_number = $phone_number;
            $param_address = $address;
            $param_role = $role;

            if(mysqli_stmt_execute($stmt)){
                // Setelah pendaftaran berhasil, jika peran adalah seller,
                // tambahkan logika untuk membuat entri toko default
                // Ini adalah cara yang sederhana. Anda mungkin ingin membuat halaman terpisah
                // bagi seller untuk mengelola toko mereka (misal: seller/stores.php).
                if ($role == 'seller') {
                    $new_seller_id = mysqli_insert_id($conn); // Dapatkan ID user baru
                    // Buat store_id_string yang unik, misal: 'toko_' + username + timestamp
                    $store_id_str = 'toko_' . strtolower(str_replace([' ', '-', '.'], '_', $username)) . '_' . time();

                    $default_store_name = !empty($full_name) ? htmlspecialchars($full_name) . "'s Store" : $username . "'s Store";
                    $default_store_address = !empty($address) ? htmlspecialchars($address) : 'Alamat belum ditentukan';
                    $default_store_phone = !empty($phone_number) ? htmlspecialchars($phone_number) : 'Nomor telepon belum ditentukan';

                    $sql_insert_store = "INSERT INTO stores (store_id_string, name, address, phone_number, seller_user_id) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt_store = mysqli_prepare($conn, $sql_insert_store)) {
                        mysqli_stmt_bind_param($stmt_store, "ssssi", $store_id_str, $default_store_name, $default_store_address, $default_store_phone, $new_seller_id);
                        if (!mysqli_stmt_execute($stmt_store)) {
                            // Log error: could not create default store for new seller
                            error_log("Error creating default store for new seller " . $username . ": " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($stmt_store);
                    } else {
                        error_log("Error preparing store insert statement: " . mysqli_error($conn));
                    }
                }
                header("location: login.php");
                exit; // Penting untuk exit setelah header redirect
            } else{
                error_log("Error executing user insert: " . mysqli_error($conn));
                echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti. (Error: " . mysqli_error($conn) . ")";
            }

            mysqli_stmt_close($stmt);
        } else {
            error_log("Error preparing user insert statement: " . mysqli_error($conn));
            echo "Oops! Terjadi kesalahan. Silakan coba lagi nanti. (Error: " . mysqli_error($conn) . ")";
        }
    }

    // Close connection after all processing in POST request
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - PlantPals</title>
    <link rel="stylesheet" href="/PlantPals/css/main_styles.css">
    <style>
        body { /* Overrides main_styles body background for register page */
            background: linear-gradient(to right, rgb(228, 250, 228), rgb(242, 230, 234));
            justify-content: center; /* Center content vertically and horizontally */
            align-items: center;
        }
        .register-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            width: 100%;
            max-width: 450px;
        }
        h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            margin-bottom: 25px;
            color: #E5989B;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            font-family: 'Poppins', sans-serif;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #3a5a20;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select,
        textarea { /* Tambahkan textarea untuk styling */
            width: 100%;
            padding: 12px;
            border: 1px solid #c3d9c3;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus,
        textarea:focus { /* Tambahkan textarea untuk styling */
            border-color: #E5989B;
            box-shadow: 0 0 0 3px rgba(229, 152, 155, 0.2);
            outline: none;
        }
        .btn {
            background-color: #E5989B;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
        }
        .btn:hover {
            background-color: rgb(182, 88, 117);
            transform: translateY(-2px);
        }
        .login-link {
            display: block;
            margin-top: 20px;
            font-size: 0.95rem;
            color: #2f5d3a;
        }
        .login-link a {
            color: #E5989B;
            font-weight: 600;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .error-message {
            color: #f44336;
            font-size: 0.9rem;
            margin-top: -10px;
            margin-bottom: 10px;
            text-align: left;
        }
        select { /* Custom select styling for consistent look */
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%232f5d3a%22%20d%3D%22M287%2C197.3L159.2%2C69.5c-4.4-4.4-11.4-4.4-15.8%2C0L5.4%2C197.3c-4.4%2C4.4-4.4%2C11.4%2C0%2C15.8c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0l135.9-135.9l135.9%2C135.9c4.4%2C4.4%2C11.4%2C4.4%2C15.8%2C0C291.4%2C208.7%2C291.4%2C201.7%2C287%2C197.3z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 12px top 50%;
            background-size: 10px auto;
            padding-right: 35px; /* Make space for arrow */
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Daftar Akun Baru</h2>
        <p>Silakan isi formulir di bawah ini.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>">
                <div class="error-message"><?php echo $username_err; ?></div>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
                <div class="error-message"><?php echo $email_err; ?></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password">
                <div class="error-message"><?php echo $password_err; ?></div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" name="confirm_password" id="confirm_password">
                <div class="error-message"><?php echo $confirm_password_err; ?></div>
            </div>
            <div class="form-group">
                <label for="full_name">Nama Lengkap:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
                <div class="error-message"><?php echo $full_name_err; ?></div>
            </div>
            <div class="form-group">
                <label for="phone_number">Nomor Telepon (WhatsApp):</label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
                <div class="error-message"><?php echo $phone_number_err; ?></div>
            </div>
            <div class="form-group">
                <label for="address">Alamat Lengkap Pengiriman:</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                <div class="error-message"><?php echo $address_err; ?></div>
            </div>
            <div class="form-group">
                <label for="role">Daftar sebagai:</label>
                <select name="role" id="role" required>
                    <option value="">-- Pilih Peran --</option>
                    <option value="buyer" <?php echo ($role == 'buyer') ? 'selected' : ''; ?>>Pembeli</option>
                    <option value="seller" <?php echo ($role == 'seller') ? 'selected' : ''; ?>>Penjual</option>
                </select>
                <div class="error-message"><?php echo $role_err; ?></div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Daftar</button>
            </div>
        </form>
        <div class="login-link">Sudah punya akun? <a href="login.php">Login di sini</a>.</div>
    </div>
</body>
</html>