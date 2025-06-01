<?php
session_start();

if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!file_exists("users.txt")) {
        $error = "Belum ada user yang terdaftar, silakan daftar dulu.";
    } else {
        $users = file("users.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $valid_login = false;

        foreach ($users as $user) {
            list($user_name, $user_pass) = explode(",", $user);
            if ($username === $user_name && $password === $user_pass) {
                $valid_login = true;
                break;
            }
        }

        if ($valid_login) {
            $_SESSION['username'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Login - PlantPals</title>
  <style>
    body {
      background: #e6f0db;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 30px 40px;
      border-radius: 10px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      width: 350px;
      text-align: center;
    }
    h2 {
      color: #3a5a20;
      margin-bottom: 25px;
    }
    input {
      width: 100%;
      padding: 12px;
      margin: 12px 0;
      border-radius: 6px;
      border: 1.5px solid #9bbf93;
      font-size: 16px;
      transition: border-color 0.3s ease;
    }
    input:focus {
      outline: none;
      border-color: #4caf50;
      box-shadow: 0 0 5px #4caf50;
    }
    button {
      width: 100%;
      padding: 14px;
      background-color: #4caf50;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: bold;
      font-size: 18px;
      cursor: pointer;
      transition: background-color 0.3s ease;
      margin-top: 15px;
    }
    button:hover {
      background-color: #3b7d33;
    }
    .error {
      color: #d93025;
      margin-top: 12px;
    }
    a {
      color: #4caf50;
      text-decoration: none;
      font-weight: 600;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Login PlantPals</h2>
    <form method="post" autocomplete="off">
      <input type="text" name="username" placeholder="Username" required />
      <input type="password" name="password" placeholder="Password" required />
      <button type="submit">Masuk</button>
    </form>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <p style="margin-top: 20px;">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
  </div>
</body>
</html>
