<?php
session_start();
include "../config/db.php";

// Ensure user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Get search filter if exists
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : 'all';

// Build query for approved products
$query = "SELECT p.*, u.name as farmer_name, u.farm_location 
          FROM products p 
          JOIN users u ON p.farmer_id = u.user_id 
          WHERE p.admin_approved = 'approved' 
          AND p.status = 'Approved'";

// Add search filter
if (!empty($search)) {
    $query .= " AND (p.name LIKE '%$search%' 
                    OR p.description LIKE '%$search%' 
                    OR p.category LIKE '%$search%'
                    OR u.name LIKE '%$search%')";
}

// Add category filter
if ($category_filter != 'all') {
    $query .= " AND p.category = '$category_filter'";
}

$query .= " ORDER BY p.created_at DESC";
$products_result = $conn->query($query);

// Get categories for filtering
$categories_query = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND admin_approved = 'approved' ORDER BY category");
$categories = [];
while($cat = $categories_query->fetch_assoc()) {
    $categories[] = $cat['category'];
}

// Count products by category
$category_counts = [];
foreach($categories as $category) {
    $count_query = $conn->query("SELECT COUNT(*) as count FROM products WHERE category = '$category' AND admin_approved = 'approved'");
    $category_counts[$category] = $count_query->fetch_assoc()['count'];
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = '{$_SESSION['user_id']}'");
    $cart_count = $cart_query->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpiceCeylon - Home</title>
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
        
        .video-banner {
            position: relative;
            height: 70vh;
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .banner-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 20px;
        }
        
        .video-overlay h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .video-overlay p {
            font-size: 1.2rem;
            max-width: 600px;
            margin-bottom: 30px;
        }
        
        .section-title {
            text-align: center;
            margin: 50px 0 30px;
            color: var(--spice-dark);
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--spice-red);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            padding: 20px 0;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--spice-dark);
        }
        
        .product-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--spice-red);
        }
        
        .product-category {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view {
            flex: 1;
            background: var(--spice-blue);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-view:hover {
            background: #2980b9;
            color: white;
            text-decoration: none;
        }
        
        .btn-cart {
            flex: 1;
            background: var(--spice-green);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-cart:hover {
            background: #219653;
        }
        
        .category-filter {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 30px 0;
            padding: 0 20px;
        }
        
        .category-btn {
            padding: 8px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            color: var(--spice-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category-btn:hover, .category-btn.active {
            background: var(--spice-red);
            border-color: var(--spice-red);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            margin: 40px 0;
            border: 2px dashed #e9ecef;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        .farmer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
        }
        
        .farmer-info i {
            color: var(--spice-green);
        }
        
        .stock-info {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 10px;
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
        
        .search-bar {
            max-width: 600px;
            margin: 30px auto;
            position: relative;
        }
        
        .search-bar input {
            width: 100%;
            padding: 15px 20px;
            padding-right: 60px;
            border: 2px solid #e9ecef;
            border-radius: 30px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-bar input:focus {
            border-color: var(--spice-blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .search-bar button {
            position: absolute;
            right: 5px;
            top: 5px;
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-bar button:hover {
            background: #a04a2c;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--spice-red);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 50px 0 20px;
            margin-top: 80px;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        @media (max-width: 768px) {
            .video-overlay h1 {
                font-size: 2.5rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .hero-stats {
                gap: 20px;
            }
            
            .stat-value {
                font-size: 1.5rem;
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
                    <li class="nav-item"><a class="nav-link active" href="home.php">Home</a></li>
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

    <!-- Video Banner -->
    <div class="video-banner">
        <video autoplay muted loop class="banner-video">
            <source src="../assets/videos/landing-video.mp4" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
        <div class="video-overlay">
            <h1>Welcome to SpiceCeylon</h1>
            <p>Discover authentic Sri Lankan spices directly from farmers. Fresh, organic, and fair trade.</p>
            <a href="#products" class="btn btn-lg" style="background: var(--spice-red); color: white; padding: 12px 30px; border-radius: 30px; text-decoration: none;">
                Shop Now <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="hero-stats">
        <div class="stat-item">
            <div class="stat-value"><?php echo $products_result->num_rows; ?></div>
            <div class="stat-label">Spice Varieties</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">100%</div>
            <div class="stat-label">Organic</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">Direct</div>
            <div class="stat-label">From Farmers</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">24/7</div>
            <div class="stat-label">Support</div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="container">
        <div class="search-bar">
            <form method="GET" action="home.php">
                <input type="text" name="search" placeholder="Search spices by name, category, or farmer..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
    </div>

    <!-- Category Filter -->
    <?php if(count($categories) > 0): ?>
    <div class="container">
        <div class="category-filter">
            <a href="home.php" class="category-btn <?php echo $category_filter == 'all' ? 'active' : ''; ?>">All Spices</a>
            <?php foreach($categories as $category): ?>
                <a href="home.php?category=<?php echo urlencode($category); ?>" 
                   class="category-btn <?php echo $category_filter == $category ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($category); ?> (<?php echo $category_counts[$category]; ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Products Section -->
    <div class="container" id="products">
        <h2 class="section-title">Our Premium Spice Collection</h2>
        <?php if($products_result->num_rows > 0): ?>
            <div class="products-grid">
                <?php while($product = $products_result->fetch_assoc()): 
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
                    
                    // Stock status
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
                <div class="product-card">
                    <?php if($image_path): ?>
                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                    <?php else: ?>
                        <div style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-leaf fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-info">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                        
                        <p class="product-description">
                            <?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>
                            <?php if(strlen($product['description']) > 100): ?>...<?php endif; ?>
                        </p>
                        
                        <div class="<?php echo $stock_class; ?> stock-info">
                            <i class="fas fa-<?php echo $stock_class == 'stock-out' ? 'times' : ($stock_class == 'stock-low' ? 'exclamation-triangle' : 'check'); ?> me-1"></i>
                            <?php echo $stock_text; ?> (<?php echo $product['stock']; ?>)
                        </div>
                        
                        <div class="product-meta">
                            <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
                            <small class="text-muted">/kg</small>
                        </div>
                        
                        <div class="farmer-info">
                            <i class="fas fa-tractor"></i>
                            <span>From: <?php echo htmlspecialchars($product['farmer_name']); ?></span>
                            <?php if($product['farm_location']): ?>
                                <span class="ms-2">• <?php echo htmlspecialchars($product['farm_location']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-actions mt-3">
                            <a href="product_detail.php?id=<?php echo $product['product_id']; ?>" class="btn-view">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                            <?php if($product['stock'] > 0): ?>
                                <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                    <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="btn-cart" style="background: #95a5a6;" disabled>
                                    <i class="fas fa-times me-1"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3 class="text-muted mb-3">No Products Found</h3>
                <p class="text-muted">
                    <?php if(!empty($search)): ?>
                        No products found matching "<?php echo htmlspecialchars($search); ?>"
                    <?php elseif($category_filter != 'all'): ?>
                        No products found in category "<?php echo htmlspecialchars($category_filter); ?>"
                    <?php else: ?>
                        No approved products available at the moment.
                    <?php endif; ?>
                </p>
                <a href="home.php" class="btn btn-primary">
                    <i class="fas fa-redo me-1"></i> View All Products
                </a>
            </div>
        <?php endif; ?>
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
                        <a href="home.php">Home</a>
                        <a href="products.php">Products</a>
                        <a href="cart.php">Shopping Cart</a>
                        <a href="orders.php">My Orders</a>
                        <a href="about.php">About Us</a>
                        <a href="contact.php">Contact Us</a>
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
        // Add to Cart Function - UPDATED with package_size parameter
        function addToCart(productId, productName) {
            fetch('actions/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1&package_size=1kg` // Default to 1kg when adding from home page
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '11';
                    toast.innerHTML = `
                        <div class="toast show" role="alert">
                            <div class="toast-header" style="background: #27ae60; color: white;">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                <strong>${productName}</strong> added to cart successfully!
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    // Remove toast after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                    
                    // Update cart count
                    updateCartCount();
                } else {
                    alert('Error adding to cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again. Check console for details.');
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
        
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Update active category button
        document.querySelectorAll('.category-btn').forEach(btn => {
            if (window.location.href.includes(btn.href)) {
                btn.classList.add('active');
            }
        });
    </script>
</body>
</html>