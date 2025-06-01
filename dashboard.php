<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - PlantPals</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background:rgb(239, 249, 239);
      color: #2f5d3a;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      font-size: 16px;
    }

    a {
      color: #E5989B;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    header {
      background-color: #E5989B
      color: white;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    }

    header h1 {
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    header h1 span.emoji {
      font-size: 2.4rem;
    }

    .logout-btn {
      background: white;
      color: #E5989B;
      border: none;
      padding: 8px 16px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .logout-btn:hover {
      background-color:rgb(182, 88, 117);
      color: white;
    }

    .container {
      display: flex;
      flex: 1;
      min-height: calc(100vh - 70px);
    }

    nav.sidebar {
      width: 220px;
      background-color:rgb(228, 250, 228);
      padding: 30px 20px;
      border-right: 2px solidrgb(217, 195, 206);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    nav.sidebar a {
      font-weight: 600;
      padding: 12px 15px;
      border-radius: 8px;
      color: #2f5d3a;
      background-color: transparent;
      transition: background-color 0.25s ease;
    }

    nav.sidebar a.active,
    nav.sidebar a:hover {
      background-color: #E5989B;
      color: white;
    }

    main.content {
      flex: 1;
      padding: 30px 40px;
      overflow-y: auto;
    }

    main.content h2 {
      margin-bottom: 25px;
      font-size: 2rem;
      font-weight: 700;
    }

    .search-bar {
      margin-bottom: 30px;
      display: flex;
      gap: 10px;
      align-items: center;
      max-width: 1000px;
    }

    .search-bar input {
      flex: 1;
      padding: 10px 14px;
      border: 1px solid #c3d9c3;
      border-radius: 8px;
      font-size: 1rem;
      outline: none;
    }

    .search-bar button {
      padding: 10px 16px;
      background-color: #E5989B;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .search-bar button:hover {
      background-color: rgb(182, 88, 117);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }

    .card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 6px 12px rgba(76, 175, 80, 0.2);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform 0.25s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 24px rgb(252, 244, 248);
    }

    .card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    .card-content {
      padding: 20px 18px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .card-content h3 {
      margin-bottom: 10px;
      color: #2f5d3a;
    }

    .card-content p {
      flex: 1;
      color: #555;
      font-size: 0.9rem;
      line-height: 1.3;
      margin-bottom: 15px;
    }

    .card-content button {
      align-self: flex-start;
      background-color: #E5989B;
      border: none;
      color: white;
      padding: 10px 18px;
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .card-content button:hover {
      background-color: rgb(182, 88, 117);
    }

    footer {
      text-align: center;
      padding: 15px 0;
      font-size: 0.9rem;
      color: #777;
      background-color:rgb(242, 230, 234);
      border-top: 1px solidrgb(217, 195, 208);
    }

    @media (max-width: 600px) {
      .container {
        flex-direction: column;
      }

      nav.sidebar {
        width: 100%;
        flex-direction: row;
        overflow-x: auto;
        border-right: none;
        border-bottom: 2px solid rgb(252, 244, 248);
      }

      nav.sidebar a {
        flex: 1;
        text-align: center;
        padding: 12px 10px;
        font-size: 0.9rem;
      }

      main.content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <header>
    <h1><span class="emoji">🌿</span> PlantPals</h1>
    <form action="logout.php" method="post" style="margin:0;">
      <button class="logout-btn" type="submit" name="logout">Logout (<?php echo $username; ?>)</button>
    </form>
  </header>

  <div class="container">
    <nav class="sidebar">
      <a href="#" class="active">Home</a>
      <a href="#">Product</a>
      <a href="#">Profile</a>
      <a href="#">Orders</a>
      <a href="#">Contact</a>
    </nav>

    <main class="content">
      <h2>PlantPals.</h2>
      <form class="search-bar" action="search.php" method="get">
        <input type="text" name="q" placeholder="Search..." />
        <button type="submit">🔍</button>
      </form>

      <div class="grid">
<div class="card">
    <img src="assets/rose.jpg" alt="Rose" />
    <div class="card-content">
      <h3>Pink Rose</h3>
      <p>Tanaman tropis dengan daun besar berlubang yang aesthetic.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/sakura.jpg" alt="Sakura" />
    <div class="card-content">
      <h3>Sakura</h3>
      <p>Tanaman kecil berduri yang mudah dirawat dan lucu.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/tulip.jpg" alt="Tulip" />
    <div class="card-content">
      <h3>Tulip</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/lvalley.jpg" alt="lvalley" />
    <div class="card-content">
      <h3>Lily of The Valley</h3>
      <p>Nama ilmiah : Convallaria majalis</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/succulent.jpg" alt="succulent" />
    <div class="card-content">
      <h3>Succulent</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/jasmine.jpg" alt="jasmine" />
    <div class="card-content">
      <h3>Jasmine</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/dahlia.jpg" alt="dahlia" />
    <div class="card-content">
      <h3>Dahlia</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/peony.jpg" alt="peony" />
    <div class="card-content">
      <h3>Peony</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/dandelion.jpg" alt="dandelion" />
    <div class="card-content">
      <h3>Dandelion</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/lily.jpg" alt="lily" />
    <div class="card-content">
      <h3>Lily</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/lavender.jpg" alt="lavender" />
    <div class="card-content">
      <h3>Lavender</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/edelweis.jpg" alt="edelweis" />
    <div class="card-content">
      <h3>Edelweis</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/sun.jpg" alt="sun" />
    <div class="card-content">
      <h3>Sun Flowers</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/iris.jpg" alt="iris" />
    <div class="card-content">
      <h3>Iris</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/lotus.jpg" alt="lotus" />
    <div class="card-content">
      <h3>Lotus</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/gardenia.jpg" alt="gardenia" />
    <div class="card-content">
      <h3>Gardenia</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/snowdrop.jpg" alt="snowdrop" />
    <div class="card-content">
      <h3>Snowdrop</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/orchid.jpg" alt="orchid" />
    <div class="card-content">
      <h3>Orchid</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/aster.jpg" alt="aster" />
    <div class="card-content">
      <h3>Aster</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/ixora.jpg" alt="ixora" />
    <div class="card-content">
      <h3>Ixora</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/calendula.jpg" alt="calendula" />
    <div class="card-content">
      <h3>Calendula</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

    <div class="card">
    <img src="assets/delphinium.jpg" alt="delphinium" />
    <div class="card-content">
      <h3>Delphinium</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>

  <div class="card">
    <img src="assets/saffron.jpg" alt="saffron" />
    <div class="card-content">
      <h3>Saffron</h3>
      <p>Tanaman mini dengan bentuk unik, cocok untuk dekorasi meja.</p>
      <button>See more</button>
    </div>
  </div>
</div>

  <footer>
    <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
  </footer>
</body>
</html>

