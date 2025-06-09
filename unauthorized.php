<?php
session_start();
// No need to include config or auth_middleware here
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Tidak Diizinkan - PlantPals</title>
    <style>
        body {
            background: rgb(245, 255, 245);
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #2a4d3a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
        }
        h1 {
            font-size: 3rem;
            color: #f44336; /* Red for error */
            margin-bottom: 20px;
        }
        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            padding: 12px 25px;
            background-color: #E5989B;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        a:hover {
            background-color: rgb(182, 88, 117);
        }
    </style>
</head>
<body>
    <h1>Akses Tidak Diizinkan!</h1>
    <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
    <?php
    // Determine where to redirect based on user role, or default to login
    $redirect_url = 'login.php';
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'admin':
                    $redirect_url = 'admin/index.php';
                    break;
                case 'seller':
                    $redirect_url = 'seller/index.php';
                    break;
                case 'buyer':
                default:
                    $redirect_url = 'dashboard.php';
                    break;
            }
        }
    }
    ?>
    <a href="<?php echo htmlspecialchars($redirect_url); ?>">Kembali ke Dashboard</a>
    <a href="logout.php" style="margin-left: 15px;">Logout</a>
</body>
</html>