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
      max-height: 120px; /*batas tinggi konten awal */
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
      <a href="#" class="active">Home</a>
      <a href="#">Product</a>
      <a href="#">Profile</a>
      <a href="#">Orders</a>
      <a href="#">Contact</a>
    </nav>

    <main class="content">
      <h2>PlantPals</h2>
      <form class="search-bar" action="search.php" method="get">
        <input type="text" id="searchInput" name="q" placeholder="Search..." />
        <button type="submit">🔍</button>
      </form>


<div id="flowerGrid" class="grid">
<div class="card">
  <img src="assets/rose.jpg" alt="Pink Rose" />
  <div class="card-content">
    <h3>Pink Rose</h3>
    <p><strong>Nama Ilmiah:</strong> Rosa</p>
    <p><strong>Familia:</strong> Rosaceae</p>
    <p><strong>Deskripsi:</strong> Tanaman semak berbunga yang populer dengan warna bunga pink yang melambangkan cinta dan keanggunan.</p>
    <p><strong>Habitat:</strong> Tumbuh baik di daerah beriklim sedang hingga tropis, membutuhkan sinar matahari penuh.</p>
    <p><strong>Perawatan:</strong> Siram 2-3 kali seminggu, dan potong daun yang kering secara rutin untuk menjaga kesehatan tanaman.</p>
    <p><strong>Fakta unik:</strong> Simbol cinta dan keanggunan, sering digunakan dalam seni dan budaya sebagai lambang kasih sayang.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=pinkrose'">See more</button>
</div>

<div class="card">
  <img src="assets/sakura.jpg" alt="Sakura" />
  <div class="card-content">
    <h3>Sakura</h3>
    <p><strong>Nama Ilmiah:</strong> Prunus serrulata</p>
    <p><strong>Famili:</strong> Rosaceae</p>
    <p><strong>Fakta Unik:</strong> Mekar hanya dalam waktu singkat saat musim semi, simbol kecantikan yang fana di budaya Jepang.</p>
    <p><strong>Habitat:</strong> Ditemukan di daerah beriklim sedang seperti Jepang, Korea, dan China.</p>
    <p><strong>Perawatan:</strong> Tanam di daerah sejuk, siram teratur, dan butuh sinar matahari langsung.</p>
    </div>
    <button class="see-more-btn" onclick="window.location.href='detail.html?flower=sakura'">See more</button>
</div>

<div class="card">
  <img src="assets/tulip.jpg" alt="Tulip" />
  <div class="card-content">
    <h3>Tulip</h3>
    <p><strong>Nama Ilmiah:</strong> Tulipa spp.</p>
    <p><strong>Famili:</strong> Liliaceae</p>
    <p><strong>Fakta Unik:</strong> Berasal dari Asia Tengah tapi sangat identik dengan Belanda.</p>
    <p><strong>Habitat:</strong> Tumbuh di daerah dengan musim dingin yang jelas; populer di taman Eropa.</p>
    <p><strong>Perawatan:</strong> Simpan di tempat sejuk, siram secukupnya, tanah jangan terlalu lembap.</p>
    </div>
    <button class="see-more-btn" onclick="window.location.href='detail.html?flower=tulip'">See more</button>
</div>

<div class="card">
  <img src="assets/lvalley.jpg" alt="Lily of the Valley" />
  <div class="card-content">
    <h3>Lily of The Valley</h3>
    <p><strong>Nama Ilmiah:</strong> Convallaria majalis</p>
    <p><strong>Famili:</strong> Asparagaceae</p>
    <p><strong>Fakta Unik:</strong> Meskipun sangat harum dan cantik, bunga ini beracun jika tertelan.</p>
    <p><strong>Habitat:</strong> Biasanya tumbuh di hutan-hutan teduh dan beriklim sedang.</p>
    <p><strong>Perawatan:</strong> Suka tempat teduh, tanah lembap, dan tidak perlu sering disiram.</p>
    </div>
    <button class="see-more-btn" onclick="window.location.href='detail.html?flower=lvalley'">See more</button>
</div>

