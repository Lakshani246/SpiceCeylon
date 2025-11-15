<?php
session_start();
include "../config/db.php";
include "header.php";

// Ensure user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch products from database
$sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 20";
$result = $conn->query($sql);
?>

<link rel="stylesheet" href="../assets/css/home.css">
<script src="../assets/js/home.js" defer></script>

<div class="home-container">
    <!-- Video Banner -->
    <div class="video-banner">
        <video autoplay muted loop class="banner-video">
            <source src="../assets/videos/landing-video.mp4" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
        <div class="video-overlay">
            <h1>Welcome to SpiceCeylon</h1>
            <p>Discover the finest Sri Lankan spices at your fingertips.</p>
        </div>
    </div>

    <!-- Spices Grid -->
    <h2 class="section-title">Our Spices</h2>
    <div class="spices-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="spice-card">
                    <img src="../assets/images/<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                    <h3><?php echo $row['name']; ?></h3>
                    <p>Price: LKR <?php echo number_format($row['price'],2); ?></p>
                    <div class="card-buttons">
                        <a href="browse.php?product_id=<?php echo $row['product_id']; ?>" class="btn-view">View</a>
                        <button class="btn-add-cart" data-id="<?php echo $row['product_id']; ?>">Add to Cart</button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No spices available at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>
