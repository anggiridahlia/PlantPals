<?php
// config.php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Ganti jika username MySQL Anda berbeda
define('DB_PASSWORD', '');     // Ganti jika password MySQL Anda berbeda
define('DB_NAME', 'plantpals_db'); // Pastikan sama dengan nama database yang Anda buat di Langkah 1

// Coba koneksi ke database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Cek koneksi
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>