<div class="card">
  <img src="assets/succulent.jpg" alt="Succulent" />
  <div class="card-content">
    <h3>Succulent</h3>
    <p><strong>Nama Ilmiah:</strong> Beragam (contoh: Echeveria, Aloe, Crassula)</p>
    <p><strong>Famili:</strong> Bervariasi tergantung spesies (umumnya Crassulaceae atau Asphodelaceae)</p>
    <p><strong>Fakta Unik:</strong> Dapat menyimpan air di daun, cocok untuk daerah kering dan perawatannya sangat mudah.</p>
    <p><strong>Habitat:</strong> Umum di wilayah gurun dan semi-kering seperti Afrika, Amerika Selatan.</p>
    <p><strong>Perawatan:</strong> Butuh cahaya terang, siram seminggu sekali atau saat tanah benar-benar kering.</p>
   </div>
    <button class="see-more-btn" onclick="window.location.href='detail.html?flower=lvalley'">See more</button>
</div>


<div class="card">
  <img src="assets/jasmine.jpg" alt="Jasmine" />
  <div class="card-content">
    <h3>Jasmine</h3>
    <p><strong>Nama ilmiah:</strong> Jasminum sambac</p>
    <p><strong>Familia:</strong> Oleaceae</p>
    <p><strong>Deskripsi:</strong> Tanaman merambat atau semak dengan daun hijau kecil dan bunga putih kecil beraroma sangat harum, biasanya berbentuk bintang.</p>
    <p><strong>Habitat:</strong> Tumbuh subur di daerah tropis dan subtropis Asia Selatan dan Asia Tenggara, menyukai tanah subur dan kelembapan sedang.</p>
    <p><strong>Fakta unik:</strong> Aromanya populer untuk parfum dan teh tradisional.</p>
    <p><strong>Perawatan:</strong> Sinar matahari penuh, tanah lembap, dan rajin dipangkas agar tanaman rimbun.</p>
  </div>
  <button class="see-more-btn" onclick="toggleDescription(this)">See More</button>
</div>

<div class="card">
  <img src="assets/dahlia.jpg" alt="Dahlia" />
  <div class="card-content">
    <h3>Dahlia</h3>
    <p><strong>Nama ilmiah:</strong> Dahlia pinnata</p>
    <p><strong>Familia:</strong> Asteraceae</p>
    <p><strong>Deskripsi:</strong> Tanaman semak dengan bunga besar beraneka warna dan bentuk, dari yang sederhana hingga bergelombang dan berlapis banyak.</p>
    <p><strong>Habitat:</strong> Asli dari Meksiko, tumbuh baik di dataran tinggi beriklim sedang, membutuhkan tanah subur dan drainase baik.</p>
    <p><strong>Fakta unik:</strong> Bunga nasional Meksiko dan sangat populer sebagai tanaman hias.</p>
    <p><strong>Perawatan:</strong> Siram teratur, sinar matahari penuh, pupuk bulanan untuk pertumbuhan optimal.</p>
  </div>
  <button class="see-more-btn" onclick="toggleDescription(this)">See More</button>
</div>

<div class="card">
  <img src="assets/peony.jpg" alt="Peony" />
  <div class="card-content">
    <h3>Peony</h3>
    <p><strong>Nama ilmiah:</strong> Paeonia spp.</p>
    <p><strong>Familia:</strong> Paeoniaceae</p>
    <p><strong>Deskripsi:</strong> Semak dengan bunga besar, berlapis tebal, dan warna cerah mulai dari merah muda, merah, hingga putih. Daunnya lebar dan hijau tua.</p>
    <p><strong>Habitat:</strong> Tumbuh di daerah beriklim sedang hingga dingin di Asia dan Eropa, suka tanah yang subur dan gembur.</p>
    <p><strong>Fakta unik:</strong> Bisa hidup lebih dari 100 tahun dan dipercaya membawa keberuntungan.</p>
    <p><strong>Perawatan:</strong> Tempat terbuka, tanah subur, siram rutin saat musim tumbuh.</p>
  </div>
  <button class="see-more-btn" onclick="toggleDescription(this)">See More</button>
</div>

