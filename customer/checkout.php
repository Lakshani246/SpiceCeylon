<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Get cart items
$cart_query = "
    SELECT c.cart_id, c.quantity, c.price, p.product_id, p.name, p.image, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    WHERE c.customer_id = '$user_id'
";
$cart_result = $conn->query($cart_query);

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
    // Get form data
    $shipping_name = $conn->real_escape_string($_POST['shipping_name']);
    $shipping_phone = $conn->real_escape_string($_POST['shipping_phone']);
    $shipping_address = $conn->real_escape_string($_POST['shipping_address']);
    $shipping_city = $conn->real_escape_string($_POST['shipping_city']);
    $shipping_postal = $conn->real_escape_string($_POST['shipping_postal']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $notes = $conn->real_escape_string($_POST['notes']);

    // Get card details if credit card is selected
    $card_name = $card_number = $card_expiry = $card_cvv = '';
    $card_saved = 0;
    
    if ($payment_method == 'credit_card') {
        $card_name = $conn->real_escape_string($_POST['card_name']);
        $card_number = $conn->real_escape_string($_POST['card_number']);
        $card_expiry = $conn->real_escape_string($_POST['card_expiry']);
        $card_cvv = $conn->real_escape_string($_POST['card_cvv']);
        $card_saved = isset($_POST['save_card']) ? 1 : 0;
        
        // Validate card details
        if (empty($card_name) || empty($card_number) || empty($card_expiry) || empty($card_cvv)) {
            $_SESSION['error'] = "Please fill in all card details for credit card payment";
            header("Location: checkout.php");
            exit;
        }
        
        // Basic card validation
        if (!preg_match('/^[0-9]{16}$/', str_replace(' ', '', $card_number))) {
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
    $final_total = $total_amount + $shipping_fee;

    // Check if cart is empty
    if (empty($cart_items)) {
        $_SESSION['error'] = "Your cart is empty!";
        header("Location: cart.php");
        exit;
    }

    // Create order
    $order_query = "
        INSERT INTO orders (
            customer_id, total_amount, shipping_fee, final_total,
            shipping_name, shipping_phone, shipping_address, shipping_city, 
            shipping_postal, payment_method, notes, status
        ) VALUES (
            '$user_id', '$total_amount', '$shipping_fee', '$final_total',
            '$shipping_name', '$shipping_phone', '$shipping_address', '$shipping_city',
            '$shipping_postal', '$payment_method', '$notes', 'Pending'
        )
    ";
    
    $order_result = $conn->query($order_query);
    
    if ($order_result) {
        $order_id = $conn->insert_id;
        
        // If credit card payment, save card details
        if ($payment_method == 'credit_card' && $card_saved) {
            $card_query = "
                INSERT INTO payment_cards (customer_id, order_id, card_name, 
                         card_last_four, card_expiry, card_type, is_default)
                VALUES (
                    '$user_id', '$order_id', '$card_name',
                    '" . substr($card_number, -4) . "',
                    '$card_expiry', 
                    '" . (substr($card_number, 0, 1) == '4' ? 'Visa' : 'MasterCard') . "',
                    1
                )
            ";
            $conn->query($card_query);
        }
        
        // Add order items
        foreach ($cart_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $order_item_query = "
                INSERT INTO order_items (order_id, product_id, quantity, price, total_price)
                VALUES ('$order_id', '{$item['product_id']}', '{$item['quantity']}', 
                       '{$item['price']}', '$item_total')
            ";
            $conn->query($order_item_query);
        }

        // Clear cart
        $conn->query("DELETE FROM cart WHERE customer_id = '$user_id'");
        
        // Redirect to success page
        header("Location: order_success.php?order_id=$order_id");
        exit;
    } else {
        $_SESSION['error'] = "Order failed: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b85c38;
            --secondary: #d17a50;
            --light: #f8f0e9;
            --dark: #2c1810;
            --success: #28a745;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .summary-card {
            position: sticky;
            top: 20px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
        }
        
        .cart-item-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--light);
        }
        
        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-method:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.1);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background-color: rgba(184, 92, 56, 0.05);
        }
        
        .card-details-container {
            animation: fadeIn 0.5s ease;
            margin-top: 20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #eaeaea;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(184, 92, 56, 0.25);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #20c997);
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .card-icon {
            width: 30px;
            height: 30px;
            object-fit: contain;
            opacity: 0.3;
            transition: opacity 0.3s ease;
        }
        
        .card-icon.active {
            opacity: 1;
        }
        
        .cvv-eye {
            cursor: pointer;
            background: white;
        }
        
        .cvv-eye:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="checkout-container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="home.php" class="text-decoration-none"><i class="fas fa-home me-2"></i>Home</a></li>
                    <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none"><i class="fas fa-shopping-cart me-2"></i>Cart</a></li>
                    <li class="breadcrumb-item active"><i class="fas fa-check me-2"></i>Checkout</li>
                </ol>
            </nav>

            <h1 class="fw-bold mb-4" style="color: var(--primary); border-bottom: 3px solid var(--primary); padding-bottom: 10px; display: inline-block;">
                <i class="fas fa-shopping-bag me-2"></i>Checkout
            </h1>

            <?php if (empty($cart_items)): ?>
                <div class="alert alert-warning text-center p-5 rounded-3">
                    <div class="alert-icon mb-3">
                        <i class="fas fa-shopping-cart fa-3x" style="color: #ffc107;"></i>
                    </div>
                    <h3 class="fw-bold mb-3">Your cart is empty</h3>
                    <p class="lead mb-4">Add some delicious spices to your cart before checking out.</p>
                    <a href="home.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-store me-2"></i>Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" id="checkout-form">
                    <div class="row g-4">
                        <!-- Left Column: Forms -->
                        <div class="col-lg-8">
                            <!-- Shipping Information -->
                            <div class="form-section">
                                <h3 class="fw-bold mb-4" style="color: var(--primary); border-left: 4px solid var(--primary); padding-left: 15px;">
                                    <i class="fas fa-truck me-2"></i>Shipping Information
                                </h3>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="shipping_name" class="form-label fw-bold">Full Name *</label>
                                        <input type="text" class="form-control form-control-lg" 
                                               id="shipping_name" name="shipping_name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_phone" class="form-label fw-bold">Phone Number *</label>
                                        <input type="tel" class="form-control form-control-lg" 
                                               id="shipping_phone" name="shipping_phone" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="shipping_address" class="form-label fw-bold">Address *</label>
                                        <textarea class="form-control" id="shipping_address" 
                                                  name="shipping_address" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_city" class="form-label fw-bold">City *</label>
                                        <input type="text" class="form-control" 
                                               id="shipping_city" name="shipping_city" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shipping_postal" class="form-label fw-bold">Postal Code *</label>
                                        <input type="text" class="form-control" 
                                               id="shipping_postal" name="shipping_postal" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="form-section">
                                <h3 class="fw-bold mb-4" style="color: var(--primary); border-left: 4px solid var(--primary); padding-left: 15px;">
                                    <i class="fas fa-credit-card me-2"></i>Payment Method
                                </h3>
                                
                                <div class="payment-method" onclick="selectPayment('cash')">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" 
                                               name="payment_method" id="cash" 
                                               value="cash_on_delivery" required>
                                        <label class="form-check-label fw-bold h5 mb-0" for="cash">
                                            <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                        </label>
                                    </div>
                                    <p class="text-muted mt-2 ps-4">
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
                                    <p class="text-muted mt-2 ps-4">
                                        Pay securely with your credit or debit card
                                    </p>
                                </div>

                                <!-- Credit Card Details Form -->
                                <div id="cardDetails" class="card-details-container" style="display: none;">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Card Details</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <label for="card_name" class="form-label fw-bold">Name on Card *</label>
                                                    <input type="text" class="form-control" id="card_name" name="card_name" 
                                                           placeholder="John Doe" maxlength="50">
                                                </div>
                                                
                                                <div class="col-12 mb-3">
                                                    <label for="card_number" class="form-label fw-bold">Card Number *</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="card_number" name="card_number" 
                                                               placeholder="1234 5678 9012 3456" maxlength="19">
                                                        <span class="input-group-text bg-white">
                                                            <i class="fas fa-credit-card" id="cardTypeIcon"></i>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex mt-3">
                                                        <img src="https://img.icons8.com/color/30/000000/visa.png" 
                                                             alt="Visa" class="card-icon me-3" id="visaIcon">
                                                        <img src="https://img.icons8.com/color/30/000000/mastercard.png" 
                                                             alt="MasterCard" class="card-icon" id="mastercardIcon">
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="card_expiry" class="form-label fw-bold">Expiry Date (MM/YY) *</label>
                                                    <input type="text" class="form-control" id="card_expiry" name="card_expiry" 
                                                           placeholder="MM/YY" maxlength="5">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="card_cvv" class="form-label fw-bold">CVV *</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="card_cvv" name="card_cvv" 
                                                               placeholder="123" maxlength="4">
                                                        <span class="input-group-text cvv-eye" id="cvvEye">
                                                            <i class="fas fa-eye"></i>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">3 or 4 digits on back of card</small>
                                                </div>
                                                
                                                <div class="col-12 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="save_card" name="save_card" checked>
                                                        <label class="form-check-label" for="save_card">
                                                            Save this card for future purchases
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-lock me-2"></i>
                                                        <small>Your payment information is secured with SSL encryption. We don't store your full card details.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Notes -->
                            <div class="form-section">
                                <h3 class="fw-bold mb-4" style="color: var(--primary); border-left: 4px solid var(--primary); padding-left: 15px;">
                                    <i class="fas fa-sticky-note me-2"></i>Order Notes
                                </h3>
                                <div class="mb-3">
                                    <label for="notes" class="form-label fw-bold">Additional Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" 
                                              rows="3" placeholder="Any special instructions for your order..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Order Summary -->
                        <div class="col-lg-4">
                            <div class="card summary-card">
                                <div class="card-header bg-primary text-white">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-receipt fa-2x me-3"></i>
                                        <div>
                                            <h4 class="mb-0">Order Summary</h4>
                                            <small class="opacity-75">Review your items</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <!-- Cart Items -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-shopping-basket me-2"></i>Your Items
                                        </h6>
                                        <?php foreach ($cart_items as $item): ?>
                                            <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #f8f9fa;">
                                                <img src="../assets/images/<?php echo htmlspecialchars($item['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="cart-item-image me-3">
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                    <small class="text-muted">
                                                        Rs. <?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                                                    </small>
                                                </div>
                                                <div class="fw-bold">
                                                    Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Price Breakdown -->
                                    <div class="bg-light p-4 rounded-3 mb-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span class="fw-bold">Rs. <?php echo number_format($total_amount, 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Shipping Fee:</span>
                                            <span class="fw-bold">Rs. 200.00</span>
                                        </div>
                                        <hr class="my-3">
                                        <div class="d-flex justify-content-between fw-bold fs-4">
                                            <span>Total:</span>
                                            <span style="color: var(--primary);">
                                                Rs. <?php echo number_format($total_amount + 200, 2); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Place Order Button -->
                                    <button type="submit" name="place_order" 
                                            class="btn btn-success btn-lg w-100 py-3">
                                        <i class="fas fa-check-circle me-2"></i>Place Order
                                    </button>
                                    
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-lock me-1"></i>Secure checkout
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

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

        // Card number formatting and validation
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formatted = value.replace(/(\d{4})/g, '$1 ').trim();
            e.target.value = formatted.substring(0, 19);
            
            // Detect card type
            const visaIcon = document.getElementById('visaIcon');
            const mastercardIcon = document.getElementById('mastercardIcon');
            const cardTypeIcon = document.getElementById('cardTypeIcon');
            
            if (value.startsWith('4')) {
                visaIcon.classList.add('active');
                mastercardIcon.classList.remove('active');
                cardTypeIcon.className = 'fab fa-cc-visa text-primary';
            } else if (value.startsWith('5')) {
                visaIcon.classList.remove('active');
                mastercardIcon.classList.add('active');
                cardTypeIcon.className = 'fab fa-cc-mastercard text-primary';
            } else {
                visaIcon.classList.remove('active');
                mastercardIcon.classList.remove('active');
                cardTypeIcon.className = 'fas fa-credit-card';
            }
        });

        // Expiry date formatting
        document.getElementById('card_expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            } else {
                e.target.value = value;
            }
        });

        // Show/hide CVV
        let cvvVisible = false;
        document.getElementById('cvvEye').addEventListener('click', function() {
            const cvvInput = document.getElementById('card_cvv');
            const eyeIcon = this.querySelector('i');
            
            if (cvvVisible) {
                cvvInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
                cvvVisible = false;
            } else {
                cvvInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
                cvvVisible = true;
            }
        });

        // Form validation
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                showAlert('Please select a payment method', 'warning');
                return false;
            }
            
            // Validate card details if credit card is selected
            if (paymentMethod.value === 'credit_card') {
                const cardName = document.getElementById('card_name').value.trim();
                const cardNumber = document.getElementById('card_number').value.replace(/\s+/g, '');
                const cardExpiry = document.getElementById('card_expiry').value;
                const cardCvv = document.getElementById('card_cvv').value;
                
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
                
                if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
                    e.preventDefault();
                    showAlert('Please enter expiry date in MM/YY format', 'warning');
                    return false;
                }
                
                const [month, year] = cardExpiry.split('/');
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
            // Remove existing alerts
            document.querySelectorAll('.custom-alert').forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `custom-alert alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
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