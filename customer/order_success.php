<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;

// Get order details
$order_query = "
    SELECT o.*, COUNT(oi.order_item_id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id 
    WHERE o.order_id = '$order_id' AND o.customer_id = '$user_id'
    GROUP BY o.order_id
";
$order_result = $conn->query($order_query);
$order = $order_result->fetch_assoc();

// Get order items
$items_query = "
    SELECT oi.*, p.name, p.image, p.category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = '$order_id'
";
$items_result = $conn->query($items_query);

if (!$order) {
    header("Location: orders.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - SpiceCeylon</title>
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
        
        /* Success Container */
        .success-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Success Header */
        .success-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .success-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .success-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Success Card */
        .success-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .success-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: rgba(184, 92, 56, 0.05);
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding: 20px;
        }
        
        .card-header h3 {
            color: var(--spice-dark);
            font-weight: 600;
            margin: 0;
            font-size: 1.3rem;
        }
        
        .card-header h3 i {
            color: var(--spice-red);
            margin-right: 10px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Success Icon */
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--spice-green), #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.2);
        }
        
        /* Order Items */
        .order-item {
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        /* Summary Item */
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
        .btn-success {
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
        
        .btn-success:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: var(--spice-dark);
            border: 2px solid var(--spice-dark);
            padding: 13px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-outline:hover {
            background: var(--spice-dark);
            color: white;
        }
        
        /* Order ID Badge */
        .order-id-badge {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            display: inline-block;
            margin: 10px 0;
        }
        
        /* Status Badge */
        .status-badge {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Delivery Info */
        .delivery-info {
            background: rgba(52, 152, 219, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 3px solid var(--spice-blue);
        }
        
        /* Footer */
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .success-card {
                padding: 15px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-group .btn {
                width: 100%;
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
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="orders.php">Orders</a></li>
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

    <!-- Success Container -->
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check fa-2x text-white"></i>
            </div>
            <h1>Order Successful!</h1>
            <p>Your order has been confirmed and is being processed</p>
            <div class="order-id-badge">
                <i class="fas fa-receipt me-2"></i>
                Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Order Details -->
            <div class="col-lg-8">
                <!-- Order Summary -->
                <div class="success-card">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt"></i>Order Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted">Order ID</label>
                                    <div class="fw-bold">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted">Order Date</label>
                                    <div class="fw-bold">
                                        <?php echo date('F j, Y', strtotime($order['order_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted">Status</label>
                                    <div class="status-badge">
                                        <i class="fas fa-clock me-1"></i> <?php echo $order['status']; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted">Payment Method</label>
                                    <div class="fw-bold">
                                        <?php 
                                        if ($order['payment_method'] == 'cash_on_delivery') {
                                            echo '<i class="fas fa-money-bill-wave me-1"></i> Cash on Delivery';
                                        } else {
                                            echo '<i class="fas fa-credit-card me-1"></i> Credit/Debit Card';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <h6 class="fw-bold mb-3" style="color: var(--spice-dark);">
                            <i class="fas fa-shopping-basket me-2"></i>Order Items
                            <span class="badge bg-primary ms-2"><?php echo $order['item_count']; ?> items</span>
                        </h6>
                        
                        <?php if ($items_result->num_rows > 0): ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php while($item = $items_result->fetch_assoc()): ?>
                                    <div class="order-item">
                                        <div class="d-flex align-items-center">
                                            <?php
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
                                            <img src="<?php echo $image_path; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="product-image me-3"
                                                 onerror="this.src='../assets/images/default-spice.jpg'">
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="text-muted small">
                                                    <span class="me-3">
                                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category']); ?>
                                                    </span>
                                                    <span>
                                                        <i class="fas fa-times me-1"></i><?php echo $item['quantity']; ?> kg
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="fw-bold">
                                                Rs. <?php echo number_format($item['total_price'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="success-card">
                    <div class="card-header">
                        <h3><i class="fas fa-map-marker-alt"></i>Shipping Address</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Recipient</h6>
                                    <p class="mb-2">
                                        <i class="fas fa-user me-2"></i>
                                        <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong>
                                    </p>
                                    <p class="mb-0 text-muted">
                                        <i class="fas fa-phone me-2"></i>
                                        <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Delivery Address</h6>
                                    <p class="mb-2">
                                        <i class="fas fa-home me-2"></i>
                                        <?php echo htmlspecialchars($order['shipping_address']); ?>
                                    </p>
                                    <p class="mb-0 text-muted">
                                        <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                                        <?php echo htmlspecialchars($order['shipping_postal']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="delivery-info">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-truck me-3" style="color: var(--spice-blue);"></i>
                                <div>
                                    <div class="fw-bold">Estimated Delivery</div>
                                    <div class="text-muted">
                                        3-5 business days from <?php echo date('F j', strtotime($order['order_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Actions -->
            <div class="col-lg-4">
                <!-- Order Total -->
                <div class="success-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calculator"></i>Order Total</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate totals from items if not in order table
                        $subtotal = 0;
                        $items_result->data_seek(0); // Reset pointer
                        while($item = $items_result->fetch_assoc()) {
                            $subtotal += $item['total_price'];
                        }
                        
                        $shipping_fee = 200.00;
                        $tax_amount = $subtotal * 0.08;
                        $final_total = $subtotal + $shipping_fee + $tax_amount;
                        ?>
                        
                        <div class="summary-item">
                            <span>Subtotal</span>
                            <span>Rs. <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Shipping Fee</span>
                            <span>Rs. <?php echo number_format($shipping_fee, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Tax (8%)</span>
                            <span>Rs. <?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                        
                        <div class="summary-total">
                            <span>Total Amount</span>
                            <span>Rs. <?php echo number_format($final_total, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="success-card">
                    <div class="card-header">
                        <h3><i class="fas fa-forward"></i>What's Next?</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex align-items-start mb-3">
                                <i class="fas fa-envelope me-3" style="color: var(--spice-blue); margin-top: 3px;"></i>
                                <div>
                                    <div class="fw-bold">Order Confirmation</div>
                                    <small class="text-muted">Email confirmation sent to your inbox</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start mb-3">
                                <i class="fas fa-box me-3" style="color: var(--spice-gold); margin-top: 3px;"></i>
                                <div>
                                    <div class="fw-bold">Order Processing</div>
                                    <small class="text-muted">Your spices are being prepared (1-2 days)</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start">
                                <i class="fas fa-shipping-fast me-3" style="color: var(--spice-green); margin-top: 3px;"></i>
                                <div>
                                    <div class="fw-bold">Delivery</div>
                                    <small class="text-muted">Estimated delivery in 3-5 business days</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-light border" style="background: rgba(184, 92, 56, 0.05);">
                            <i class="fas fa-info-circle me-2" style="color: var(--spice-red);"></i>
                            <small>You can track your order from your <a href="orders.php" class="fw-bold" style="color: var(--spice-red);">Orders page</a>.</small>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-3">
                    <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-eye me-2"></i>View Full Details
                    </a>
                    
                    <a href="orders.php" class="btn btn-outline">
                        <i class="fas fa-clipboard-list me-2"></i>All Orders
                    </a>
                    
                    <a href="home.php" class="btn btn-outline">
                        <i class="fas fa-store me-2"></i>Continue Shopping
                    </a>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i>Secure Transaction
                    </small>
                </div>
            </div>
        </div>
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
        // Simple success animation
        document.addEventListener('DOMContentLoaded', function() {
            const successIcon = document.querySelector('.success-icon');
            
            // Bounce animation
            let bounceCount = 0;
            const bounce = () => {
                if (bounceCount < 3) {
                    successIcon.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        successIcon.style.transform = 'translateY(0)';
                        setTimeout(() => {
                            bounceCount++;
                            bounce();
                        }, 200);
                    }, 200);
                }
            };
            
            bounce();
            
            // Play subtle success sound
            try {
                const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-winning-chimes-2015.mp3');
                audio.volume = 0.1;
                audio.play().catch(() => {});
            } catch (e) {
                // Audio not supported or user hasn't interacted
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>