<div class="card">
  <img src="assets/dandelion.jpg" alt="Dandelion" />
  <div class="card-content">
    <h3>Dandelion</h3>
    <p><strong>Nama ilmiah:</strong> Taraxacum officinale</p>
    <p><strong>Familia:</strong> Asteraceae</p>
    <p><strong>Deskripsi:</strong> Tanaman kecil dengan bunga kuning cerah berbentuk cakram dan daun bergerigi. Biji berbulu halus yang mudah terbawa angin.</p>
    <p><strong>Habitat:</strong> Tersebar luas di daerah beriklim sedang hingga dingin, sering ditemukan di padang rumput dan tepi jalan.</p>
    <p><strong>Fakta unik:</strong> Biji terbang dengan angin, melambangkan harapan dan kebebasan.</p>
    <p><strong>Perawatan:</strong> Tahan banting, cukup sinar matahari dan air, tumbuh hampir di semua jenis tanah.</p>
  </div>
  <button class="see-more-btn" onclick="toggleDescription(this)">See More</button>
</div>

<div class="card">
  <img src="assets/lily.jpg" alt="Lily" />
  <div class="card-content">
    <h3>Lily</h3>
    <p><strong>Nama ilmiah:</strong> Lilium spp.</p>
    <p><strong>Familia:</strong> Liliaceae</p>
    <p><strong>Deskripsi:</strong> Tanaman berumbi dengan bunga besar, berbentuk terompet atau corong, warna dan pola beragam. Daunnya ramping dan panjang.</p>
    <p><strong>Habitat:</strong> Tumbuh di berbagai wilayah beriklim sedang, termasuk hutan, pegunungan, dan ladang terbuka.</p>
    <p><strong>Fakta unik:</strong> Banyak jenis dan warna, sering digunakan untuk dekorasi dan upacara keagamaan.</p>
    <p><strong>Perawatan:</strong> Sinar matahari sebagian, siram teratur, tanah harus dikeringkan dengan baik untuk mencegah pembusukan.</p>
  </div>
  <button class="see-more-btn" onclick="toggleDescription(this)">See More</button>
</div>



<!-- Lavender -->
<div class="card">
  <img src="assets/lavender.jpg" alt="Lavender" />
  <div class="card-content">
    <h3>Lavender</h3>
    <p><strong>Nama Ilmiah:</strong> Lavandula angustifolia</p>
    <p><strong>Famili:</strong> Lamiaceae</p>
    <p><strong>Fakta Unik:</strong> Aromanya bisa mengusir nyamuk dan bikin rileks.</p>
    <p><strong>Habitat:</strong> Tumbuh di daerah Mediterania, dataran tinggi kering.</p>
    <p><strong>Perawatan:</strong> Banyak sinar matahari, tanah kering, jangan terlalu lembap.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=lavender'">See more</button>
</div>

<!-- Edelweis -->
<div class="card">
  <img src="assets/edelweis.jpg" alt="Edelweis" />
  <div class="card-content">
    <h3>Edelweis</h3>
    <p><strong>Nama Ilmiah:</strong> Anaphalis javanica</p>
    <p><strong>Famili:</strong> Asteraceae</p>
    <p><strong>Fakta Unik:</strong> Tumbuh di pegunungan tinggi, simbol cinta abadi.</p>
    <p><strong>Habitat:</strong> Pegunungan tropis Indonesia, terutama di atas 2000 mdpl.</p>
    <p><strong>Perawatan:</strong> Tanah berpasir, sinar matahari, tidak suka lembap.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=edelweis'">See more</button>
</div>

<!-- Sun Flowers -->
<div class="card">
  <img src="assets/sun.jpg" alt="Sun Flowers" />
  <div class="card-content">
    <h3>Sun Flowers</h3>
    <p><strong>Nama Ilmiah:</strong> Helianthus annuus</p>
    <p><strong>Famili:</strong> Asteraceae</p>
    <p><strong>Fakta Unik:</strong> Mengikuti arah matahari (heliotropisme).</p>
    <p><strong>Habitat:</strong> Tumbuh baik di daerah dataran rendah dan terbuka.</p>
    <p><strong>Perawatan:</strong> Sinar matahari penuh, air cukup, tanah subur.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=sunflowers'">See more</button>
