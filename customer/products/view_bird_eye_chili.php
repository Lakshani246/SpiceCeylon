<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

include "../../config/db.php"; 

// Get the actual product from database
$product_query = mysqli_query($conn, "SELECT * FROM products WHERE name LIKE '%Ceylon Chili%' OR name LIKE '%Bird''s Eye%' OR name LIKE '%Kochchi%' LIMIT 1");
$product = mysqli_fetch_assoc($product_query);

if (!$product) {
    die("Ceylon Chili product not found in database! Please check products table.");
}

// Use actual data from database
$spice_id = $product['product_id'];
$spice_name = $product['name'];
$spice_desc = $product['description']; // This will show the description from database
$product_price = $product['price'];
$product_stock = $product['stock'];

// USE VIEW PAGE SPECIFIC IMAGE
$spice_image = '../../assets/images/Ceylon-Chili1.jpg'; // Your detailed view image

// If view image doesn't exist, fall back to database image
if (!file_exists($spice_image)) {
    $spice_image = '../../assets/images/' . $product['image'];
}

$prices = [
    '50g'   => 90,
    '100g'  => 170,
    '250g'  => 400,
    '500g'  => 750,
    '1kg'   => 1400
];

$default_price = $prices['250g'];
$spice_sinhala = 'Kochchi - කොච්චි';

// Review submit
$review_submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $rating = (int)$_POST['rating'];
    $review_text = mysqli_real_escape_string($conn, $_POST['review_text']);

    $sql = "INSERT INTO product_reviews (product_id, user_name, user_email, rating, review_text)
            VALUES ('$spice_id','$name','$email',$rating,'$review_text')";
    if (mysqli_query($conn, $sql)) {
        $review_submitted = true;
    }
}

$reviews_result = mysqli_query($conn, "SELECT * FROM product_reviews WHERE product_id='$spice_id' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $spice_name; ?> | SpiceCeylon</title>
<link rel="stylesheet" href="../../assets/css/view_product.css">
<script>
    const PRICE_DATA = <?php echo json_encode($prices); ?>;
    const DEFAULT_PRICE = <?php echo $default_price; ?>;
</script>
</head>
<body>

<header>
    <h1>SpiceCeylon</h1>
    <nav>
        <a href="../home.php">Home</a>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../orders.php">Orders</a>
        <a href="../../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="product-container">
    <a href="../home.php" class="back-button">← Back to Shop</a>
    <div class="product-details">
        <div class="product-image">
            <img src="<?php echo $spice_image; ?>" alt="<?php echo $spice_name; ?>">
        </div>
        <div class="product-info">
            <h1><?php echo $spice_name; ?></h1>
            <h3><?php echo $spice_sinhala; ?></h3>
            <p><?php echo $spice_desc; ?></p>
            <div class="product-prices">
                <?php foreach($prices as $size => $price): ?>
                    <p><strong><?php echo $size; ?>:</strong> Rs. <?php echo $price; ?>.00</p>
                <?php endforeach; ?>
            </div>
            <div class="size-buttons">
                <?php foreach($prices as $size => $p): ?>
                    <button data-size="<?php echo $size; ?>"><?php echo $size; ?></button>
                <?php endforeach; ?>
            </div>
            <!-- ADD TO CART FORM -->
<form method="POST" action="../add_to_cart.php">
    <input type="hidden" name="product_id" value="<?php echo $spice_id; ?>">
    
    <div class="quantity-container">
        <label>Quantity:</label>
        <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product_stock; ?>">
    </div>
    
    <p><strong>Price:</strong> Rs. <span id="display-price"><?php echo $default_price; ?>.00</span></p>
    <p class="stock-info"><strong>In Stock:</strong> <?php echo $product_stock; ?> units</p>
    
    <button type="submit" name="add_to_cart" class="btn-add-cart">
        Add to Cart
    </button>
</form>
        </div>
    </div>
    <div class="product-meta">
        <p><strong>SKU:</strong> CCH-015</p>
        <p><strong>Category:</strong> Chilies & Heat Elements</p>
        <p><strong>Heat Level:</strong> Very Hot 🌶️🌶️🌶️🌶️</p>
        <p><strong>Share:</strong>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode("https://yourwebsite.com"); ?>" target="_blank">
            <img src="../../assets/icons/facebook.png" alt="Facebook" class="share-icon">
        </a>
        <a href="https://www.pinterest.com/pin/create/button/?url=<?php echo urlencode("https://yourwebsite.com"); ?>" target="_blank">
            <img src="../../assets/icons/pinterest.png" alt="Pinterest" class="share-icon">
        </a>
        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode("Check this out: https://yourwebsite.com"); ?>" target="_blank">
            <img src="../../assets/icons/whatsapp.png" alt="WhatsApp" class="share-icon">
        </a>
        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode("Check this out: https://yourwebsite.com"); ?>" target="_blank">
            <img src="../../assets/icons/twitter.png" alt="Twitter" class="share-icon">
        </a>
    </p>
    </div>
    <div class="reviews-section">
        <h2>Reviews</h2>
        <?php if(mysqli_num_rows($reviews_result)===0): ?>
            <p>No reviews yet.</p>
        <?php else: ?>
            <?php while($row=mysqli_fetch_assoc($reviews_result)): ?>
                <div class="review">
                    <p><strong><?php echo $row['user_name']; ?></strong> - Rating: <?php echo $row['rating']; ?>/5</p>
                    <p><?php echo nl2br($row['review_text']); ?></p>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        <h3>Leave a Review</h3>
        <?php if($review_submitted) echo "<p style='color:green;'>Thank you! Review submitted.</p>"; ?>
        <form method="post">
            <p>Your rating *</p>
            <select name="rating" required>
                <option value="">Select Rating</option>
                <?php for($i=1;$i<=5;$i++) echo "<option>$i</option>"; ?>
            </select>
            <p>Your review *</p>
            <textarea name="review_text" required></textarea>
            <p>Name *</p>
            <input type="text" name="name" required>
            <p>Email *</p>
            <input type="email" name="email" required>
            <button type="submit" name="submit_review">Submit Review</button>
        </form>
    </div>
</div>
<script>
const prices = <?php echo json_encode($prices); ?>;
</script>
<script src="../../assets/js/view_product.js"></script>
</body>
</html>