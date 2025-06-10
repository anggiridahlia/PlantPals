<?php
session_start();
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('display_startup_errors', 1); // Mengaktifkan tampilan error saat startup
error_reporting(E_ALL); // Melaporkan semua jenis error

// Tentukan ROOT_PATH untuk path yang lebih stabil
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'auth_middleware.php';
require_role('seller'); // Memastikan user adalah seller
require_once ROOT_PATH . 'config.php'; // config.php ada di root PlantPals/

$seller_id = $_SESSION['id']; // Dapatkan ID penjual yang sedang login

$product_reviews = [];
$average_rating_seller = 0;
$total_reviews_count_seller = 0;

// Fetch all reviews for this seller's products
$sql_reviews = "SELECT pr.id, pr.rating, pr.comment, pr.created_at, u.username as reviewer_username, p.name as product_name
                FROM product_reviews pr
                JOIN products p ON pr.product_id = p.id
                JOIN users u ON pr.user_id = u.id
                WHERE p.seller_id = ?
                ORDER BY pr.created_at DESC";

if ($stmt_reviews = mysqli_prepare($conn, $sql_reviews)) {
    mysqli_stmt_bind_param($stmt_reviews, "i", $seller_id);
    mysqli_stmt_execute($stmt_reviews);
    $result_reviews = mysqli_stmt_get_result($stmt_reviews);
    while ($row_review = mysqli_fetch_assoc($result_reviews)) {
        $product_reviews[] = $row_review;
    }
    mysqli_stmt_close($stmt_reviews);

    // Calculate overall average rating and total count for seller
    if (!empty($product_reviews)) {
        $total_rating_sum = 0;
        foreach ($product_reviews as $review) {
            $total_rating_sum += $review['rating'];
        }
        $total_reviews_count_seller = count($product_reviews);
        $average_rating_seller = round($total_rating_sum / $total_reviews_count_seller, 1);
    }
} else {
    error_log("Error preparing seller reviews fetch statement: " . mysqli_error($conn));
}

mysqli_close($conn);
?>
<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'header.php'; ?>

    <h1>Ulasan Produk Anda</h1>

    <?php if ($total_reviews_count_seller > 0): ?>
        <div class="review-summary card-panel">
            <div class="stars" style="font-size: 2.5rem; color: #FFD700; margin-bottom: 10px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php echo ($i <= $average_rating_seller) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                <?php endfor; ?>
            </div>
            <p style="font-size: 1.1rem; color: #555; margin: 0;">Rating Rata-rata: <strong><?php echo htmlspecialchars($average_rating_seller); ?> dari 5</strong> (dari <?php echo htmlspecialchars($total_reviews_count_seller); ?> ulasan)</p>
        </div>
    <?php else: ?>
        <p class="card-panel">Belum ada ulasan untuk produk Anda.</p>
    <?php endif; ?>

    <div class="section-header" style="margin-top: 40px;">
        <h2>Daftar Ulasan</h2>
    </div>

    <?php if (empty($product_reviews)): ?>
        <p class="card-panel">Tidak ada ulasan untuk ditampilkan.</p>
    <?php else: ?>
        <ul class="review-list" style="list-style: none; padding: 0;">
            <?php foreach ($product_reviews as $review): ?>
                <li class="card-panel" style="margin-bottom: 20px;">
                    <div class="review-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 10px;">
                        <strong style="font-size: 1.1rem; color: #000;">Dari: <?php echo htmlspecialchars($review['reviewer_username']); ?> (Produk: <?php echo htmlspecialchars($review['product_name']); ?>)</strong>
                        <div class="stars" style="color: #FFD700; font-size: 1.2rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php echo ($i <= $review['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="review-comment" style="font-size: 0.95rem; color: #444; line-height: 1.6; margin-bottom: 10px;"><?php echo nl2br(htmlspecialchars($review['comment'] ?? 'Tidak ada komentar.')); ?></p>
                    <span class="review-date" style="font-size: 0.85rem; color: #777; text-align: right; display: block;"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($review['created_at']))); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>