</div>

<!-- Iris -->
<div class="card">
  <img src="assets/iris.jpg" alt="Iris" />
  <div class="card-content">
    <h3>Iris</h3>
    <p><strong>Nama Ilmiah:</strong> Iris germanica</p>
    <p><strong>Famili:</strong> Iridaceae</p>
    <p><strong>Fakta Unik:</strong> Dinamai dari dewi pelangi Yunani.</p>
    <p><strong>Habitat:</strong> Lahan terbuka beriklim sedang, banyak ditemukan di Eropa.</p>
    <p><strong>Perawatan:</strong> Sinar matahari sedang–penuh, tanah dikeringkan dengan baik.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=iris'">See more</button>
</div>

<!-- Lotus -->
<div class="card">
  <img src="assets/lotus.jpg" alt="Lotus" />
  <div class="card-content">
    <h3>Lotus</h3>
    <p><strong>Nama Ilmiah:</strong> Nelumbo nucifera</p>
    <p><strong>Famili:</strong> Nelumbonaceae</p>
    <p><strong>Fakta Unik:</strong> Bisa tumbuh di air kotor tapi bunganya bersih dan suci.</p>
    <p><strong>Habitat:</strong> Kolam dan rawa dangkal di daerah tropis dan subtropis Asia.</p>
    <p><strong>Perawatan:</strong> Butuh air menggenang, sinar matahari penuh.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=lotus'">See more</button>
</div>

 <!-- Gardenia -->
<div class="card">
  <img src="assets/gardenia.jpg" alt="Gardenia" />
  <div class="card-content">
    <h3>Gardenia</h3>
    <p><strong>Nama Ilmiah:</strong> Gardenia jasminoides</p>
    <p><strong>Famili:</strong> Rubiaceae</p>
    <p><strong>Fakta Unik:</strong> Wangi kuat, sering dipakai untuk parfum.</p>
    <p><strong>Habitat:</strong> Tumbuhan asli Asia Tenggara, tumbuh di daerah teduh dan lembap.</p>
    <p><strong>Perawatan:</strong> Sinar terang tapi tidak langsung, kelembapan tinggi, tanah asam.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=gardenia'">See more</button>
</div>

<!-- Snowdrop -->
<div class="card">
  <img src="assets/snowdrop.jpg" alt="Snowdrop" />
  <div class="card-content">
    <h3>Snowdrop</h3>
    <p><strong>Nama Ilmiah:</strong> Galanthus nivalis</p>
    <p><strong>Famili:</strong> Amaryllidaceae</p>
    <p><strong>Fakta Unik:</strong> Mekar di akhir musim dingin atau awal semi.</p>
    <p><strong>Habitat:</strong> Hutan beriklim sedang di Eropa dan Asia Barat.</p>
    <p><strong>Perawatan:</strong> Tanah lembap, teduh-sejuk, siram teratur.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=snowdrop'">See more</button>
</div>

<!-- Orchid (Anggrek) -->
<div class="card">
  <img src="assets/orchid.jpg" alt="Orchid" />
  <div class="card-content">
    <h3>Orchid</h3>
    <p><strong>Nama Ilmiah:</strong> Orchidaceae (beragam spesies)</p>
    <p><strong>Famili:</strong> Orchidaceae</p>
    <p><strong>Fakta Unik:</strong> Tumbuh di udara, bukan di tanah (epifit).</p>
    <p><strong>Habitat:</strong> Hutan tropis dan subtropis di seluruh dunia.</p>
    <p><strong>Perawatan:</strong> Cahaya terang tak langsung, siram seminggu 1-2x, jangan genang air.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=orchid'">See more</button>
</div>

<!-- Aster -->
<div class="card">
  <img src="assets/aster.jpg" alt="Aster" />
  <div class="card-content">
    <h3>Aster</h3>
    <p><strong>Nama Ilmiah:</strong> Aster amellus</p>
    <p><strong>Famili:</strong> Asteraceae</p>
    <p><strong>Fakta Unik:</strong> Serupa daisy, menarik kupu-kupu.</p>
    <p><strong>Habitat:</strong> Dataran terbuka dan kebun di daerah beriklim sedang.</p>
    <p><strong>Perawatan:</strong> Sinar matahari penuh, tanah subur, air cukup.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=aster'">See more</button>
