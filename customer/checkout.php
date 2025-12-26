<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Debug: Check if we're receiving POST data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST Data Received: " . print_r($_POST, true));
}

// Get user details for pre-filling form
$user_query = "SELECT name, email, phone, address FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Get cart items with product details
$cart_query = "
    SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.image, p.stock, p.category, 
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
        $item_total = $item['price'] * $item['quantity'];
        $total_amount += $item_total;
        $cart_items[] = $item;
    }
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    error_log("Checkout process started for user: $user_id");
    
    // Get form data
    $shipping_name = trim($_POST['shipping_name']);
    $shipping_phone = trim($_POST['shipping_phone']);
    $shipping_address = trim($_POST['shipping_address']);
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_postal = trim($_POST['shipping_postal']);
    $payment_method = trim($_POST['payment_method']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    error_log("Form data collected: $shipping_name, $payment_method");

    // Validate required fields
    if (empty($shipping_name) || empty($shipping_phone) || empty($shipping_address) || 
        empty($shipping_city) || empty($shipping_postal) || empty($payment_method)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: checkout.php");
        exit;
    }

    // Get card details if credit card is selected
    $card_name = $card_number = $card_expiry = $card_cvv = '';
    $card_saved = 0;
    
    if ($payment_method == 'credit_card') {
        $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
        $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
        $card_expiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : '';
        $card_cvv = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : '';
        $card_saved = isset($_POST['save_card']) ? 1 : 0;
        
        // Validate card details
        if (empty($card_name) || empty($card_number) || empty($card_expiry) || empty($card_cvv)) {
            $_SESSION['error'] = "Please fill in all card details for credit card payment";
            header("Location: checkout.php");
            exit;
        }
        
        // Basic card validation
        $card_number_clean = str_replace(' ', '', $card_number);
        if (!preg_match('/^[0-9]{16}$/', $card_number_clean)) {
            $_SESSION['error'] = "Invalid card number. Must be 16 digits";
            header("Location: checkout.php");
            exit;
        }
        
        if (!preg_match('/^[0-9]{3,4}$/', $card_cvv)) {
            $_SESSION['error'] = "Invalid CVV. Must be 3 or 4 digits";
            header("Location: checkout.php");
            exit;
        }
    }

    // Calculate totals
    $shipping_fee = 200.00;
    $tax_amount = $total_amount * 0.08;
    $final_total = $total_amount + $shipping_fee + $tax_amount;

    // Check if cart is empty
    if (empty($cart_items)) {
        $_SESSION['error'] = "Your cart is empty!";
        header("Location: cart.php");
        exit;
    }

    error_log("Starting transaction for order");
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Debug: Check connection
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        // Create order with prepared statement
        $order_query = "
            INSERT INTO orders (
                customer_id, total_amount, shipping_fee, final_total,
                shipping_name, shipping_phone, shipping_address, shipping_city, 
                shipping_postal, payment_method, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ";
        
        $order_stmt = $conn->prepare($order_query);
        if (!$order_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $bind_result = $order_stmt->bind_param("idddsssssss", 
            $user_id, $total_amount, $shipping_fee, $final_total,
            $shipping_name, $shipping_phone, $shipping_address, $shipping_city,
            $shipping_postal, $payment_method, $notes
        );
        
        if (!$bind_result) {
            throw new Exception("Bind failed: " . $order_stmt->error);
        }
        
        $execute_result = $order_stmt->execute();
        if (!$execute_result) {
            throw new Exception("Execute failed: " . $order_stmt->error);
        }
        
        $order_id = $conn->insert_id;
        error_log("Order created successfully with ID: $order_id");
        
        // If credit card payment, save card details
        if ($payment_method == 'credit_card' && $card_saved) {
            $card_last_four = substr(str_replace(' ', '', $card_number), -4);
            $card_type = (substr($card_number, 0, 1) == '4') ? 'Visa' : 'MasterCard';
            
            $card_query = "
                INSERT INTO payment_cards (customer_id, order_id, card_name, 
                         card_last_four, card_expiry, card_type, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ";
            $card_stmt = $conn->prepare($card_query);
            if ($card_stmt) {
                $card_stmt->bind_param("iissss", 
                    $user_id, $order_id, $card_name, $card_last_four, $card_expiry, $card_type
                );
                
                if (!$card_stmt->execute()) {
                    error_log("Card save failed: " . $card_stmt->error);
                    // Don't throw exception for card save failure, just log it
                }
                $card_stmt->close();
            }
        }
        
        // Add order items
        $order_item_query = "
            INSERT INTO order_items (order_id, product_id, quantity, price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ";
        $order_item_stmt = $conn->prepare($order_item_query);
        if (!$order_item_stmt) {
            throw new Exception("Prepare order items failed: " . $conn->error);
        }
        
        foreach ($cart_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $bind_result = $order_item_stmt->bind_param("iiidd", 
                $order_id, $item['product_id'], $item['quantity'], 
                $item['price'], $item_total
            );
            
            if (!$bind_result) {
                throw new Exception("Bind order item failed: " . $order_item_stmt->error);
            }
            
            if (!$order_item_stmt->execute()) {
                throw new Exception("Execute order item failed: " . $order_item_stmt->error);
            }
            
            error_log("Order item added: {$item['product_id']} x {$item['quantity']}");
        }
        $order_item_stmt->close();

        // Clear cart
        $clear_cart_stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        if ($clear_cart_stmt) {
            $clear_cart_stmt->bind_param("i", $user_id);
            if (!$clear_cart_stmt->execute()) {
                throw new Exception("Clear cart failed: " . $clear_cart_stmt->error);
            }
            $clear_cart_stmt->close();
        }

        // Commit transaction
        $conn->commit();
        error_log("Transaction committed successfully");
        
        // Store success message in session
        $_SESSION['order_success'] = true;
        $_SESSION['order_id'] = $order_id;
        
        // Redirect to success page
        header("Location: order_success.php?order_id=$order_id");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Checkout error: " . $e->getMessage());
        $_SESSION['error'] = "Order failed: " . $e->getMessage();
        header("Location: checkout.php");
        exit;
    } finally {
        // Close statements
        if (isset($order_stmt)) $order_stmt->close();
    }
}

// Display error message if any
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SpiceCeylon</title>
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
        
        /* Checkout Container */
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Checkout Header */
        .checkout-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .checkout-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .checkout-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        
        .section-header {
            background: rgba(184, 92, 56, 0.05);
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding: 20px;
        }
        
        .section-header h3 {
            color: var(--spice-dark);
            font-weight: 600;
            margin: 0;
            font-size: 1.3rem;
        }
        
        .section-header h3 i {
            color: var(--spice-red);
            margin-right: 10px;
        }
        
        .section-body {
            padding: 25px;
        }
        
        /* Form Elements */
        .form-label {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--spice-red);
            box-shadow: 0 0 0 3px rgba(184, 92, 56, 0.1);
            outline: none;
        }
        
        /* Payment Methods */
        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-method:hover {
            border-color: var(--spice-red);
        }
        
        .payment-method.selected {
            border-color: var(--spice-red);
            background: rgba(184, 92, 56, 0.03);
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
        
        /* Cart Items in Summary */
        .cart-item-summary {
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
        }
        
        .cart-item-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
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
        }
        
        .btn-back {
            background: white;
            color: var(--spice-dark);
            border: 2px solid var(--spice-dark);
            padding: 13px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: var(--spice-dark);
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
        
        /* Alert */
        .alert-custom {
            border-radius: 8px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        /* Terms & Conditions */
        .terms-check {
            background: rgba(184, 92, 56, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .required {
            color: #e74c3c;
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
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="checkout.php">Checkout</a></li>
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

    <!-- Checkout Container -->
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fas fa-shopping-bag me-2"></i>Checkout</h1>
            <p>Complete your purchase securely in just a few steps</p>
        </div>

        <!-- Error Alert -->
        <?php if($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

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
            <form method="POST" id="checkout-form" action="checkout.php">
                <div class="row">
                    <!-- Left Column: Forms -->
                    <div class="col-lg-8">
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-truck"></i>Shipping Information <span class="required">*</span></h3>
                            </div>
                            <div class="section-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="shipping_name" class="form-label">Full Name <span class="required">*</span></label>
                                        <input type="text" class="form-control" 
                                               id="shipping_name" name="shipping_name" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_phone" class="form-label">Phone Number <span class="required">*</span></label>
                                        <input type="tel" class="form-control" 
                                               id="shipping_phone" name="shipping_phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-12">
                                        <label for="shipping_address" class="form-label">Address <span class="required">*</span></label>
                                        <textarea class="form-control" id="shipping_address" 
                                                  name="shipping_address" rows="3" required
                                                  placeholder="Enter your complete address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_city" class="form-label">City <span class="required">*</span></label>
                                        <input type="text" class="form-control" 
                                               id="shipping_city" name="shipping_city" 
                                               placeholder="Enter your city" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_postal" class="form-label">Postal Code <span class="required">*</span></label>
                                        <input type="text" class="form-control" 
                                               id="shipping_postal" name="shipping_postal" 
                                               placeholder="Enter postal code" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-credit-card"></i>Payment Method <span class="required">*</span></h3>
                            </div>
                            <div class="section-body">
                                <div class="payment-method" onclick="selectPayment('cash')">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" 
                                               name="payment_method" id="cash" 
                                               value="cash_on_delivery" required checked>
                                        <label class="form-check-label fw-bold h5 mb-0" for="cash">
                                            <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                        </label>
                                    </div>
                                    <p class="text-muted mt-2 ps-4 mb-0">
                                        Pay with cash when your order is delivered
                                    </p>
                                </div>
                                
                                <div class="payment-method" onclick="selectPayment('card')">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" 
                                               name="payment_method" id="card" 
                                               value="credit_card">
                                        <label class="form-check-label fw-bold h5 mb-0" for="card">
                                            <i class="fas fa-credit-card me-2"></i>Credit/Debit Card
                                        </label>
                                    </div>
                                    <p class="text-muted mt-2 ps-4 mb-0">
                                        Pay securely with your credit or debit card
                                    </p>
                                </div>

                                <!-- Credit Card Details Form -->
                                <div id="cardDetails" class="card-details-container" style="display: none;">
                                    <div class="card border-0 shadow-sm mt-3">
                                        <div class="card-header bg-white border-bottom">
                                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Card Details</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <label for="card_name" class="form-label">Name on Card *</label>
                                                    <input type="text" class="form-control" id="card_name" name="card_name" 
                                                           placeholder="John Doe" maxlength="50">
                                                </div>
                                                
                                                <div class="col-12 mb-3">
                                                    <label for="card_number" class="form-label">Card Number *</label>
                                                    <input type="text" class="form-control" id="card_number" name="card_number" 
                                                           placeholder="1234567890123456" maxlength="16">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="card_expiry" class="form-label">Expiry Date (MMYY) *</label>
                                                    <input type="text" class="form-control" id="card_expiry" name="card_expiry" 
                                                           placeholder="MMYY" maxlength="4">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="card_cvv" class="form-label">CVV *</label>
                                                    <input type="password" class="form-control" id="card_cvv" name="card_cvv" 
                                                           placeholder="123" maxlength="4">
                                                </div>
                                                
                                                <div class="col-12 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="save_card" name="save_card" value="1">
                                                        <label class="form-check-label" for="save_card">
                                                            Save this card for future purchases
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Notes -->
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-sticky-note"></i>Order Notes (Optional)</h3>
                            </div>
                            <div class="section-body">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" 
                                              rows="4" placeholder="Any special instructions for your order..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Order Summary -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <div class="summary-header">
                                <h3><i class="fas fa-receipt me-2"></i>Order Summary</h3>
                                <small class="text-muted"><?php echo count($cart_items); ?> item(s) in cart</small>
                            </div>
                            
                            <!-- Cart Items in Summary -->
                            <div class="mb-4" style="max-height: 300px; overflow-y: auto;">
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
                                ?>
                                <div class="cart-item-summary">
                                    <div class="d-flex">
                                        <img src="<?php echo $image_path; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="cart-item-image me-3"
                                             onerror="this.src='../assets/images/default-spice.jpg'">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div class="text-muted">
                                                <small><?php echo $item['quantity']; ?> kg × Rs. <?php echo number_format($item['price'], 2); ?></small>
                                            </div>
                                            <div class="fw-bold mt-1">
                                                Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php
                            $shipping_fee = 200.00;
                            $tax_amount = $total_amount * 0.08;
                            $final_total = $total_amount + $shipping_fee + $tax_amount;
                            ?>
                            
                            <div class="summary-item">
                                <span>Subtotal</span>
                                <span>Rs. <?php echo number_format($total_amount, 2); ?></span>
                            </div>
                            
                            <div class="summary-item">
                                <span>Shipping Fee</span>
                                <span>Rs. <?php echo number_format($shipping_fee, 2); ?></span>
                            </div>
                            
                            <div class="summary-item">
                                <span>Estimated Tax</span>
                                <span>Rs. <?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            
                            <div class="summary-total">
                                <span>Total Amount</span>
                                <span>Rs. <?php echo number_format($final_total, 2); ?></span>
                            </div>
                            
                            <div class="terms-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label small" for="terms">
                                        I agree to the <a href="terms.php" class="text-decoration-none" style="color: var(--spice-red);">Terms & Conditions</a> and 
                                        <a href="privacy.php" class="text-decoration-none" style="color: var(--spice-red);">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-3 mt-4">
                                <button type="submit" name="place_order" class="btn-checkout">
                                    <i class="fas fa-lock me-2"></i>Complete Order
                                </button>
                                <a href="cart.php" class="btn btn-back">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Cart
                                </a>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-lock me-1"></i>Secure SSL Encryption
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
        // Payment method selection
        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(pm => {
                pm.classList.remove('selected');
            });
            
            const selectedElement = event.currentTarget;
            selectedElement.classList.add('selected');
            document.getElementById(method).checked = true;
            
            // Show/hide card details
            const cardDetails = document.getElementById('cardDetails');
            if (method === 'card') {
                cardDetails.style.display = 'block';
            } else {
                cardDetails.style.display = 'none';
            }
        }

        // Initialize cash on delivery as selected
        document.addEventListener('DOMContentLoaded', function() {
            selectPayment('cash');
        });

        // Form validation
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const terms = document.getElementById('terms');
            
            if (!paymentMethod) {
                e.preventDefault();
                showAlert('Please select a payment method', 'warning');
                return false;
            }
            
            if (!terms.checked) {
                e.preventDefault();
                showAlert('Please agree to the Terms & Conditions', 'warning');
                return false;
            }
            
            // Validate card details if credit card is selected
            if (paymentMethod.value === 'credit_card') {
                const cardName = document.getElementById('card_name').value.trim();
                const cardNumber = document.getElementById('card_number').value.trim();
                const cardExpiry = document.getElementById('card_expiry').value.trim();
                const cardCvv = document.getElementById('card_cvv').value.trim();
                
                if (!cardName) {
                    e.preventDefault();
                    showAlert('Please enter the name on card', 'warning');
                    return false;
                }
                
                if (cardNumber.length !== 16) {
                    e.preventDefault();
                    showAlert('Please enter a valid 16-digit card number', 'warning');
                    return false;
                }
                
                if (cardExpiry.length !== 4) {
                    e.preventDefault();
                    showAlert('Please enter expiry date in MMDD format', 'warning');
                    return false;
                }
                
                const month = cardExpiry.substring(0, 2);
                const year = cardExpiry.substring(2, 4);
                const currentYear = new Date().getFullYear() % 100;
                const currentMonth = new Date().getMonth() + 1;
                
                if (month < 1 || month > 12) {
                    e.preventDefault();
                    showAlert('Invalid month in expiry date', 'warning');
                    return false;
                }
                
                if (year < currentYear || (year == currentYear && month < currentMonth)) {
                    e.preventDefault();
                    showAlert('Card has expired', 'warning');
                    return false;
                }
                
                if (cardCvv.length < 3 || cardCvv.length > 4 || !/^\d+$/.test(cardCvv)) {
                    e.preventDefault();
                    showAlert('Please enter a valid CVV (3 or 4 digits)', 'warning');
                    return false;
                }
            }
        });

        // Show alert function
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '350px';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>