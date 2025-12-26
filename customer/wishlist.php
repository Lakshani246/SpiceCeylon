<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Check if wishlist table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'wishlist'");
if ($table_check->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS `wishlist` (
        `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`wishlist_id`),
        UNIQUE KEY `unique_wishlist` (`customer_id`,`product_id`),
        KEY `customer_id` (`customer_id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($create_table);
}

// Handle add to wishlist
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $product_id = $_GET['add'];
    
    // Check if product exists and is approved
    $check_product = $conn->prepare("SELECT product_id FROM products WHERE product_id = ? AND status = 'Approved' AND admin_approved = 'approved'");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $check_result = $check_product->get_result();
    
    if ($check_result->num_rows > 0) {
        // Check if already in wishlist
        $check_wishlist = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE customer_id = ? AND product_id = ?");
        $check_wishlist->bind_param("ii", $user_id, $product_id);
        $check_wishlist->execute();
        $wishlist_result = $check_wishlist->get_result();
        
        if ($wishlist_result->num_rows == 0) {
            $add_stmt = $conn->prepare("INSERT INTO wishlist (customer_id, product_id) VALUES (?, ?)");
            $add_stmt->bind_param("ii", $user_id, $product_id);
            $add_stmt->execute();
            $_SESSION['success_message'] = "Product added to wishlist!";
        } else {
            $_SESSION['info_message'] = "Product is already in your wishlist!";
        }
        
        $check_wishlist->close();
    } else {
        $_SESSION['error_message'] = "Product not found or not available!";
    }
    
    $check_product->close();
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: wishlist.php");
    }
    exit;
}

// Handle remove from wishlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $wishlist_id = $_GET['remove'];
    
    $remove_stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id = ? AND customer_id = ?");
    $remove_stmt->bind_param("ii", $wishlist_id, $user_id);
    $remove_stmt->execute();
    
    $_SESSION['success_message'] = "Product removed from wishlist!";
    header("Location: wishlist.php");
    exit;
}

// Clear all wishlist items
if (isset($_GET['clear'])) {
    $clear_stmt = $conn->prepare("DELETE FROM wishlist WHERE customer_id = ?");
    $clear_stmt->bind_param("i", $user_id);
    $clear_stmt->execute();
    
    $_SESSION['success_message'] = "Wishlist cleared!";
    header("Location: wishlist.php");
    exit;
}

// Move to cart
if (isset($_GET['move_to_cart']) && is_numeric($_GET['move_to_cart'])) {
    $product_id = $_GET['move_to_cart'];
    
    // Check if product is in wishlist
    $check_wishlist = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE customer_id = ? AND product_id = ?");
    $check_wishlist->bind_param("ii", $user_id, $product_id);
    $check_wishlist->execute();
    $wishlist_result = $check_wishlist->get_result();
    
    if ($wishlist_result->num_rows > 0) {
        // Check if already in cart
        $check_cart = $conn->prepare("SELECT cart_id FROM cart WHERE customer_id = ? AND product_id = ?");
        $check_cart->bind_param("ii", $user_id, $product_id);
        $check_cart->execute();
        $cart_result = $check_cart->get_result();
        
        if ($cart_result->num_rows == 0) {
            // Add to cart
            $add_cart = $conn->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, 1)");
            $add_cart->bind_param("ii", $user_id, $product_id);
            $add_cart->execute();
            $add_cart->close();
            
            // Remove from wishlist
            $remove_stmt = $conn->prepare("DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?");
            $remove_stmt->bind_param("ii", $user_id, $product_id);
            $remove_stmt->execute();
            
            $_SESSION['success_message'] = "Product moved to cart!";
        } else {
            $_SESSION['info_message'] = "Product is already in your cart!";
        }
        $check_cart->close();
    }
    $check_wishlist->close();
    
    header("Location: wishlist.php");
    exit;
}

// Get wishlist items with product details
$wishlist_query = "
    SELECT w.wishlist_id, w.created_at, 
           p.product_id, p.name, p.description, p.category, p.price, p.stock, p.image,
           u.name as farmer_name, u.farm_location
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    JOIN users u ON p.farmer_id = u.user_id
    WHERE w.customer_id = ? 
    AND p.status = 'Approved' 
    AND p.admin_approved = 'approved'
    ORDER BY w.created_at DESC