</div>

<!-- Ixora -->
<div class="card">
  <img src="assets/ixora.jpg" alt="Ixora" />
  <div class="card-content">
    <h3>Ixora</h3>
    <p><strong>Nama Ilmiah:</strong> Ixora coccinea</p>
    <p><strong>Famili:</strong> Rubiaceae</p>
    <p><strong>Fakta Unik:</strong> Di Indonesia disebut "Asoka", bunga klasik halaman rumah.</p>
    <p><strong>Habitat:</strong> Tumbuhan tropis yang tumbuh di daerah panas dan lembap.</p>
    <p><strong>Perawatan:</strong> Cahaya matahari penuh, siram teratur, tahan panas.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=ixora'">See more</button>
</div>

<!-- Calendula -->
<div class="card">
  <img src="assets/calendula.jpg" alt="Calendula" />
  <div class="card-content">
    <h3>Calendula</h3>
    <p><strong>Nama Ilmiah:</strong> Calendula officinalis</p>
    <p><strong>Famili:</strong> Asteraceae</p>
    <p><strong>Fakta Unik:</strong> Biasa dipakai sebagai obat luka dan kosmetik.</p>
    <p><strong>Habitat:</strong> Asal Eropa Selatan dan Mediterania, tumbuh di kebun dan ladang.</p>
    <p><strong>Perawatan:</strong> Sinar matahari penuh, tanah gembur, air cukup.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=calendula'">See more</button>
</div>

<!-- Delphinium -->
<div class="card">
  <img src="assets/delphinium.jpg" alt="Delphinium" />
  <div class="card-content">
    <h3>Delphinium</h3>
    <p><strong>Nama Ilmiah:</strong> Delphinium elatum</p>
    <p><strong>Famili:</strong> Ranunculaceae</p>
    <p><strong>Fakta Unik:</strong> Bunga tinggi yang mekar di musim panas.</p>
    <p><strong>Habitat:</strong> Pegunungan dan daerah beriklim sedang di Eropa dan Amerika Utara.</p>
    <p><strong>Perawatan:</strong> Tanah subur, siram rutin, perlu penyangga karena tinggi.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=delphinium'">See more</button>
</div>

<!-- Saffron -->
<div class="card">
  <img src="assets/saffron.jpg" alt="Saffron" />
  <div class="card-content">
    <h3>Saffron</h3>
    <p><strong>Nama Ilmiah:</strong> Crocus sativus</p>
    <p><strong>Famili:</strong> Iridaceae</p>
    <p><strong>Fakta Unik:</strong> Rempah termahal di dunia, dari putik bunga.</p>
    <p><strong>Habitat:</strong> Tumbuh di daerah beriklim sedang dan kering, seperti Asia Barat dan Mediterania.</p>
    <p><strong>Perawatan:</strong> Sinar matahari penuh, tanah kering, siram minim.</p>
  </div>
  <button class="see-more-btn" onclick="window.location.href='detail.html?flower=saffron'">See more</button>
</div>


<script>
  const searchInput = document.getElementById('searchInput');
  const cards = document.querySelectorAll('#flowerGrid .card');

  searchInput.addEventListener('input', function () {
    const keyword = searchInput.value.toLowerCase();

    cards.forEach(card => {
      const title = card.querySelector('h3').textContent.toLowerCase();
      const desc = card.querySelector('p').textContent.toLowerCase();

      // Tampilkan hanya jika keyword cocok
      if (title.includes(keyword) || desc.includes(keyword)) {
        card.style.display = 'block'; // Atau 'flex' sesuai CSS kamu
      } else {
        card.style.display = 'none';
      }
    });
  });
</script>



  <footer>
    <p>&copy; 2025 PlantPals. 💚 Semua hak cipta dilindungi.</p>
  </footer>
</body>
</html>

