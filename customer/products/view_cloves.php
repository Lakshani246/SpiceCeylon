<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

include "../../config/db.php"; // Correct path to DB

$spice_id = 'cloves';
$spice_name = 'Cloves';
$spice_sinhala = 'Karabu Neti - කරාබු නැටි';
$spice_desc = 'Cloves are aromatic flower buds used widely in Sri Lankan cuisine and traditional medicine.';
$spice_image = '../../assets/images/Cloves1.jpg'; // <-- make sure this image exists in assets/images/

// --- Handle review submission ---
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

// Fetch reviews
$reviews_result = mysqli_query($conn, "SELECT * FROM product_reviews WHERE product_id='$spice_id' ORDER BY created_at DESC");
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $spice_name; ?> | SpiceCeylon</title>
<style>
/* === Include the same CSS as Cinnamon page === */
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:0; padding:0; background:#fff8f0; color:#333; }
a { text-decoration: none; color: inherit; }
header { background:#d27f2d; color:#fff; padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; }
header h1 { margin:0; font-size:1.8rem; }
nav a { margin-left:1rem; font-weight:bold; color:#fff; transition:0.3s; }
nav a:hover{opacity:0.8;}
.product-container { max-width:1200px; margin:2rem auto; padding:0 20px; }
.back-button { display:inline-block; margin-bottom:1rem; padding:8px 15px; background:#d27f2d; color:#fff; border-radius:5px; float:right; transition:0.3s;}
.back-button:hover{background:#b36b24;}
.product-details { display:flex; flex-wrap:wrap; gap:2rem; margin-bottom:3rem; }
.product-image { flex: 1 1 300px; text-align: center; }
.product-image img { max-width: 80%; border-radius: 10px; box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
.product-info { flex:1 1 400px; }
.product-info h1 { font-size:2rem; margin-bottom:0.5rem; color:#7c3f0d; }
.product-info h3 { font-size:1.2rem; margin-bottom:1rem; color:#d27f2d; }
.product-info p { font-size:1rem; line-height:1.5; margin-bottom:1rem; }
.size-buttons { margin-bottom:1rem; }
.size-buttons button { background:#fff3e0; border:2px solid #d27f2d; color:#d27f2d; padding:8px 15px; margin-right:8px; border-radius:5px; cursor:pointer; font-weight:bold; transition:0.3s;}
.size-buttons button.selected, .size-buttons button:hover { background:#d27f2d; color:#fff; }
.quantity-container { margin-bottom:1.5rem; display:flex; align-items:center; gap:10px; }
.quantity-container input { width:60px; padding:5px; text-align:center; border-radius:5px; border:1px solid #ccc; }
.btn-add-cart { background:#d27f2d; color:#fff; border:none; padding:12px 25px; border-radius:5px; cursor:pointer; font-size:1rem; }
.btn-add-cart:hover { background:#b36b24; }
.product-meta { margin-top: 1.5rem; font-size: 0.95rem; color: #555; }
.product-meta p { margin: 5px 0; }
.product-meta .share-icon { width: 24px; height: 24px; margin-right: 8px; vertical-align: middle; cursor: pointer; transition: opacity 0.3s; }
.product-meta .share-icon:hover { opacity: 0.7; }
.reviews-section { border-top:1px solid #eee; padding-top:2rem; }
.reviews-section h2 { color:#7c3f0d; margin-bottom:1rem; }
.review { background:#fdf6f0; border-left:4px solid #d27f2d; padding:10px 15px; margin-bottom:1rem; border-radius:5px; }
.review p { margin:0; }
form p{margin:0.5rem 0 0.2rem;}
form input, form select, form textarea {width:100%; padding:6px; margin-bottom:1rem; border-radius:5px; border:1px solid #ccc;}
form button {padding:10px 20px; background:#d27f2d; color:#fff; border:none; border-radius:5px; cursor:pointer;}
form button:hover{background:#b36b24;}
footer { background-color: #d27f2d; color: #fff; text-align: center; padding: 1.5rem 20px; margin-top: 3rem; font-size: 0.95rem; position: relative;}
footer a { color: #fff; text-decoration: underline; margin: 0 5px; transition: opacity 0.3s; }
footer a:hover { opacity: 0.8; }
footer p { margin: 0.5rem 0 0; }
@media(max-width:768px){ .product-details{flex-direction:column;} .back-button{float:none; display:block; margin-bottom:1rem;} footer{padding:1rem 10px;font-size:0.9rem;} }
</style>
</head>
<body>

<header>
    <h1>SpiceCeylon</h1>
    <nav>
        <a href="../home.php">Home</a>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../orders.php">Orders</a>
        <a href="../auth/logout.php">Logout</a>
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

            <!-- Size Selection -->
            <div class="size-buttons">
                <?php
                $sizes = ['25g','50g','100g','250g','500g','1kg'];
                foreach($sizes as $size) {
                    echo "<button data-size='$size'>$size</button>";
                }
                ?>
            </div>

            <!-- Quantity -->
            <div class="quantity-container">
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity" value="1" min="1">
            </div>

            <button class="btn-add-cart" data-id="<?php echo $spice_id; ?>">Add to Cart</button>
        </div>
    </div>

    <!-- Product Meta -->
<div class="product-meta">
    <p><strong>SKU:</strong> N/A</p>
    <p><strong>Category:</strong> Core Sri Lankan Spices</p>
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

    <!-- Reviews Section -->
    <div class="reviews-section">
        <h2>Reviews</h2>
        <?php if(mysqli_num_rows($reviews_result)===0): ?>
            <p>There are no reviews yet.</p>
        <?php else: ?>
            <?php while($row=mysqli_fetch_assoc($reviews_result)): ?>
                <div class="review">
                    <p><strong><?php echo htmlspecialchars($row['user_name']); ?></strong> - Rating: <?php echo $row['rating']; ?>/5</p>
                    <p><?php echo nl2br(htmlspecialchars($row['review_text'])); ?></p>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>

        <h3>Be the first to review “<?php echo $spice_name; ?>”</h3>
        <?php if($review_submitted) echo "<p style='color:green;'>Thank you! Your review has been submitted.</p>"; ?>
        <form method="post" action="">
            <p>Your rating *</p>
            <select name="rating" required>
                <option value="">Select Rating</option>
                <?php for($i=1;$i<=5;$i++){ echo "<option value='$i'>$i</option>"; } ?>
            </select>
            <p>Your review *</p>
            <textarea name="review_text" rows="4" required></textarea>
            <p>Name *</p>
            <input type="text" name="name" required>
            <p>Email *</p>
            <input type="email" name="email" required>
            <button type="submit" name="submit_review">Submit Review</button>
        </form>
    </div>
</div>

<script>
// Size selection
const sizeButtons = document.querySelectorAll('.size-buttons button');
let selectedSize = '100g';
sizeButtons.forEach(btn=>{
    btn.addEventListener('click', ()=>{
        sizeButtons.forEach(b=>b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedSize = btn.getAttribute('data-size');
    });
});

// Add to cart
document.querySelector('.btn-add-cart').addEventListener('click', ()=>{
    const spiceId = "<?php echo $spice_id; ?>";
    const quantity = document.getElementById('quantity').value;
    alert(`Added ${quantity} x ${selectedSize} of ${spiceId} to your cart!`);
    // TODO: Replace alert with AJAX/PHP call to add cart
});
</script>

<?php include "../footer.php"; ?>
</body>
</html>
