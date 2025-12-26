<?php
session_start();
require_once "../config/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$product_id) {
    header("Location: home.php");
    exit();
}

// Fetch product details - REMOVED profile_pic from query
$query = "SELECT p.*, u.name as farmer_name, u.farm_location, u.phone as farmer_phone, 
                 u.email as farmer_email
          FROM products p 
          JOIN users u ON p.farmer_id = u.user_id 
          WHERE p.product_id = '$product_id' 
          AND p.admin_approved = 'approved' 
          AND p.status = 'Approved'";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    header("Location: home.php");
    exit();
}

$product = $result->fetch_assoc();

// Get image path
$image_path = '';
if ($product['image']) {
    $image_name = basename($product['image']);
    $locations = [
        '../assets/images/' . $image_name,
        '../' . $product['image'],
        '../uploads/products/' . $image_name,
        $product['image']
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            $image_path = $location;
            break;
        }
    }
}

// Get farmer's other products
$farmer_products_query = $conn->query("SELECT * FROM products WHERE farmer_id = '{$product['farmer_id']}' AND product_id != '$product_id' AND admin_approved = 'approved' LIMIT 3");

// Get product reviews - REMOVED profile_pic from query
$reviews_query = $conn->query("SELECT r.*, u.name as customer_name
                              FROM reviews r 
                              JOIN users u ON r.customer_id = u.user_id 
                              WHERE r.product_id = '$product_id' 
                              ORDER BY r.created_at DESC LIMIT 10");

// Check if user has already reviewed this product
$user_review = null;
if (isset($_SESSION['user_id'])) {
    $user_review_query = $conn->query("SELECT * FROM reviews WHERE product_id = '$product_id' AND customer_id = '{$_SESSION['user_id']}'");
    $user_review = $user_review_query->fetch_assoc();
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = '{$_SESSION['user_id']}'");
    $cart_count = $cart_query->fetch_assoc()['count'];
}

// Package sizes with multipliers
$package_sizes = [
    '25g' => 0.025,
    '50g' => 0.05,
    '100g' => 0.1,
    '250g' => 0.25,
    '500g' => 0.5,
    '1kg' => 1
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--spice-red) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--spice-dark) !important;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .nav-link:hover {
            color: var(--spice-red) !important;
        }
        
        .product-hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('<?php echo $image_path ? $image_path : '../assets/images/spice-bg.jpg'; ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0 50px;
            margin-bottom: 50px;
        }
        
        .product-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: -50px;
            position: relative;
        }
        
        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .product-title {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }
        
        .product-price {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--spice-red);
            margin-bottom: 10px;
        }
        
        .product-meta {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            color: #666;
        }
        
        .product-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .product-meta-item i {
            color: var(--spice-red);
        }
        
        .package-options {
            margin: 30px 0;
        }
        
        .package-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            margin: 0 10px 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .package-btn:hover, .package-btn.active {
            border-color: var(--spice-red);
            background: var(--spice-red);
            color: white;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            border-color: var(--spice-red);
            color: var(--spice-red);
        }
        
        .quantity-input {
            width: 70px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px;
        }
        
        .btn-add-cart {
            background: var(--spice-green);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .btn-add-cart:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .btn-wishlist {
            background: white;
            color: var(--spice-red);
            border: 2px solid var(--spice-red);
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .btn-wishlist:hover {
            background: var(--spice-red);
            color: white;
        }
        
        .stock-info {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .stock-in {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
        }
        
        .stock-low {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
        }
        
        .stock-out {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .section-title {
            margin: 40px 0 20px;
            color: var(--spice-dark);
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--spice-red);
        }
        
        .farmer-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .review-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .review-rating {
            color: var(--spice-gold);
            margin-bottom: 10px;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }
        
        .star {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ddd;
            transition: color 0.3s ease;
        }
        
        .star:hover, .star.active {
            color: var(--spice-gold);
        }
        
        .related-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .related-product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 50px 0 20px;
            margin-top: 80px;
        }
        
        @media (max-width: 768px) {
            .product-container {
                padding: 20px;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .product-price {
                font-size: 1.8rem;
            }
            
            .product-image {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-pepper-hot me-2"></i>SpiceCeylon
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i> Cart 
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="request.php">Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="wishlist.php"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Product Hero Section -->
    <div class="product-hero">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb" style="background: transparent;">
                            <li class="breadcrumb-item"><a href="home.php" class="text-white">Home</a></li>
                            <li class="breadcrumb-item"><a href="home.php?category=<?php echo urlencode($product['category']); ?>" class="text-white"><?php echo htmlspecialchars($product['category']); ?></a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
                        </ol>
                    </nav>
                    <h1 class="text-white"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="text-white">Direct from <?php echo htmlspecialchars($product['farmer_name']); ?>'s farm</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details -->
    <div class="container">
        <div class="product-container">
            <div class="row">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="sticky-top" style="top: 20px;">
                        <?php if($image_path): ?>
                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <?php else: ?>
                            <div style="height: 400px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                <i class="fas fa-leaf fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Stock Status -->
                        <?php 
                        $stock_class = 'stock-in';
                        $stock_text = 'In Stock';
                        if ($product['stock'] <= 0) {
                            $stock_class = 'stock-out';
                            $stock_text = 'Out of Stock';
                        } elseif ($product['stock'] < 10) {
                            $stock_class = 'stock-low';
                            $stock_text = 'Low Stock';
                        }
                        ?>
                        <div class="<?php echo $stock_class; ?> stock-info">
                            <i class="fas fa-<?php echo $stock_class == 'stock-out' ? 'times' : ($stock_class == 'stock-low' ? 'exclamation-triangle' : 'check'); ?> me-1"></i>
                            <?php echo $stock_text; ?> (<?php echo $product['stock']; ?> kg available)
                        </div>
                        
                        <!-- Farmer Info -->
                        <div class="farmer-card">
                            <div class="d-flex align-items-center mb-3">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--spice-green); display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fas fa-user fa-lg text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Farmer: <?php echo htmlspecialchars($product['farmer_name']); ?></h5>
                                    <p class="mb-0 text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($product['farm_location']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <small><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($product['farmer_phone']); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <small><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($product['farmer_email']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="col-lg-6">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-meta">
                        <div class="product-meta-item">
                            <i class="fas fa-tags"></i>
                            <span><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                        <div class="product-meta-item">
                            <i class="fas fa-star"></i>
                            <span>
                                <?php 
                                // Calculate average rating
                                $rating_query = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = '$product_id'");
                                $rating_data = $rating_query->fetch_assoc();
                                echo number_format($rating_data['avg_rating'] ?? 0, 1) . ' (' . ($rating_data['review_count'] ?? 0) . ' reviews)';
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <p class="lead"><?php echo htmlspecialchars($product['description']); ?></p>
                    
                    <!-- Package Size Selection -->
                    <div class="package-options">
                        <h4 class="section-title">Select Package Size</h4>
                        <div>
                            <?php 
                            $base_price = $product['price']; // Price per kg
                            $selected_package = '1kg';
                            $selected_price = $base_price;
                            ?>
                            <?php foreach($package_sizes as $size => $multiplier): ?>
                                <button class="package-btn <?php echo $size == $selected_package ? 'active' : ''; ?>" 
                                        data-size="<?php echo $size; ?>"
                                        data-multiplier="<?php echo $multiplier; ?>"
                                        data-price="<?php echo number_format($base_price * $multiplier, 2); ?>">
                                    <?php echo $size; ?> 
                                    <br>
                                    <small>Rs. <?php echo number_format($base_price * $multiplier, 2); ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Quantity Selector -->
                    <div class="quantity-selector">
                        <h5 class="mb-0">Quantity:</h5>
                        <div class="d-flex align-items-center">
                            <button class="quantity-btn" onclick="updateQuantity(-1)">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button class="quantity-btn" onclick="updateQuantity(1)">+</button>
                            <span class="ms-3" id="selected-package">(1kg)</span>
                        </div>
                    </div>
                    
                    <!-- Price Display -->
                    <div class="mb-4">
                        <h3 class="product-price" id="display-price">Rs. <?php echo number_format($base_price, 2); ?></h3>
                        <small class="text-muted" id="unit-price">Rs. <?php echo number_format($base_price, 2); ?> per kg</small>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row g-3">
                        <div class="col-md-8">
                            <?php if($product['stock'] > 0): ?>
                                <button class="btn-add-cart w-100" onclick="addToCart(<?php echo $product_id; ?>)">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="btn-add-cart w-100" style="background: #95a5a6;" disabled>
                                    <i class="fas fa-times"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <button class="btn-wishlist w-100" onclick="addToWishlist(<?php echo $product_id; ?>)">
                                <i class="fas fa-heart"></i> Wishlist
                            </button>
                        </div>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="mt-5">
                        <h4 class="section-title">Product Details</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Origin:</strong> <?php echo htmlspecialchars($product['farm_location']); ?></p>
                                <p><strong>Harvest Date:</strong> <?php echo date('F Y', strtotime($product['harvest_date'] ?? '2024-01-01')); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Storage:</strong> Keep in cool, dry place</p>
                                <p><strong>Shelf Life:</strong> 12 months</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reviews Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <h4 class="section-title">Customer Reviews</h4>
                    
                    <!-- Add Review Form -->
                    <?php if(!$user_review && isset($_SESSION['user_id'])): ?>
                    <div class="review-card">
                        <h5>Add Your Review</h5>
                        <div class="star-rating" id="review-stars">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <input type="hidden" id="rating-value" value="5">
                        <div class="mb-3">
                            <textarea class="form-control" id="review-text" rows="3" placeholder="Write your review here..."></textarea>
                        </div>
                        <button class="btn btn-primary" onclick="submitReview()">Submit Review</button>
                    </div>
                    <?php elseif($user_review): ?>
                    <div class="alert alert-info">
                        You have already reviewed this product.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Reviews List -->
                    <div id="reviews-container">
                        <?php if($reviews_query->num_rows > 0): ?>
                            <?php while($review = $reviews_query->fetch_assoc()): ?>
                            <div class="review-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['customer_name']); ?></strong>
                                        <div class="review-rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php if($i <= $review['rating']): ?>
                                                    ★
                                                <?php else: ?>
                                                    ☆
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span class="ms-2"><?php echo $review['rating']; ?>/5</span>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></small>
                                </div>
                                <p class="mb-0"><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No reviews yet. Be the first to review this product!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if($farmer_products_query->num_rows > 0): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h4 class="section-title">More from this Farmer</h4>
                    <div class="related-products">
                        <?php while($related = $farmer_products_query->fetch_assoc()): ?>
                        <div class="related-product-card">
                            <?php
                            $related_image = '';
                            if ($related['image']) {
                                $rel_image_name = basename($related['image']);
                                $rel_locations = [
                                    '../assets/images/' . $rel_image_name,
                                    '../' . $related['image'],
                                    '../uploads/products/' . $rel_image_name
                                ];
                                foreach ($rel_locations as $location) {
                                    if (file_exists($location)) {
                                        $related_image = $location;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <?php if($related_image): ?>
                                <img src="<?php echo $related_image; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>" style="width: 100%; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div style="height: 150px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-leaf fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div style="padding: 15px;">
                                <h6 class="mb-1"><?php echo htmlspecialchars($related['name']); ?></h6>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($related['category']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-danger">Rs. <?php echo number_format($related['price'], 2); ?></strong>
                                    <a href="product_detail.php?id=<?php echo $related['product_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen since 2020.</p>
                    <div class="mt-3">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Quick Links</h4>
                    <div class="footer-links">
                        <a href="home.php" class="text-light">Home</a><br>
                        <a href="products.php" class="text-light">Products</a><br>
                        <a href="cart.php" class="text-light">Shopping Cart</a><br>
                        <a href="orders.php" class="text-light">My Orders</a><br>
                        <a href="about.php" class="text-light">About Us</a><br>
                        <a href="contact.php" class="text-light">Contact Us</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Contact Us</h4>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Colombo, Sri Lanka</p>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> info@spiceceylon.com</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Sat: 8:00 AM - 6:00 PM</p>
                </div>
            </div>
            <hr class="mt-4 mb-3">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SpiceCeylon. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Package selection
        let selectedPackage = '1kg';
        let selectedMultiplier = 1;
        let basePrice = <?php echo $base_price; ?>;
        let selectedQuantity = 1;
        
        document.querySelectorAll('.package-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.package-btn').forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                // Update selected package
                selectedPackage = this.dataset.size;
                selectedMultiplier = parseFloat(this.dataset.multiplier);
                
                // Update display
                document.getElementById('selected-package').textContent = `(${selectedPackage})`;
                updatePrice();
            });
        });
        
        // Quantity functions
        function updateQuantity(change) {
            const input = document.getElementById('quantity');
            let newValue = parseInt(input.value) + change;
            const maxStock = <?php echo $product['stock']; ?>;
            
            if (newValue < 1) newValue = 1;
            if (newValue > maxStock) newValue = maxStock;
            
            input.value = newValue;
            selectedQuantity = newValue;
            updatePrice();
        }
        
        // Update price display
        function updatePrice() {
            const totalPrice = basePrice * selectedMultiplier * selectedQuantity;
            const unitPrice = basePrice * selectedMultiplier;
            
            document.getElementById('display-price').textContent = `Rs. ${totalPrice.toFixed(2)}`;
            document.getElementById('unit-price').textContent = `Rs. ${unitPrice.toFixed(2)} per ${selectedPackage}`;
        }
        
        // Review stars functionality
        document.querySelectorAll('#review-stars .star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                document.getElementById('rating-value').value = rating;
                
                // Update star display
                document.querySelectorAll('#review-stars .star').forEach(s => {
                    if (s.dataset.rating <= rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });
        
        // Initialize review stars
        document.addEventListener('DOMContentLoaded', function() {
            const reviewStars = document.getElementById('review-stars');
            if (reviewStars) {
                const stars = reviewStars.querySelectorAll('.star');
                stars.forEach((star, index) => {
                    if (index < 5) { // Default to 5 stars
                        star.classList.add('active');
                    }
                });
            }
        });
        
        // Add to cart function - UPDATED with better error handling
        function addToCart(productId) {
            const quantity = document.getElementById('quantity').value;
            const packageSize = selectedPackage;
            
            console.log('Adding to cart:', {productId, quantity, packageSize});
            
            fetch('actions/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}&package_size=${packageSize}`
            })
            .then(response => {
                console.log('Response status:', response.status, response.statusText);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showToast('success', 'Product added to cart!', `${selectedQuantity} × ${selectedPackage} added to your cart.`);
                    updateCartCount();
                } else {
                    showToast('error', 'Error', data.message || 'Failed to add to cart.');
                }
            })
            .catch(error => {
                console.error('Fetch error details:', error);
                showToast('error', 'Network Error', 'Check console for details or try again.');
            });
        }
        
        // Add to wishlist function - UPDATED
        function addToWishlist(productId) {
            console.log('Adding to wishlist:', productId);
            
            fetch('actions/add_to_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showToast('success', 'Added to Wishlist', 'Product added to your wishlist!');
                } else {
                    showToast('error', 'Error', data.message || 'Failed to add to wishlist.');
                }
            })
            .catch(error => {
                console.error('Fetch error details:', error);
                showToast('error', 'Error', 'An error occurred. Please try again.');
            });
        }
        
        // Submit review function - FIXED VERSION
        function submitReview() {
            const rating = document.getElementById('rating-value').value;
            const comment = document.getElementById('review-text').value.trim();
            
            if (!comment) {
                showToast('error', 'Error', 'Please write a review.');
                return;
            }
            
            if (rating < 1 || rating > 5) {
                showToast('error', 'Error', 'Please select a rating between 1-5 stars.');
                return;
            }
            
            console.log('Submitting review:', {rating, comment});
            
            // Create form data
            const formData = new URLSearchParams();
            formData.append('product_id', <?php echo $product_id; ?>);
            formData.append('rating', rating);
            formData.append('comment', comment);
            
            fetch('actions/add_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
            .then(response => {
                console.log('Response status:', response.status, response.statusText);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showToast('success', 'Review Submitted', 'Thank you for your review!');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showToast('error', 'Error', data.message || 'Failed to submit review.');
                }
            })
            .catch(error => {
                console.error('Fetch error details:', error);
                showToast('error', 'Network Error', 'Could not submit review. Check console for details.');
            });
        }
        
        // Update cart count
        function updateCartCount() {
            fetch('actions/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartBadge = document.querySelector('.nav-link[href="cart.php"] .badge');
                    if (data.count > 0) {
                        if (cartBadge) {
                            cartBadge.textContent = data.count;
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-danger';
                            badge.textContent = data.count;
                            document.querySelector('.nav-link[href="cart.php"]').appendChild(badge);
                        }
                    } else if (cartBadge) {
                        cartBadge.remove();
                    }
                })
                .catch(error => {
                    console.error('Error updating cart count:', error);
                });
        }
        
        // Toast notification
        function showToast(type, title, message) {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.position-fixed.top-0.end-0');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '1060';
            
            const bgColor = type === 'success' ? '#27ae60' : '#e74c3c';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header" style="background: ${bgColor}; color: white;">
                        <i class="fas fa-${icon} me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Add event listener to close button
            const closeBtn = toast.querySelector('.btn-close');
            closeBtn.addEventListener('click', () => {
                toast.remove();
            });
            
            // Remove toast after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }
        
        // Initialize quantity input
        document.getElementById('quantity').addEventListener('change', function() {
            selectedQuantity = parseInt(this.value) || 1;
            const maxStock = <?php echo $product['stock']; ?>;
            
            if (selectedQuantity < 1) selectedQuantity = 1;
            if (selectedQuantity > maxStock) selectedQuantity = maxStock;
            
            this.value = selectedQuantity;
            updatePrice();
        });
        
        // Initialize page
        updatePrice();
        
        // Add debug info
        console.log('Page initialized for product ID: <?php echo $product_id; ?>');
        console.log('Base price: Rs. <?php echo $base_price; ?>');
        console.log('Stock: <?php echo $product['stock']; ?> kg');
    </script>
</body>
</html>