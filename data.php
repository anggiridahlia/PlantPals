<?php
// data.php
// Data bunga dalam array PHP (digunakan sebagai fallback jika database kosong)
// Path gambar harus absolut dari root web server.

// PENTING: ID Penjual default ini digunakan HANYA jika database produk kosong
// Asumsikan seller1 memiliki ID 2 di tabel `users`.
$DEFAULT_FALLBACK_SELLER_ID = 2; // Ganti ini jika ID seller1 Anda berbeda di database Anda

$flowers_initial_data = [
    [
        "name" => "Pink Rose", "img" => "/PlantPals/assets/rose.jpg", "scientific" => "Rosa", "family" => "Rosaceae",
        "description" => "Tanaman semak berbunga yang populer dengan warna bunga pink yang melambangkan cinta dan keanggunan.",
        "habitat" => "Tumbuh baik di daerah beriklim sedang hingga tropis, membutuhkan sinar matahari penuh.",
        "care" => "Siram 2-3 kali seminggu, dan potong daun yang kering secara rutin untuk menjaga kesehatan tanaman.",
        "fact" => "Simbol cinta dan keanggunan, sering digunakan dalam seni dan budaya sebagai lambang kasih sayang.",
        "price" => 75000, "stock" => 10, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
        "name" => "Sakura", "img" => "/PlantPals/assets/sakura.jpg", "scientific" => "Prunus serrulata", "family" => "Rosaceae",
        "description" => "Bunga berwarna merah muda yang mekar di musim semi dan menjadi simbol kecantikan yang sementara.",
        "habitat" => "Ditemukan di daerah beriklim sedang seperti Jepang, Korea, dan China.",
        "care" => "Tanam di daerah sejuk, siram teratur, dan butuh sinar matahari langsung.",
        "fact" => "Mekar hanya dalam waktu singkat saat musim semi, simbol kecantikan yang fana di budaya Jepang.",
        "price" => 120000, "stock" => 5, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
        "name" => "Tulip", "img" => "/PlantPals/assets/tulip.jpg", "scientific" => "Tulipa spp.", "family" => "Liliaceae",
        "description" => "Bunga dengan kelopak berwarna-warni, simbol musim semi di Belanda.",
        "habitat" => "Tumbuh di daerah dengan musim dingin yang jelas; populer di taman Eropa.",
        "care" => "Simpan di tempat sejuk, siram secukupnya, tanah jangan terlalu lembap.",
        "fact" => "Berasal dari Asia Tengah tapi sangat identik dengan Belanda.",
        "price" => 85000, "stock" => 15, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Lily of The Valley", "img" => "/PlantPals/assets/lvalley.jpg", "scientific" => "Convallaria majalis", "family" => "Asparagaceae",
      "description" => "Bunga putih kecil berbentuk lonceng dengan aroma manis.",
      "habitat" => "Hutan-hutan teduh dan beriklim sedang.",
      "care" => "Suka tempat teduh, tanah lembap, dan tidak perlu sering disiram.",
      "fact" => "Meskipun sangat harum dan cantik, bunga ini beracun jika tertelan.",
      "price" => 95000, "stock" => 8, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Succulent", "img" => "/PlantPals/assets/succulent.jpg", "scientific" => "Beragam (contoh: Echeveria, Aloe, Crassula)", "family" => "Bervariasi (umumnya Crassulaceae atau Asphodelaceae)",
      "description" => "Tanaman yang dapat menyimpan air di daun, cocok untuk daerah kering.",
      "habitat" => "Umum di wilayah gurun dan semi-kering seperti Afrika, Amerika Selatan.",
      "care" => "Butuh cahaya terang, siram seminggu sekali atau saat tanah benar-benar kering.",
      "fact" => "Perawatannya sangat mudah dan cocok untuk pemula.",
      "price" => 60000, "stock" => 20, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Jasmine", "img" => "/PlantPals/assets/jasmine.jpg", "scientific" => "Jasminum sambac", "family" => "Oleaceae",
      "description" => "Tanaman merambat atau semak dengan daun hijau kecil dan bunga putih kecil beraroma sangat harum, biasanya berbentuk bintang.",
      "habitat" => "Tumbuh subur di daerah tropis dan subtropis Asia Selatan dan Asia Tenggara, menyukai tanah subur dan kelembapan sedang.",
      "care" => "Sinar matahari penuh, tanah lembap, dan rajin dipangkas agar tanaman rimbun.",
      "fact" => "Aromanya populer untuk parfum dan teh tradisional.",
      "price" => 70000, "stock" => 12, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Dahlia", "img" => "/PlantPals/assets/dahlia.jpg", "scientific" => "Dahlia pinnata", "family" => "Asteraceae",
      "description" => "Tanaman semak dengan bunga besar beraneka warna dan bentuk, dari yang sederhana hingga bergelombang dan berlapis banyak.",
      "habitat" => "Asli dari Meksiko, tumbuh baik di dataran tinggi beriklim sedang, membutuhkan tanah subur dan drainase baik.",
      "care" => "Siram teratur, sinar matahari penuh, pupuk bulanan untuk pertumbuhan optimal.",
      "fact" => "Bunga nasional Meksiko dan sangat populer sebagai tanaman hias.",
      "price" => 110000, "stock" => 7, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Peony", "img" => "/PlantPals/assets/peony.jpg", "scientific" => "Paeonia spp.", "family" => "Paeoniaceae",
      "description" => "Semak dengan bunga besar, berlapis tebal, dan warna cerah mulai dari merah muda, merah, hingga putih. Daunnya lebar dan hijau tua.",
      "habitat" => "Tumbuh di daerah beriklim sedang hingga dingin di Asia dan Eropa, suka tanah yang subur dan gembur.",
      "care" => "Tempat terbuka, tanah subur, siram rutin saat musim tumbuh.",
      "fact" => "Bisa hidup lebih dari 100 tahun dan dipercaya membawa keberuntungan.",
      "price" => 130000, "stock" => 6, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Dandelion", "img" => "/PlantPals/assets/dandelion.jpg", "scientific" => "Taraxacum officinale", "family" => "Asteraceae",
      "description" => "Tanaman kecil dengan bunga kuning cerah berbentuk cakram dan daun bergerigi. Biji berbulu halus yang mudah terbawa angin.",
      "habitat" => "Tersebar luas di daerah beriklim sedang hingga dingin, sering ditemukan di padang rumput dan tepi jalan.",
      "care" => "Tahan banting, cukup sinar matahari dan air, tumbuh hampir di semua jenis tanah.",
      "fact" => "Biji terbang dengan angin, melambangkan harapan dan kebebasan.",
      "price" => 40000, "stock" => 25, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Lily", "img" => "/PlantPals/assets/lily.jpg", "scientific" => "Lilium spp.", "family" => "Liliaceae",
      "description" => "Tanaman berumbi dengan bunga besar, berbentuk terompet atau corong, warna dan pola beragam. Daunnya ramping dan panjang.",
      "habitat" => "Tumbuh di berbagai wilayah beriklim sedang, termasuk hutan, pegunungan, dan ladang terbuka.",
      "care" => "Sinar matahari sebagian, siram teratur, tanah harus dikeringkan dengan baik untuk mencegah pembusukan.",
      "fact" => "Banyak jenis dan warna, sering digunakan untuk dekorasi dan upacara keagamaan.",
      "price" => 100000, "stock" => 9, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Lavender", "img" => "/PlantPals/assets/lavender.jpg", "scientific" => "Lavandula angustifolia", "family" => "Lamiaceae",
      "description" => "Bunga ungu kecil dengan aroma menenangkan.",
      "habitat" => "Tumbuh di daerah Mediterania, dataran tinggi kering.",
      "care" => "Banyak sinar matahari, tanah kering, jangan terlalu lembap.",
      "fact" => "Aromanya bisa mengusir nyamuk dan bikin rileks.",
      "price" => 65000, "stock" => 18, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Edelweis", "img" => "/PlantPals/assets/edelweis.jpg", "scientific" => "Anaphalis javanica", "family" => "Asteraceae",
      "description" => "Bunga abadi yang tumbuh di pegunungan tinggi.",
      "habitat" => "Pegunungan tropis Indonesia, terutama di atas 2000 mdpl.",
      "care" => "Tanah berpasir, sinar matahari, tidak suka lembap.",
      "fact" => "Tumbuh di pegunungan tinggi, simbol cinta abadi.",
      "price" => 180000, "stock" => 3, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Sun Flowers", "img" => "/PlantPals/assets/sun.jpg", "scientific" => "Helianthus annuus", "family" => "Asteraceae",
      "description" => "Bunga besar berwarna kuning cerah yang selalu menghadap matahari.",
      "habitat" => "Tumbuh baik di daerah dataran rendah dan terbuka.",
      "care" => "Sinar matahari penuh, air cukup, tanah subur.",
      "fact" => "Mengikuti arah matahari (heliotropisme).",
      "price" => 55000, "stock" => 14, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Iris", "img" => "/PlantPals/assets/iris.jpg", "scientific" => "Iris germanica", "family" => "Iridaceae",
      "description" => "Bunga dengan kelopak yang unik dan warna yang bervariasi.",
      "habitat" => "Lahan terbuka beriklim sedang, banyak ditemukan di Eropa.",
      "care" => "Sinar matahari sedang–penuh, tanah dikeringkan dengan baik.",
      "fact" => "Dinamai dari dewi pelangi Yunani.",
      "price" => 80000, "stock" => 10, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Lotus", "img" => "/PlantPals/assets/lotus.jpg", "scientific" => "Nelumbo nucifera", "family" => "Nelumbonaceae",
      "description" => "Bunga air yang indah, sering dianggap simbol kesucian.",
      "habitat" => "Kolam dan rawa dangkal di daerah tropis dan subtropis Asia.",
      "care" => "Butuh air menggenang, sinar matahari penuh.",
      "fact" => "Bisa tumbuh di air kotor tapi bunganya bersih dan suci.",
      "price" => 140000, "stock" => 5, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Gardenia", "img" => "/PlantPals/assets/gardenia.jpg", "scientific" => "Gardenia jasminoides", "family" => "Rubiaceae",
      "description" => "Bunga putih harum dengan kelopak tebal dan mengkilap.",
      "habitat" => "Tumbuhan asli Asia Tenggara, tumbuh di daerah teduh dan lembap.",
      "care" => "Sinar terang tapi tidak langsung, kelembapan tinggi, tanah asam.",
      "fact" => "Wangi kuat, sering dipakai untuk parfum.",
      "price" => 105000, "stock" => 7, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Snowdrop", "img" => "/PlantPals/assets/snowdrop.jpg", "scientific" => "Galanthus nivalis", "family" => "Amaryllidaceae",
      "description" => "Bunga kecil putih yang mekar di musim dingin.",
      "habitat" => "Hutan beriklim sedang di Eropa dan Asia Barat.",
      "care" => "Tanah lembap, teduh-sejuk, siram teratur.",
      "fact" => "Mekar di akhir musim dingin atau awal semi.",
      "price" => 90000, "stock" => 11, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Orchid", "img" => "/PlantPals/assets/orchid.jpg", "scientific" => "Orchidaceae (beragam spesies)", "family" => "Orchidaceae",
      "description" => "Bunga eksotis dengan berbagai bentuk dan warna menarik.",
      "habitat" => "Hutan tropis dan subtropis di seluruh dunia.",
      "care" => "Cahaya terang tak langsung, siram seminggu 1-2x, jangan genang air.",
      "fact" => "Tumbuh di udara, bukan di tanah (epifit).",
      "price" => 170000, "stock" => 4, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Aster", "img" => "/PlantPals/assets/aster.jpg", "scientific" => "Aster amellus", "family" => "Asteraceae",
      "description" => "Bunga yang serupa daisy dengan kelopak tipis dan banyak warna.",
      "habitat" => "Dataran terbuka dan kebun di daerah beriklim sedang.",
      "care" => "Sinar matahari penuh, tanah subur, air cukup.",
      "fact" => "Serupa daisy, menarik kupu-upu.",
      "price" => 60000, "stock" => 16, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Ixora", "img" => "/PlantPals/assets/ixora.jpg", "scientific" => "Ixora coccinea", "family" => "Rubiaceae",
      "description" => "Bunga cluster kecil berwarna cerah, dikenal sebagai bunga soka.",
      "habitat" => "Tumbuhan tropis yang tumbuh di daerah panas dan lembap.",
      "care" => "Cahaya matahari penuh, siram teratur, tahan panas.",
      "fact" => "Di Indonesia disebut 'Asoka', bunga klasik halaman rumah.",
      "price" => 50000, "stock" => 13, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Calendula", "img" => "/PlantPals/assets/calendula.jpg", "scientific" => "Calendula officinalis", "family" => "Asteraceae",
      "description" => "Bunga oranye atau kuning cerah yang sering digunakan dalam pengobatan herbal.",
      "habitat" => "Asal Eropa Selatan dan Mediterania, tumbuh di kebun dan ladang.",
      "care" => "Sinar matahari penuh, tanah gembur, air cukup.",
      "fact" => "Biasa dipakai sebagai obat luka dan kosmetik.",
      "price" => 45000, "stock" => 22, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Delphinium", "img" => "/PlantPals/assets/delphinium.jpg", "scientific" => "Delphinium elatum", "family" => "Ranunculaceae",
      "description" => "Bunga tinggi berwarna biru, ungu, atau putih yang mekar di musim panas.",
      "habitat" => "Pegunungan dan daerah beriklim sedang di Eropa dan Amerika Utara.",
      "care" => "Tanah subur, siram rutin, perlu penyangga karena tinggi.",
      "fact" => "Bunga tinggi yang mekar di musim panas.",
      "price" => 125000, "stock" => 8, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ],
    [
      "name" => "Saffron", "img" => "/PlantPals/assets/saffron.jpg", "scientific" => "Crocus sativus", "family" => "Iridaceae",
      "description" => "Tanaman kecil dengan bunga ungu, putiknya menghasilkan rempah saffron.",
      "habitat" => "Tumbuh di daerah beriklim sedang dan kering, seperti Asia Barat dan Mediterania.",
      "care" => "Sinar matahari penuh, tanah kering, siram minim.",
      "fact" => "Rempah termahal di dunia, dari putik bunga.",
      "price" => 200000, "stock" => 2, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID
    ]
    ];

    $other_products_initial_data = [
        [
            "name" => "Pot Tanah Liat Klasik", "img" => "/PlantPals/assets/pot_tanah.jpg",
            "description" => "Pot tanah liat klasik dengan desain sederhana, cocok untuk berbagai tanaman.",
            "price" => 30000, "stock" => 50, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID,
            "scientific" => "N/A", "family" => "N/A", "habitat" => "N/A", "care" => "N/A", "fact" => "N/A"
        ],
        [
            "name" => "Pupuk Organik Serbaguna", "img" => "/PlantPals/assets/pupuk.jpg",
            "description" => "Pupuk organik dari bahan alami untuk pertumbuhan tanaman yang sehat dan subur.",
            "price" => 25000, "stock" => 100, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID,
            "scientific" => "N/A", "family" => "N/A", "habitat" => "N/A", "care" => "N/A", "fact" => "N/A"
        ],
        [
            "name" => "Set Alat Kebun Mini", "img" => "/PlantPals/assets/alat_kebun.jpg",
            "description" => "Set alat kebun mini berisi sekop, garpu, dan cangkul kecil, ideal untuk berkebun di rumah.",
            "price" => 80000, "stock" => 30, "seller_id" => $DEFAULT_FALLBACK_SELLER_ID,
            "scientific" => "N/A", "family" => "N/A", "habitat" => "N/A", "care" => "N/A", "fact" => "N/A"
        ]
    ];

    $all_initial_products = array_merge($flowers_initial_data, $other_products_initial_data);
    ?>