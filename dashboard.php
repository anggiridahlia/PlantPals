<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
$username = htmlspecialchars($_SESSION['username']);

// Tentukan halaman yang akan dimuat berdasarkan parameter 'page' di URL
$page = isset($_GET['page']) ? $_GET['page'] : 'home'; // Default ke 'home' jika tidak ada parameter
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - PlantPals</title>
  <style>
    /* CSS yang sudah ada tetap sama, kecuali penambahan atau modifikasi kecil untuk active state */
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
      color: #E5989B; /* Warna link default */
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    header {
      background-color: #E5989B; /* Diperbaiki: tanda titik koma */
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
      /* min-height: calc(100vh - 70px); */ /* Dihapus atau disesuaikan jika footer ada */
    }

    nav.sidebar {
      width: 220px;
      background-color:rgb(228, 250, 228);
      padding: 30px 20px;
      border-right: 2px solid rgb(217, 195, 206); /* Diperbaiki: tanda titik koma */
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
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 6px 12px rgba(76, 175, 80, 0.2);
      padding: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      max-width: 300px;
      margin: 10px;
      transition: transform 0.25s ease;
      overflow: hidden;
    }

    .see-more-btn {
      background-color: #f0a5a5;
      border: none;
      padding: 10px 15px;
      color: white;
      border-radius: 6px;
      margin: 10px auto;
      cursor: pointer;
      width: fit-content;
    }

    .see-more-btn:hover {
      background-color: #e18b8b;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 24px rgb(252, 244, 248);
    }

    .card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
    }

    .card-content {
      max-height: 120px; /* batas tinggi konten awal */
      overflow: hidden;
      position: relative;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 15px;
      transition: max-height 0.3s ease;
    }

    .card-content::after {
      content: "";
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 10px;
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

    .card button {
      background-color: #f0a5a5;
      border: none;
      padding: 10px 15px;
      color: white;
      border-radius: 6px;
      margin-top: 10px;
      cursor: pointer;
    }

    .card button:hover {
      background-color: #e18b8b;
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
      border-top: 1px solid rgb(217, 195, 208); /* Diperbaiki: tanda titik koma */
    }

    /* CSS Tambahan untuk konten halaman */
    .page-content {
        padding: 20px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px; /* Jarak dengan footer */
    }

    .page-content h3 {
        color: #3a5a20;
        margin-bottom: 15px;
        font-size: 1.5rem;
    }

    .page-content p {
        line-height: 1.6;
        margin-bottom: 10px;
    }

    .product-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .product-item {
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        background-color: #f9f9f9;
    }

    .product-item img {
        max-width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .order-list {
        list-style: none;
        padding: 0;
    }

    .order-list li {
        background-color: #f0f8f0;
        border: 1px solid #c3d9c3;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .profile-info p strong {
        display: inline-block;
        width: 120px;
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
        padding: 10px;
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
      <a href="dashboard.php?page=home" class="<?php echo ($page == 'home') ? 'active' : ''; ?>">Home</a>
      <a href="dashboard.php?page=products" class="<?php echo ($page == 'products') ? 'active' : ''; ?>">Product</a>
      <a href="dashboard.php?page=profile" class="<?php echo ($page == 'profile') ? 'active' : ''; ?>">Profile</a>
      <a href="dashboard.php?page=orders" class="<?php echo ($page == 'orders') ? 'active' : ''; ?>">Orders</a>
      <a href="dashboard.php?page=contact" class="<?php echo ($page == 'contact') ? 'active' : ''; ?>">Contact</a>
    </nav>

    <main class="content">
      <?php
      if ($page == 'home') {
          // Konten Home (Search bar dan grid bunga)
          ?>
          <h2>PlantPals</h2>
          <form class="search-bar" action="dashboard.php" method="get">
            <input type="hidden" name="page" value="home" /> <input type="text" id="searchInput" name="q" placeholder="Search..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
            <button type="submit">🔍</button>
          </form>

          <div id="flowerGrid" class="grid">
          <?php
          // Data bunga dalam array PHP (lebih mudah dikelola dan difilter)
          $flowers = [
              [
                  "name" => "Pink Rose",
                  "img" => "assets/rose.jpg",
                  "scientific" => "Rosa",
                  "family" => "Rosaceae",
                  "description" => "Tanaman semak berbunga yang populer dengan warna bunga pink yang melambangkan cinta dan keanggunan.",
                  "habitat" => "Tumbuh baik di daerah beriklim sedang hingga tropis, membutuhkan sinar matahari penuh.",
                  "care" => "Siram 2-3 kali seminggu, dan potong daun yang kering secara rutin untuk menjaga kesehatan tanaman.",
                  "fact" => "Simbol cinta dan keanggunan, sering digunakan dalam seni dan budaya sebagai lambang kasih sayang."
              ],
              [
                  "name" => "Sakura",
                  "img" => "assets/sakura.jpg",
                  "scientific" => "Prunus serrulata",
                  "family" => "Rosaceae",
                  "description" => "Bunga berwarna merah muda yang mekar di musim semi dan menjadi simbol kecantikan yang sementara.",
                  "habitat" => "Ditemukan di daerah beriklim sedang seperti Jepang, Korea, dan China.",
                  "care" => "Tanam di daerah sejuk, siram teratur, dan butuh sinar matahari langsung.",
                  "fact" => "Mekar hanya dalam waktu singkat saat musim semi, simbol kecantikan yang fana di budaya Jepang."
              ],
              [
                  "name" => "Tulip",
                  "img" => "assets/tulip.jpg",
                  "scientific" => "Tulipa spp.",
                  "family" => "Liliaceae",
                  "description" => "Bunga dengan kelopak berwarna-warni, simbol musim semi di Belanda.",
                  "habitat" => "Tumbuh di daerah dengan musim dingin yang jelas; populer di taman Eropa.",
                  "care" => "Simpan di tempat sejuk, siram secukupnya, tanah jangan terlalu lembap.",
                  "fact" => "Berasal dari Asia Tengah tapi sangat identik dengan Belanda."
              ],
              [
                "name" => "Lily of The Valley",
                "img" => "assets/lvalley.jpg",
                "scientific" => "Convallaria majalis",
                "family" => "Asparagaceae",
                "description" => "Bunga putih kecil berbentuk lonceng dengan aroma manis.",
                "habitat" => "Hutan-hutan teduh dan beriklim sedang.",
                "care" => "Suka tempat teduh, tanah lembap, dan tidak perlu sering disiram.",
                "fact" => "Meskipun sangat harum dan cantik, bunga ini beracun jika tertelan."
              ],
              [
                "name" => "Succulent",
                "img" => "assets/succulent.jpg",
                "scientific" => "Beragam (contoh: Echeveria, Aloe, Crassula)",
                "family" => "Bervariasi (umumnya Crassulaceae atau Asphodelaceae)",
                "description" => "Tanaman yang dapat menyimpan air di daun, cocok untuk daerah kering.",
                "habitat" => "Umum di wilayah gurun dan semi-kering seperti Afrika, Amerika Selatan.",
                "care" => "Butuh cahaya terang, siram seminggu sekali atau saat tanah benar-benar kering.",
                "fact" => "Perawatannya sangat mudah dan cocok untuk pemula."
              ],
              [
                "name" => "Jasmine",
                "img" => "assets/jasmine.jpg",
                "scientific" => "Jasminum sambac",
                "family" => "Oleaceae",
                "description" => "Tanaman merambat atau semak dengan daun hijau kecil dan bunga putih kecil beraroma sangat harum, biasanya berbentuk bintang.",
                "habitat" => "Tumbuh subur di daerah tropis dan subtropis Asia Selatan dan Asia Tenggara, menyukai tanah subur dan kelembapan sedang.",
                "care" => "Sinar matahari penuh, tanah lembap, dan rajin dipangkas agar tanaman rimbun.",
                "fact" => "Aromanya populer untuk parfum dan teh tradisional."
              ],
              [
                "name" => "Dahlia",
                "img" => "assets/dahlia.jpg",
                "scientific" => "Dahlia pinnata",
                "family" => "Asteraceae",
                "description" => "Tanaman semak dengan bunga besar beraneka warna dan bentuk, dari yang sederhana hingga bergelombang dan berlapis banyak.",
                "habitat" => "Asli dari Meksiko, tumbuh baik di dataran tinggi beriklim sedang, membutuhkan tanah subur dan drainase baik.",
                "care" => "Siram teratur, sinar matahari penuh, pupuk bulanan untuk pertumbuhan optimal.",
                "fact" => "Bunga nasional Meksiko dan sangat populer sebagai tanaman hias."
              ],
              [
                "name" => "Peony",
                "img" => "assets/peony.jpg",
                "scientific" => "Paeonia spp.",
                "family" => "Paeoniaceae",
                "description" => "Semak dengan bunga besar, berlapis tebal, dan warna cerah mulai dari merah muda, merah, hingga putih. Daunnya lebar dan hijau tua.",
                "habitat" => "Tumbuh di daerah beriklim sedang hingga dingin di Asia dan Eropa, suka tanah yang subur dan gembur.",
                "care" => "Tempat terbuka, tanah subur, siram rutin saat musim tumbuh.",
                "fact" => "Bisa hidup lebih dari 100 tahun dan dipercaya membawa keberuntungan."
              ],
              [
                "name" => "Dandelion",
                "img" => "assets/dandelion.jpg",
                "scientific" => "Taraxacum officinale",
                "family" => "Asteraceae",
                "description" => "Tanaman kecil dengan bunga kuning cerah berbentuk cakram dan daun bergerigi. Biji berbulu halus yang mudah terbawa angin.",
                "habitat" => "Tersebar luas di daerah beriklim sedang hingga dingin, sering ditemukan di padang rumput dan tepi jalan.",
                "care" => "Tahan banting, cukup sinar matahari dan air, tumbuh hampir di semua jenis tanah.",
                "fact" => "Biji terbang dengan angin, melambangkan harapan dan kebebasan."
              ],
              [
                "name" => "Lily",
                "img" => "assets/lily.jpg",
                "scientific" => "Lilium spp.",
                "family" => "Liliaceae",
                "description" => "Tanaman berumbi dengan bunga besar, berbentuk terompet atau corong, warna dan pola beragam. Daunnya ramping dan panjang.",
                "habitat" => "Tumbuh di berbagai wilayah beriklim sedang, termasuk hutan, pegunungan, dan ladang terbuka.",
                "care" => "Sinar matahari sebagian, siram teratur, tanah harus dikeringkan dengan baik untuk mencegah pembusukan.",
                "fact" => "Banyak jenis dan warna, sering digunakan untuk dekorasi dan upacara keagamaan."
              ],
              [
                "name" => "Lavender",
                "img" => "assets/lavender.jpg",
                "scientific" => "Lavandula angustifolia",
                "family" => "Lamiaceae",
                "description" => "Bunga ungu kecil dengan aroma menenangkan.",
                "habitat" => "Tumbuh di daerah Mediterania, dataran tinggi kering.",
                "care" => "Banyak sinar matahari, tanah kering, jangan terlalu lembap.",
                "fact" => "Aromanya bisa mengusir nyamuk dan bikin rileks."
              ],
              [
                "name" => "Edelweis",
                "img" => "assets/edelweis.jpg",
                "scientific" => "Anaphalis javanica",
                "family" => "Asteraceae",
                "description" => "Bunga abadi yang tumbuh di pegunungan tinggi.",
                "habitat" => "Pegunungan tropis Indonesia, terutama di atas 2000 mdpl.",
                "care" => "Tanah berpasir, sinar matahari, tidak suka lembap.",
                "fact" => "Tumbuh di pegunungan tinggi, simbol cinta abadi."
              ],
              [
                "name" => "Sun Flowers",
                "img" => "assets/sun.jpg",
                "scientific" => "Helianthus annuus",
                "family" => "Asteraceae",
                "description" => "Bunga besar berwarna kuning cerah yang selalu menghadap matahari.",
                "habitat" => "Tumbuh baik di daerah dataran rendah dan terbuka.",
                "care" => "Sinar matahari penuh, air cukup, tanah subur.",
                "fact" => "Mengikuti arah matahari (heliotropisme)."
              ],
              [
                "name" => "Iris",
                "img" => "assets/iris.jpg",
                "scientific" => "Iris germanica",
                "family" => "Iridaceae",
                "description" => "Bunga dengan kelopak yang unik dan warna yang bervariasi.",
                "habitat" => "Lahan terbuka beriklim sedang, banyak ditemukan di Eropa.",
                "care" => "Sinar matahari sedang–penuh, tanah dikeringkan dengan baik.",
                "fact" => "Dinamai dari dewi pelangi Yunani."
              ],
              [
                "name" => "Lotus",
                "img" => "assets/lotus.jpg",
                "scientific" => "Nelumbo nucifera",
                "family" => "Nelumbonaceae",
                "description" => "Bunga air yang indah, sering dianggap simbol kesucian.",
                "habitat" => "Kolam dan rawa dangkal di daerah tropis dan subtropis Asia.",
                "care" => "Butuh air menggenang, sinar matahari penuh.",
                "fact" => "Bisa tumbuh di air kotor tapi bunganya bersih dan suci."
              ],
              [
                "name" => "Gardenia",
                "img" => "assets/gardenia.jpg",
                "scientific" => "Gardenia jasminoides",
                "family" => "Rubiaceae",
                "description" => "Bunga putih harum dengan kelopak tebal dan mengkilap.",
                "habitat" => "Tumbuhan asli Asia Tenggara, tumbuh di daerah teduh dan lembap.",
                "care" => "Sinar terang tapi tidak langsung, kelembapan tinggi, tanah asam.",
                "fact" => "Wangi kuat, sering dipakai untuk parfum."
              ],
              [
                "name" => "Snowdrop",
                "img" => "assets/snowdrop.jpg",
                "scientific" => "Galanthus nivalis",
                "family" => "Amaryllidaceae",
                "description" => "Bunga kecil putih yang mekar di musim dingin.",
                "habitat" => "Hutan beriklim sedang di Eropa dan Asia Barat.",
                "care" => "Tanah lembap, teduh-sejuk, siram teratur.",
                "fact" => "Mekar di akhir musim dingin atau awal semi."
              ],
              [
                "name" => "Orchid",
                "img" => "assets/orchid.jpg",
                "scientific" => "Orchidaceae (beragam spesies)",
                "family" => "Orchidaceae",
                "description" => "Bunga eksotis dengan berbagai bentuk dan warna menarik.",
                "habitat" => "Hutan tropis dan subtropis di seluruh dunia.",
                "care" => "Cahaya terang tak langsung, siram seminggu 1-2x, jangan genang air.",
                "fact" => "Tumbuh di udara, bukan di tanah (epifit)."
              ],
              [
                "name" => "Aster",
                "img" => "assets/aster.jpg",
                "scientific" => "Aster amellus",
                "family" => "Asteraceae",
                "description" => "Bunga yang serupa daisy dengan kelopak tipis dan banyak warna.",
                "habitat" => "Dataran terbuka dan kebun di daerah beriklim sedang.",
                "care" => "Sinar matahari penuh, tanah subur, air cukup.",
                "fact" => "Serupa daisy, menarik kupu-kupu."
              ],
              [
                "name" => "Ixora",
                "img" => "assets/ixora.jpg",
                "scientific" => "Ixora coccinea",
                "family" => "Rubiaceae",
                "description" => "Bunga cluster kecil berwarna cerah, dikenal sebagai bunga soka.",
                "habitat" => "Tumbuhan tropis yang tumbuh di daerah panas dan lembap.",
                "care" => "Cahaya matahari penuh, siram teratur, tahan panas.",
                "fact" => "Di Indonesia disebut 'Asoka', bunga klasik halaman rumah."
              ],
              [
                "name" => "Calendula",
                "img" => "assets/calendula.jpg",
                "scientific" => "Calendula officinalis",
                "family" => "Asteraceae",
                "description" => "Bunga oranye atau kuning cerah yang sering digunakan dalam pengobatan herbal.",
                "habitat" => "Asal Eropa Selatan dan Mediterania, tumbuh di kebun dan ladang.",
                "care" => "Sinar matahari penuh, tanah gembur, air cukup.",
                "fact" => "Biasa dipakai sebagai obat luka dan kosmetik."
              ],
              [
                "name" => "Delphinium",
                "img" => "assets/delphinium.jpg",
                "scientific" => "Delphinium elatum",
                "family" => "Ranunculaceae",
                "description" => "Bunga tinggi berwarna biru, ungu, atau putih yang mekar di musim panas.",
                "habitat" => "Pegunungan dan daerah beriklim sedang di Eropa dan Amerika Utara.",
                "care" => "Tanah subur, siram rutin, perlu penyangga karena tinggi.",
                "fact" => "Bunga tinggi yang mekar di musim panas."
              ],
              [
                "name" => "Saffron",
                "img" => "assets/saffron.jpg",
                "scientific" => "Crocus sativus",
                "family" => "Iridaceae",
                "description" => "Tanaman kecil dengan bunga ungu, putiknya menghasilkan rempah saffron.",
                "habitat" => "Tumbuh di daerah beriklim sedang dan kering, seperti Asia Barat dan Mediterania.",
                "care" => "Sinar matahari penuh, tanah kering, siram minim.",
                "fact" => "Rempah termahal di dunia, dari putik bunga."
              ]
          ];

          $filtered_flowers = [];
          $keyword = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

          if (!empty($keyword)) {
              foreach ($flowers as $flower) {
                  if (
                      stripos($flower['name'], $keyword) !== false ||
                      stripos($flower['description'], $keyword) !== false ||
                      stripos($flower['scientific'], $keyword) !== false ||
                      stripos($flower['family'], $keyword) !== false ||
                      stripos($flower['habitat'], $keyword) !== false ||
                      stripos($flower['care'], $keyword) !== false ||
                      stripos($flower['fact'], $keyword) !== false
                  ) {
                      $filtered_flowers[] = $flower;
                  }
              }
          } else {
              $filtered_flowers = $flowers;
          }

          if (empty($filtered_flowers)) {
              echo "<p>Tidak ada hasil untuk pencarian Anda.</p>";
          } else {
              foreach ($filtered_flowers as $flower) {
                  ?>
                  <div class="card">
                      <img src="<?php echo $flower['img']; ?>" alt="<?php echo $flower['name']; ?>" />
                      <div class="card-content">
                          <h3><?php echo $flower['name']; ?></h3>
                          <p><strong>Nama Ilmiah:</strong> <?php echo $flower['scientific']; ?></p>
                          <p><strong>Familia:</strong> <?php echo $flower['family']; ?></p>
                          <p><strong>Deskripsi:</strong> <?php echo $flower['description']; ?></p>
                          <p><strong>Habitat:</strong> <?php echo $flower['habitat']; ?></p>
                          <p><strong>Perawatan:</strong> <?php echo $flower['care']; ?></p>
                          <p><strong>Fakta unik:</strong> <?php echo $flower['fact']; ?></p>
                      </div>
                      <button class="see-more-btn" onclick="window.location.href='detail.html?flower=<?php echo urlencode(strtolower(str_replace(' ', '', $flower['name']))); ?>'">See more</button>
                  </div>
                  <?php
              }
          }
          ?>
          </div> <?php
      } elseif ($page == 'products') {
          // Konten halaman Produk
          ?>
          <div class="page-content">
              <h2>Produk Kami</h2>
              <p>Jelajahi berbagai pilihan tanaman hias dan produk terkait yang tersedia di PlantPals.</p>
              <div class="product-list">
                  <div class="product-item">
                      <img src="assets/rose_pot.jpg" alt="Rose in Pot">
                      <h4>Mawar Merah dalam Pot</h4>
                      <p>Harga: Rp 75.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
                  <div class="product-item">
                      <img src="assets/succulent_set.jpg" alt="Succulent Set">
                      <h4>Set Sukulen Mini</h4>
                      <p>Harga: Rp 120.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
                  <div class="product-item">
                      <img src="assets/orchid_pot.jpg" alt="Orchid in Pot">
                      <h4>Anggrek Bulan Putih</h4>
                      <p>Harga: Rp 150.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
                   <div class="product-item">
                      <img src="assets/pot_tanah.jpg" alt="Pot Tanah Liat">
                      <h4>Pot Tanah Liat Klasik</h4>
                      <p>Harga: Rp 30.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
                   <div class="product-item">
                      <img src="assets/pupuk.jpg" alt="Pupuk Organik">
                      <h4>Pupuk Organik Serbaguna</h4>
                      <p>Harga: Rp 25.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
                  <div class="product-item">
                      <img src="assets/alat_kebun.jpg" alt="Alat Kebun">
                      <h4>Set Alat Kebun Mini</h4>
                      <p>Harga: Rp 80.000</p>
                      <button class="btn" style="background-color: #4CAF50;">Beli Sekarang</button>
                  </div>
              </div>
          </div>
          <?php
      } elseif ($page == 'profile') {
          // Konten halaman Profil
          ?>
          <div class="page-content profile-info">
              <h2>Profil Pengguna</h2>
              <p>Kelola informasi akun Anda di sini.</p>
              <p><strong>Username:</strong> <?php echo $username; ?></p>
              <p><strong>Email:</strong> user@example.com</p>
              <p><strong>Bergabung Sejak:</strong> 01 Januari 2024</p>
              <p><strong>Status Akun:</strong> Aktif</p>
              <button class="btn" style="background-color: #E5989B; margin-top: 20px;">Edit Profil</button>
          </div>
          <?php
      } elseif ($page == 'orders') {
          // Konten halaman Pesanan
          ?>
          <div class="page-content">
              <h2>Pesanan Anda</h2>
              <p>Berikut adalah daftar pesanan yang telah Anda lakukan.</p>
              <ul class="order-list">
                  <li>
                      <span><strong>ID Pesanan:</strong> #2024001</span>
                      <span><strong>Tanggal:</strong> 15 Mei 2024</span>
                      <span><strong>Total:</strong> Rp 75.000</span>
                      <span><strong>Status:</strong> Selesai</span>
                  </li>
                  <li>
                      <span><strong>ID Pesanan:</strong> #2024002</span>
                      <span><strong>Tanggal:</strong> 20 April 2024</span>
                      <span><strong>Total:</strong> Rp 120.000</span>
                      <span><strong>Status:</strong> Dikirim</span>
                  </li>
                  <li>
                      <span><strong>ID Pesanan:</strong> #2024003</span>
                      <span><strong>Tanggal:</strong> 01 April 2024</span>
                      <span><strong>Total:</strong> Rp 150.000</span>
                      <span><strong>Status:</strong> Diproses</span>
                  </li>
              </ul>
              <p style="margin-top: 20px;">Tidak ada pesanan aktif saat ini.</p>
          </div>
          <?php
      } elseif ($page == 'contact') {
          // Konten halaman Kontak
          ?>
          <div class="page-content">
              <h2>Hubungi Kami</h2>
              <p>Kami siap membantu Anda. Silakan hubungi kami melalui informasi di bawah ini:</p>
              <p><strong>Email:</strong> info@plantpals.com</p>
              <p><strong>Telepon:</strong> +62 812-3456-7890</p>
              <p><strong>Alamat:</strong> Jl. Bunga Indah No. 123, Denpasar, Bali, Indonesia</p>
              <h3 style="margin-top: 30px;">Form Kontak</h3>
              <form style="display: flex; flex-direction: column; gap: 15px;">
                  <input type="text" placeholder="Nama Anda" style="padding: 10px; border: 1px solid #c3d9c3; border-radius: 5px;">
                  <input type="email" placeholder="Email Anda" style="padding: 10px; border: 1px solid #c3d9c3; border-radius: 5px;">
                  <textarea placeholder="Pesan Anda" rows="5" style="padding: 10px; border: 1px solid #c3d9c3; border-radius: 5px; resize: vertical;"></textarea>
                  <button type="submit" class="btn" style="background-color: #4CAF50; width: fit-content; padding: 10px 20px;">Kirim Pesan</button>
              </form>
          </div>
          <?php
      } else {
          // Jika parameter page tidak dikenal, kembali ke home
          ?>
          <div class="page-content">
              <h2>Halaman Tidak Ditemukan</h2>
              <p>Halaman yang Anda cari tidak tersedia. Silakan kembali ke <a href="dashboard.php?page=home">Home</a>.</p>
          </div>
          <?php
      }
      ?>
    </main>
  </div>

  <footer>
    <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
  </footer>

<script>
  // Script untuk mempertahankan fungsi search
  // Karena sekarang form search mengarah ke dashboard.php?page=home
  // Fungsi filtering akan berjalan di sisi server (PHP)

  // Kode JS toggleDescription untuk card di Home tetap relevan jika Anda ingin mengaktifkannya
  // saat ini saya melihat ada tombol see more yang mengarah ke detail.html,
  // dan ada fungsi toggleDescription yang tidak terpakai (atau terpakai di bagian lain).
  // Jika Anda ingin card di dashboard bisa expand/collapse, Anda bisa uncomment dan sesuaikan fungsi ini.

  // function toggleDescription(button) {
  //   const cardContent = button.previousElementSibling; // Ini adalah .card-content
  //   if (cardContent.style.maxHeight === 'none' || cardContent.style.maxHeight === '') {
  //     cardContent.style.maxHeight = cardContent.scrollHeight + 'px'; // Expand
  //     button.textContent = 'See Less';
  //   } else {
  //     cardContent.style.maxHeight = '120px'; // Collapse
  //     button.textContent = 'See More';
  //   }
  // }

</script>
</body>
</html>