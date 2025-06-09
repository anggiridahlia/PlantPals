<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>PlantPals - Selamat Datang</title>
  <style>
    body {
      background: linear-gradient(to bottom right, #d9f2d9, #f0fff0);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      color: #2f5d3a;
      text-align: center;
      padding: 20px;
    }
    h1 {
      font-size: 3rem;
      margin-bottom: 10px;
    }
    p {
      font-size: 1.3rem;
      margin-bottom: 40px;
      max-width: 400px;
    }
    .btn-group {
      display: flex;
      gap: 20px;
    }
    .btn {
      padding: 14px 28px;
      border-radius: 8px;
      background-color: #4caf50;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
      text-decoration: none;
      box-shadow: 0 5px 10px rgba(76,175,80,0.4);
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    .btn:hover {
      background-color: #388e3c;
      box-shadow: 0 8px 14px rgba(56,142,60,0.6);
    }
  </style>
</head>
<body>
  <h1>🌿 PlantPals</h1>
  <p>Temukan, rawat, dan beli tanaman hias favoritmu dengan mudah!</p>
  <div class="btn-group">
    <a href="login.php" class="btn">Masuk</a>
    <a href="register.php" class="btn">Daftar</a>
  </div>
</body>
</html>