";
$wishlist_stmt = $conn->prepare($wishlist_query);
$wishlist_stmt->bind_param("i", $user_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();

// Get cart count
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-light: #f8f9fa;
            --spice-pink: #e84393;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--spice-light);
            color: #333;
            padding-bottom: 50px;
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
        
        .nav-link:hover, .nav-link.active {
            color: var(--spice-red) !important;
        }
        
        /* Wishlist Container */
        .wishlist-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Header */
        .wishlist-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .wishlist-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .wishlist-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Wishlist Card */
        .wishlist-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .wishlist-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        
        .wishlist-card.featured {
            border: 2px solid var(--spice-pink);
        }
        
        .wishlist-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--spice-pink);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 1;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-details {
            padding: 20px;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
        
        .product-price {
            font-weight: 700;
            color: var(--spice-red);
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .product-category {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .farmer-info {
            color: #666;
            font-size: 0.85rem;
        }
        
        .farmer-info i {
            color: var(--spice-green);
            margin-right: 5px;
        }
        
        .stock-info {
            display: inline-block;
            padding: 3px 10px;
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
        
        .added-date {
            color: #999;
            font-size: 0.8rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .added-date i {
            margin-right: 5px;
        }
        
        /* Action Buttons */
        .wishlist-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-wishlist {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-add-cart {
            background: var(--spice-green);
            color: white;
        }
        
        .btn-add-cart:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.2);
        }
        
        .btn-remove {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .btn-remove:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.2);
        }
        
        .btn-view {
            background: var(--spice-blue);
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        /* Empty State */
        .empty-wishlist {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
            margin: 40px 0;
        }
        
        .empty-wishlist-icon {
            font-size: 5rem;
            color: var(--spice-pink);
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-wishlist h3 {
            color: var(--spice-dark);
            margin-bottom: 10px;
        }
        
        .empty-wishlist p {
            color: #666;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Wishlist Actions */
        .wishlist-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn-clear-wishlist {
            background: transparent;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-clear-wishlist:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
            border-left: 4px solid var(--spice-green);
        }
        
        .alert-info {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
            border-left: 4px solid var(--spice-blue);
        }
        
        .alert-warning {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
            border-left: 4px solid var(--spice-gold);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .quick-action-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        /* Footer */
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }
        
        @media (max-width: 768px) {
            .wishlist-card {
                margin-bottom: 20px;
            }
            
            .wishlist-actions {
                flex-direction: column;
            }
            
            .btn-wishlist {
                width: 100%;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-action-card {
                min-width: 100%;
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
            <div class="collapse navbar-collapse">
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
                    <li class="nav-item"><a class="nav-link active" href="wishlist.php">Wishlist</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Wishlist Container -->
    <div class="wishlist-container">
        <!-- Header -->
        <div class="wishlist-header">
            <h1><i class="fas fa-heart me-2" style="color: var(--spice-pink);"></i>My Wishlist</h1>
            <p>Save your favorite spices for later. Move items to cart when you're ready to buy!</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo $_SESSION['info_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-card">
                <i class="fas fa-shopping-cart text-success"></i>
                <h6 class="mb-1">Move to Cart</h6>
                <small class="text-muted">Add wishlist items to cart</small>
            </div>
            <div class="quick-action-card">
                <i class="fas fa-sync-alt text-primary"></i>
                <h6 class="mb-1">Keep for Later</h6>
                <small class="text-muted">Save items you want later</small>
            </div>
            <div class="quick-action-card">
                <i class="fas fa-store text-warning"></i>
                <h6 class="mb-1">Continue Shopping</h6>
                <small class="text-muted">Browse more spices</small>
            </div>
        </div>

        <?php if ($wishlist_result->num_rows > 0): ?>
            <!-- Wishlist Header Actions -->
            <div class="wishlist-header-actions">
                <div>
                    <span class="text-muted"><?php echo $wishlist_result->num_rows; ?> item(s) in wishlist</span>
                </div>
                <div>
                    <a href="wishlist.php?clear=true" class="btn btn-clear-wishlist" onclick="return confirm('Clear all items from wishlist?')">
                        <i class="fas fa-trash-alt me-2"></i>Clear Wishlist
                    </a>
                </div>
            </div>

            <!-- Wishlist Items Grid -->
            <div class="row">
                <?php while ($item = $wishlist_result->fetch_assoc()): 
                    $image_path = '';
                    if ($item['image']) {
                        $image_name = basename($item['image']);
                        if (file_exists('../assets/images/' . $image_name)) {
                            $image_path = '../assets/images/' . $image_name;
                        } else {
                            $image_path = '../assets/images/default-spice.jpg';
                        }
                    } else {
                        $image_path = '../assets/images/default-spice.jpg';
                    }
                    
                    $stock_class = 'stock-in';
                    $stock_text = 'In Stock';
                    if ($item['stock'] <= 0) {
                        $stock_class = 'stock-out';
                        $stock_text = 'Out of Stock';
                    } elseif ($item['stock'] < 10) {
                        $stock_class = 'stock-low';
                        $stock_text = 'Low Stock';
                    }
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="wishlist-card">
                        <?php if (strtotime($item['created_at']) > strtotime('-1 day')): ?>
                            <span class="wishlist-badge"><i class="fas fa-star me-1"></i>New</span>
                        <?php endif; ?>
                        
                        <img src="<?php echo $image_path; ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="product-image"
                             onerror="this.src='../assets/images/default-spice.jpg'">
                        
                        <div class="product-details">
                            <h5 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h5>
                            
                            <p class="product-description">
                                <?php echo substr(htmlspecialchars($item['description']), 0, 100); ?>
                                <?php if(strlen($item['description']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <p class="product-price">Rs. <?php echo number_format($item['price'], 2); ?> / kg</p>
                            
                            <div class="<?php echo $stock_class; ?> stock-info">
                                <i class="fas fa-<?php echo $stock_class == 'stock-out' ? 'times' : ($stock_class == 'stock-low' ? 'exclamation-triangle' : 'check'); ?> me-1"></i>
                                <?php echo $stock_text; ?> (<?php echo $item['stock']; ?> kg)
                            </div>
                            
                            <div class="product-meta">
                                <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <span class="farmer-info">
                                    <i class="fas fa-tractor"></i>
                                    <?php echo htmlspecialchars($item['farmer_name']); ?>
                                </span>
                            </div>
                            
                            <div class="added-date">
                                <i class="far fa-calendar"></i>
                                Added <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                            </div>
                            
                            <div class="wishlist-actions">
                                <?php if ($item['stock'] > 0): ?>
                                    <a href="wishlist.php?move_to_cart=<?php echo $item['product_id']; ?>" 
                                       class="btn-wishlist btn-add-cart">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </a>
                                <?php else: ?>
                                    <button class="btn-wishlist btn-add-cart" disabled style="opacity: 0.6;">
                                        <i class="fas fa-times"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                                
                                <a href="wishlist.php?remove=<?php echo $item['wishlist_id']; ?>" 
                                   class="btn-wishlist btn-remove"
                                   onclick="return confirm('Remove <?php echo htmlspecialchars($item['name']); ?> from wishlist?')">
                                    <i class="fas fa-trash"></i> Remove
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Move All to Cart Button -->
            <div class="text-center mt-4">
                <a href="move_all_to_cart.php" class="btn" 
                   style="background: var(--spice-pink); color: white; padding: 12px 30px; border-radius: 8px; font-weight: 500;"
                   onclick="return confirm('Move all available items to cart? Out of stock items will remain in wishlist.')">
                    <i class="fas fa-shopping-cart me-2"></i>Move All Available Items to Cart
                </a>
            </div>
        <?php else: ?>
            <!-- Empty Wishlist State -->
            <div class="empty-wishlist">
                <div class="empty-wishlist-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Your Wishlist is Empty</h3>
                <p>You haven't added any spices to your wishlist yet. Browse our collection and save your favorites for later!</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="home.php" class="btn" style="background: var(--spice-red); color: white; padding: 12px 30px; border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-store me-2"></i>Browse Spices
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary" style="padding: 12px 30px; border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Need Help?</h4>
                    <p><i class="fas fa-heart me-2"></i> Wishlist Support</p>
                    <p><i class="fas fa-envelope me-2"></i> help@spiceceylon.com</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Quick Links</h4>
                    <p><i class="fas fa-shopping-cart me-2"></i> <a href="cart.php" class="text-light text-decoration-none">View Cart</a></p>
                    <p><i class="fas fa-history me-2"></i> <a href="orders.php" class="text-light text-decoration-none">Order History</a></p>
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
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        // Add to wishlist from other pages
        function addToWishlist(productId, productName) {
            fetch('wishlist.php?add=' + productId)
                .then(response => {
                    if (response.ok) {
                        // Show success message
                        const toast = document.createElement('div');
                        toast.className = 'position-fixed bottom-0 end-0 p-3';
                        toast.style.zIndex = '11';
                        toast.innerHTML = `
                            <div class="toast show" role="alert">
                                <div class="toast-header" style="background: var(--spice-pink); color: white;">
                                    <i class="fas fa-heart me-2"></i>
                                    <strong class="me-auto">Added to Wishlist</strong>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                                </div>
                                <div class="toast-body">
                                    <strong>${productName}</strong> added to wishlist!
                                </div>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                        
                        // Update wishlist count
                        updateWishlistCount();
                    }
                });
        }
        
        // Update wishlist count in navbar
        function updateWishlistCount() {
            // This would need an endpoint to get wishlist count
            // For now, just reload the page
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    </script>
</body>
</html>

<?php
// Close connections
if (isset($wishlist_stmt)) $wishlist_stmt->close();
if (isset($cart_stmt)) $cart_stmt->close();
$conn->close();
?>