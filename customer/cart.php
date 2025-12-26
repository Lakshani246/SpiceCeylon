<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Check if cart table has package_size and total_price columns
$check_columns = $conn->query("SHOW COLUMNS FROM cart LIKE 'package_size'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE cart ADD COLUMN package_size VARCHAR(20) DEFAULT '1kg'");
}

$check_columns2 = $conn->query("SHOW COLUMNS FROM cart LIKE 'total_price'");
if ($check_columns2->num_rows == 0) {
    $conn->query("ALTER TABLE cart ADD COLUMN total_price DECIMAL(10,2)");
}

// Get cart items with product details including package size
$cart_query = "
    SELECT c.cart_id, c.quantity, c.package_size, c.price as unit_price, c.total_price, 
           p.product_id, p.name, p.price as base_price, p.image, p.stock, p.category, 
           u.name as farmer_name
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    JOIN users u ON p.farmer_id = u.user_id
    WHERE c.customer_id = ? AND p.status = 'Approved' AND p.admin_approved = 'approved'
";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

$total_amount = 0;
$cart_items = [];

if ($cart_result->num_rows > 0) {
    while($item = $cart_result->fetch_assoc()) {
        // If unit_price or total_price is not set, calculate them
        if (!isset($item['unit_price']) || $item['unit_price'] == 0 || !isset($item['total_price']) || $item['total_price'] == 0) {
            // Calculate multiplier based on package size
            $multiplier = 1;
            switch($item['package_size']) {
                case '25g': $multiplier = 0.025; break;
                case '50g': $multiplier = 0.05; break;
                case '100g': $multiplier = 0.1; break;
                case '250g': $multiplier = 0.25; break;
                case '500g': $multiplier = 0.5; break;
                case '1kg': $multiplier = 1; break;
                default: $multiplier = 1;
            }
            
            $item['unit_price'] = $item['base_price'] * $multiplier;
            $item['total_price'] = $item['unit_price'] * $item['quantity'];
            
            // Update in database
            $update_stmt = $conn->prepare("UPDATE cart SET price = ?, total_price = ? WHERE cart_id = ?");
            $update_stmt->bind_param("ddi", $item['unit_price'], $item['total_price'], $item['cart_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        $total_amount += $item['total_price'];
        $cart_items[] = $item;
    }
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $cart_id = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    
    // Get cart item details to recalculate price
    $item_query = $conn->prepare("SELECT c.package_size, p.price as base_price FROM cart c 
                                  JOIN products p ON c.product_id = p.product_id 
                                  WHERE c.cart_id = ? AND c.customer_id = ?");
    $item_query->bind_param("ii", $cart_id, $user_id);
    $item_query->execute();
    $item_result = $item_query->get_result();
    
    if ($item_result->num_rows > 0) {
        $item = $item_result->fetch_assoc();
        
        if ($quantity <= 0) {
            $delete_stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
            $delete_stmt->bind_param("ii", $cart_id, $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        } else {
            // Calculate multiplier based on package size
            $multiplier = 1;
            switch($item['package_size']) {
                case '25g': $multiplier = 0.025; break;
                case '50g': $multiplier = 0.05; break;
                case '100g': $multiplier = 0.1; break;
                case '250g': $multiplier = 0.25; break;
                case '500g': $multiplier = 0.5; break;
                case '1kg': $multiplier = 1; break;
            }
            
            $unit_price = $item['base_price'] * $multiplier;
            $total_price = $unit_price * $quantity;
            
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ?, price = ?, total_price = ? WHERE cart_id = ? AND customer_id = ?");
            $update_stmt->bind_param("iddii", $quantity, $unit_price, $total_price, $cart_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    $item_query->close();
    
    header("Location: cart.php");
    exit;
}

// Handle remove item
if (isset($_GET['remove'])) {
    $cart_id = $_GET['remove'];
    $delete_stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
    $delete_stmt->bind_param("ii", $cart_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    header("Location: cart.php");
    exit;
}

// Clear all cart items
if (isset($_GET['clear'])) {
    $clear_stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
    $clear_stmt->bind_param("i", $user_id);
    $clear_stmt->execute();
    $clear_stmt->close();
    
    header("Location: cart.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - SpiceCeylon</title>
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
        
        /* Cart Container */
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Cart Header */
        .cart-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .cart-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .cart-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Cart Items */
        .cart-item {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        .product-details {
            padding: 20px;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--spice-red);
            font-size: 1.2rem;
        }
        
        .product-meta {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .product-category {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .package-size {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
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
        
        /* Quantity Controls */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            border-color: var(--spice-red);
            color: var(--spice-red);
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px;
            font-weight: 600;
        }
        
        .quantity-input:focus {
            border-color: var(--spice-red);
            box-shadow: 0 0 0 3px rgba(184, 92, 56, 0.1);
            outline: none;
        }
        
        /* Item Total */
        .item-total {
            font-weight: 700;
            color: var(--spice-green);
            font-size: 1.2rem;
        }
        
        /* Remove Button */
        .remove-btn {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .remove-btn:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            border: 1px solid #e9ecef;
            position: sticky;
            top: 20px;
        }
        
        .summary-header {
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .summary-header h3 {
            color: var(--spice-dark);
            font-weight: 600;
            margin: 0;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e9ecef;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--spice-red);
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        /* Buttons */
        .btn-checkout {
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-checkout:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-continue {
            background: white;
            color: var(--spice-dark);
            border: 2px solid var(--spice-dark);
            padding: 13px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-continue:hover {
            background: var(--spice-dark);
            color: white;
        }
        
        .btn-clear {
            background: transparent;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-clear:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
            margin: 40px 0;
        }
        
        .empty-cart-icon {
            font-size: 5rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            color: var(--spice-dark);
            margin-bottom: 10px;
        }
        
        .empty-cart p {
            color: #666;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Cart Actions */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cart-item {
                padding: 15px;
            }
            
            .product-details {
                padding: 15px 0;
            }
            
            .quantity-controls {
                justify-content: center;
                margin: 15px 0;
            }
            
            .item-total, .remove-btn {
                text-align: center;
                justify-content: center;
            }
            
            .cart-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-clear {
                width: 100%;
            }
            
            .product-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        /* Stock Warning */
        .stock-warning {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-top: 5px;
            display: inline-block;
        }
        
        .stock-out {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        /* Update Button */
        .update-btn {
            background: var(--spice-blue);
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .update-btn:hover {
            background: #2980b9;
        }
        
        .input-group {
            width: auto;
        }
        
        /* Price display */
        .price-breakdown {
            font-size: 0.85rem;
            color: #666;
            margin-top: 3px;
        }
        
        /* Package info */
        .package-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
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
                    <li class="nav-item"><a class="nav-link active" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
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

    <!-- Cart Container -->
    <div class="cart-container">
        <div class="cart-header">
            <h1><i class="fas fa-shopping-cart me-2"></i>Your Shopping Cart</h1>
            <p>Review and manage your selected spices before checkout</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any spices to your cart yet. Browse our collection of authentic Sri Lankan spices and start filling your cart!</p>
                <a href="home.php" class="btn btn-checkout" style="max-width: 300px; margin: 0 auto;">
                    <i class="fas fa-store me-2"></i>Browse Spices
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Cart Items Column -->
                <div class="col-lg-8">
                    <!-- Cart Actions -->
                    <div class="cart-actions">
                        <div>
                            <span class="text-muted"><?php echo count($cart_items); ?> item(s) in cart</span>
                        </div>
                        <div>
                            <a href="cart.php?clear=true" class="btn btn-clear" onclick="return confirm('Clear all items from cart?')">
                                <i class="fas fa-trash-alt me-2"></i>Clear Cart
                            </a>
                        </div>
                    </div>

                    <!-- Cart Items List -->
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): 
                            $image_path = '';
                            if ($item['image']) {
                                $image_name = basename($item['image']);
                                if (file_exists('../assets/images/' . $image_name)) {
                                    $image_path = '../assets/images/' . $image_name;
                                } elseif (file_exists('../' . $item['image'])) {
                                    $image_path = '../' . $item['image'];
                                } else {
                                    $image_path = '../assets/images/default-spice.jpg';
                                }
                            } else {
                                $image_path = '../assets/images/default-spice.jpg';
                            }
                            
                            // Calculate stock needed based on package size
                            $multiplier = 1;
                            switch($item['package_size']) {
                                case '25g': $multiplier = 0.025; break;
                                case '50g': $multiplier = 0.05; break;
                                case '100g': $multiplier = 0.1; break;
                                case '250g': $multiplier = 0.25; break;
                                case '500g': $multiplier = 0.5; break;
                                case '1kg': $multiplier = 1; break;
                                default: $multiplier = 1;
                            }
                            $stock_needed = $item['quantity'] * $multiplier;
                        ?>
                        <div class="cart-item">
                            <div class="row align-items-center p-3">
                                <!-- Product Image -->
                                <div class="col-lg-2 col-md-3 col-4">
                                    <img src="<?php echo $image_path; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="product-image"
                                         onerror="this.src='../assets/images/default-spice.jpg'">
                                </div>
                                
                                <!-- Product Details -->
                                <div class="col-lg-5 col-md-4 col-8">
                                    <div class="product-details">
                                        <h5 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <p class="product-price mb-2">Rs. <?php echo number_format($item['unit_price'], 2); ?></p>
                                        
                                        <div class="price-breakdown">
                                            <small class="text-muted">
                                                (Rs. <?php echo number_format($item['base_price'], 2); ?>/kg × 
                                                <?php echo $item['package_size']; ?>)
                                            </small>
                                        </div>
                                        
                                        <div class="product-meta">
                                            <span class="product-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                            <span class="package-size">
                                                <i class="fas fa-weight-hanging me-1"></i>
                                                <?php echo htmlspecialchars($item['package_size']); ?>
                                            </span>
                                            <span class="farmer-info">
                                                <i class="fas fa-tractor"></i>
                                                <?php echo htmlspecialchars($item['farmer_name']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($item['stock'] < $stock_needed): ?>
                                            <div class="stock-warning stock-out mt-2">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Only <?php echo $item['stock']; ?> kg available. 
                                                You need <?php echo number_format($stock_needed, 3); ?> kg for <?php echo $item['quantity']; ?> × <?php echo $item['package_size']; ?>
                                            </div>
                                        <?php elseif ($item['stock'] < 10): ?>
                                            <div class="stock-warning mt-2">
                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                Low stock: <?php echo $item['stock']; ?> kg left
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Quantity Controls -->
                                <div class="col-lg-3 col-md-3 col-12 mt-3 mt-md-0">
                                    <form method="POST" class="d-flex flex-column align-items-center">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <div class="input-group" style="width: auto;">
                                            <button type="button" class="btn btn-outline-secondary quantity-btn" 
                                                    onclick="changeQuantity(this, -1, <?php echo $item['stock']; ?>, '<?php echo $item['package_size']; ?>')">-</button>
                                            <input type="number" name="quantity" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo floor($item['stock'] / $multiplier); ?>"
                                                   class="form-control quantity-input text-center"
                                                   data-multiplier="<?php echo $multiplier; ?>">
                                            <button type="button" class="btn btn-outline-secondary quantity-btn" 
                                                    onclick="changeQuantity(this, 1, <?php echo $item['stock']; ?>, '<?php echo $item['package_size']; ?>')">+</button>
                                        </div>
                                        <button type="submit" name="update_quantity" class="btn update-btn mt-2">
                                            Update
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Item Total & Remove -->
                                <div class="col-lg-2 col-md-2 col-12 mt-3 mt-md-0">
                                    <div class="d-flex flex-column align-items-center">
                                        <p class="item-total mb-3">Rs. <?php echo number_format($item['total_price'], 2); ?></p>
                                        <a href="cart.php?remove=<?php echo $item['cart_id']; ?>" 
                                           class="remove-btn"
                                           onclick="return confirm('Remove <?php echo htmlspecialchars($item['name']); ?> from cart?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary Column -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <div class="summary-header">
                            <h3><i class="fas fa-receipt me-2"></i>Order Summary</h3>
                        </div>
                        
                        <div class="summary-item">
                            <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
                            <span>Rs. <?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Shipping Fee</span>
                            <span>Rs. 200.00</span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Estimated Tax (8%)</span>
                            <span>Rs. <?php echo number_format($total_amount * 0.08, 2); ?></span>
                        </div>
                        
                        <?php if ($total_amount > 5000): ?>
                        <div class="summary-item text-success">
                            <span><i class="fas fa-truck me-1"></i>Free Shipping</span>
                            <span>-Rs. 200.00</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-total">
                            <span>Total Amount</span>
                            <span>Rs. <?php 
                                $shipping = ($total_amount > 5000) ? 0 : 200;
                                $tax = $total_amount * 0.08;
                                echo number_format($total_amount + $shipping + $tax, 2); 
                            ?></span>
                        </div>
                        
                        <div class="mt-4">
                            <p class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Free shipping on orders over Rs. 5000
                            </p>
                        </div>
                        
                        <div class="d-grid gap-3 mt-4">
                            <a href="checkout.php" class="btn btn-checkout">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </a>
                            <a href="home.php" class="btn btn-continue">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer style="background: var(--spice-dark); color: white; padding: 40px 0 20px; margin-top: 80px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Need Help?</h4>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> support@spiceceylon.com</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Secure Payment</h4>
                    <p><i class="fas fa-shield-alt me-2"></i>100% Secure Checkout</p>
                    <p><i class="fas fa-truck me-2"></i>Fast Delivery</p>
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
        // Quantity change function with package size consideration
        function changeQuantity(button, change, maxStock, packageSize) {
            const form = button.closest('form');
            const input = form.querySelector('input[name="quantity"]');
            const multiplier = parseFloat(input.getAttribute('data-multiplier')) || 1;
            let currentValue = parseInt(input.value);
            let newValue = currentValue + change;
            
            // Calculate stock needed in kg
            const stockNeeded = newValue * multiplier;
            
            // Validate
            if (newValue < 1) {
                if (confirm('Remove this item from cart?')) {
                    input.value = 0;
                    form.submit();
                }
                return;
            }
            
            if (stockNeeded > maxStock) {
                alert('Cannot add more than available stock (' + maxStock.toFixed(3) + ' kg)\n' +
                      'You can add maximum ' + Math.floor(maxStock / multiplier) + ' items of ' + packageSize);
                return;
            }
            
            input.value = newValue;
        }
        
        // Auto-submit when quantity input changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value >= 1) {
                    this.closest('form').submit();
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close connections
if (isset($cart_stmt)) $cart_stmt->close();
$conn->close();
?>