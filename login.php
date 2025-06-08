<?php
// login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check if user is already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    switch ($_SESSION['role'] ?? 'buyer') { // Default to buyer if role not set
        case 'admin':
            header("location: admin/index.php");
            break;
        case 'seller':
            header("location: seller/index.php");
            break;
        case 'buyer':
        default:
            header("location: dashboard.php");
            break;
    }
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }

    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";

        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;

            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);

                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $id, $username_db, $hashed_password, $role);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, start a new session
                            session_regenerate_id(true); // Security: Regenerate session ID
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username_db;
                            $_SESSION["role"] = $role; // Store the user's role

                            // Redirect user based on role
                            switch ($role) {
                                case 'admin':
                                    header("location: admin/index.php");
                                    break;
                                case 'seller':
                                    header("location: seller/index.php");
                                    break;
                                case 'buyer':
                                default:
                                    header("location: dashboard.php"); // Buyer dashboard
                                    break;
                            }
                            exit;
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PlantPals</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, rgb(228, 250, 228), rgb(242, 230, 234));
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #2f5d3a;
        }
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        h2 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            color: #E5989B;
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #3a5a20;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #c3d9c3;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
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
        .register-link {
            display: block;
            margin-top: 20px;
            font-size: 0.95rem;
            color: #2f5d3a;
        }
        .register-link a {
            color: #E5989B;
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .error-message {
            color: #f44336;
            font-size: 0.9rem;
            margin-top: -10px;
            margin-bottom: 10px;
            text-align: left;
        }
        .login-error {
            color: #f44336;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🌿</div>
        <h2>Selamat Datang di PlantPals</h2>
        <p>Silakan login untuk melanjutkan.</p>

        <?php
        if(!empty($login_err)){
            echo '<div class="login-error">' . $login_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                <div class="error-message"><?php echo $username_err; ?></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
                <div class="error-message"><?php echo $password_err; ?></div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Login</button>
            </div>
        </form>
        <div class="register-link">Belum punya akun? <a href="register.php">Daftar sekarang</a>.</div>
    </div>
</body>
</html>