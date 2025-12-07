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
    <title>Order Confirmed - SpiceCeylon</title>
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
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .order-success-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .success-animation {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-15px);}
            60% {transform: translateY(-7px);}
        }
        
        .order-card {
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
            background: white;
            border-left: 4px solid var(--success);
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            border-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.2);
        }
        
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
            border-radius: 10px;
            padding: 12px 30px;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .badge {
            border-radius: 50px;
            padding: 0.4rem 1rem;
        }
        
        .step-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            opacity: 0;
            animation: confetti-fall 5s linear infinite;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="order-success-container">
            <!-- Success Message -->
            <div class="text-center mb-5">
                <div class="success-animation mb-4">
                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--success), #20c997); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);">
                        <i class="fas fa-check-circle text-white" style="font-size: 4rem;"></i>
                    </div>
                </div>
                <h1 class="fw-bold mb-3" style="color: var(--success);">
                    Order Confirmed! 🎉
                </h1>
                <p class="lead mb-4" style="max-width: 600px; margin: 0 auto;">
                    Thank you for choosing SpiceCeylon! Your order has been received and is being prepared with care.
                </p>
                <div class="alert alert-success d-inline-block" style="background: rgba(40, 167, 69, 0.1); border: 2px solid var(--success);">
                    <i class="fas fa-info-circle me-2"></i>
                    Order ID: <strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="card order-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3" style="color: var(--primary);">
                                <i class="fas fa-shopping-bag me-2"></i>Order Details
                            </h5>
                            <div class="mb-3">
                                <p class="mb-1"><strong>Order ID:</strong> 
                                    <span class="text-primary fw-bold">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </p>
                                <p class="mb-1"><strong>Order Date:</strong> 
                                    <i class="fas fa-calendar me-1 text-muted"></i>
                                    <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                                </p>
                                <p class="mb-1"><strong>Total Items:</strong> 
                                    <span class="badge bg-primary"><?php echo $order['item_count']; ?> items</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3" style="color: var(--primary);">
                                <i class="fas fa-credit-card me-2"></i>Payment & Shipping
                            </h5>
                            <div class="mb-3">
                                <p class="mb-1"><strong>Payment Method:</strong> 
                                    <span class="badge bg-light text-dark">
                                        <?php 
                                        if ($order['payment_method'] == 'cash_on_delivery') {
                                            echo 'Cash on Delivery';
                                        } else {
                                            echo 'Credit/Debit Card';
                                        }
                                        ?>
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Status:</strong> 
                                    <span class="badge bg-warning"><?php echo $order['status']; ?></span>
                                </p>
                                <p class="mb-1"><strong>Total Amount:</strong> 
                                    <span class="fw-bold fs-5" style="color: var(--primary);">
                                        Rs. <?php echo number_format($order['final_total'], 2); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="card order-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Shipping Address</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Recipient Details</h6>
                            <p class="mb-3">
                                <i class="fas fa-user me-2"></i>
                                <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong><br>
                                <small class="text-muted ms-4">
                                    <i class="fas fa-phone me-1"></i>
                                    <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                </small>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Delivery Address</h6>
                            <p class="mb-0">
                                <i class="fas fa-home me-2"></i>
                                <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                <small class="text-muted ms-4">
                                    <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                                    <?php echo htmlspecialchars($order['shipping_postal']); ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="card order-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-forward me-2"></i>What's Next?</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <div class="p-3 h-100">
                                <div class="step-icon" style="border: 3px solid #0d6efd;">
                                    <i class="fas fa-envelope fa-2x text-primary"></i>
                                </div>
                                <h6 class="fw-bold mt-2">Order Confirmation</h6>
                                <small class="text-muted">You'll receive an email confirmation with all order details</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="p-3 h-100">
                                <div class="step-icon" style="border: 3px solid #17a2b8;">
                                    <i class="fas fa-truck fa-2x text-info"></i>
                                </div>
                                <h6 class="fw-bold mt-2">Order Processing</h6>
                                <small class="text-muted">We're preparing your spices for shipment (1-2 days)</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="p-3 h-100">
                                <div class="step-icon" style="border: 3px solid var(--success);">
                                    <i class="fas fa-home fa-2x text-success"></i>
                                </div>
                                <h6 class="fw-bold mt-2">Delivery</h6>
                                <small class="text-muted">Your spices will arrive in 3-5 business days</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-clock me-2"></i>
                        <small>You can track your order status anytime from your <a href="orders.php" class="alert-link">Orders page</a>.</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-4 pt-3">
                <div class="btn-group" role="group">
                    <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary me-3">
                        <i class="fas fa-eye me-2"></i>View Full Order Details
                    </a>
                    <a href="orders.php" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-clipboard-list me-2"></i>All My Orders
                    </a>
                    <a href="home.php" class="btn btn-outline-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create confetti effect
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#b85c38', '#d17a50', '#28a745', '#ffc107', '#0d6efd'];
            
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                    confetti.style.width = (Math.random() * 10 + 5) + 'px';
                    confetti.style.height = confetti.style.width;
                    
                    document.body.appendChild(confetti);
                    
                    // Remove confetti after animation
                    setTimeout(() => {
                        confetti.remove();
                    }, 5000);
                }, i * 100);
            }
            
            // Play success sound (optional)
            const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-winning-chimes-2015.mp3');
            audio.volume = 0.3;
            audio.play().catch(e => console.log("Audio play failed (user hasn't interacted)"));
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>