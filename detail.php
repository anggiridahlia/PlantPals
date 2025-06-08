<?php
// Anda bisa menyertakan atau mendefinisikan ulang $flowers di sini
// atau membuat file terpisah (misal: data_flowers.php) yang berisi array $flowers
// lalu include_once 'data_flowers.php';

$flowers = [
    // ... (salin array $flowers dari dashboard.php ke sini atau ke file terpisah) ...
];

$selected_flower = null;
if (isset($_GET['flower'])) {
    $flower_param = strtolower(trim($_GET['flower']));
    foreach ($flowers as $flower) {
        if (strtolower(str_replace(' ', '', $flower['name'])) === $flower_param) {
            $selected_flower = $flower;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $selected_flower ? $selected_flower['name'] : 'Detail Bunga'; ?> - PlantPals</title>
  <style>
    /* Salin sebagian besar CSS dari dashboard.php yang relevan untuk card dan body */
    body {
      background: rgb(239, 249, 239);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      flex-direction: column;
      justify-content: flex-start; /* Ubah ke flex-start */
      align-items: center;
      min-height: 100vh; /* Gunakan min-height */
      margin: 0;
      color: #2f5d3a;
      padding: 20px;
    }
    .title {
      font-size: 2.5rem;
      margin-bottom: 30px;
      color: #3a5a20;
    }
    .detail-card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      width: 80%;
      max-width: 700px;
      padding: 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .detail-card img {
      width: 100%;
      max-width: 400px;
      height: auto;
      border-radius: 8px;
      margin-bottom: 25px;
    }
    .detail-card h3 {
      font-size: 2rem;
      margin-bottom: 15px;
      color: #2f5d3a;
    }
    .detail-card p {
      font-size: 1.1rem;
      line-height: 1.6;
      margin-bottom: 10px;
      color: #555;
      text-align: left;
      width: 100%;
    }
    .detail-card p strong {
      display: inline-block;
      width: 120px; /* Lebar untuk label */
      vertical-align: top;
    }
    .back-btn {
      margin-top: 30px;
      padding: 12px 25px;
      background-color: #E5989B;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1.1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
      text-decoration: none; /* Untuk link */
    }
    .back-btn:hover {
      background-color: rgb(182, 88, 117);
    }
  </style>
</head>
<body>
  <h1 class="title">Detail Bunga</h1>

  <?php if ($selected_flower): ?>
    <div class="detail-card">
      <img src="<?php echo $selected_flower['img']; ?>" alt="<?php echo $selected_flower['name']; ?>" />
      <h3><?php echo $selected_flower['name']; ?></h3>
      <p><strong>Nama Ilmiah:</strong> <?php echo $selected_flower['scientific']; ?></p>
      <p><strong>Familia:</strong> <?php echo $selected_flower['family']; ?></p>
      <p><strong>Deskripsi:</strong> <?php echo $selected_flower['description']; ?></p>
      <p><strong>Habitat:</strong> <?php echo $selected_flower['habitat']; ?></p>
      <p><strong>Perawatan:</strong> <?php echo $selected_flower['care']; ?></p>
      <p><strong>Fakta unik:</strong> <?php echo $selected_flower['fact']; ?></p>
    </div>
  <?php else: ?>
    <div class="detail-card" style="padding: 50px;">
      <h3>Bunga Tidak Ditemukan</h3>
      <p>Informasi bunga yang Anda cari tidak tersedia.</p>
    </div>
  <?php endif; ?>

  <a href="dashboard.php?page=home" class="back-btn">Kembali ke Dashboard</a>
</body>
</html>