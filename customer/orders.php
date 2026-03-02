<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// ========== DELETE ORDER LOGIC ==========
if (isset($_GET['delete_order']) && isset($_GET['order_id'])) {
    $order_id_to_delete = intval($_GET['order_id']);
    
    $check_query = "SELECT status FROM orders WHERE order_id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $order_id_to_delete, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $order_data = $check_result->fetch_assoc();
        if ($order_data['status'] === 'Pending') {
            $delete_items_query = "DELETE FROM order_items WHERE order_id = ?";
            $delete_items_stmt = $conn->prepare($delete_items_query);
            $delete_items_stmt->bind_param("i", $order_id_to_delete);
            $delete_items_stmt->execute();
            
            $delete_order_query = "DELETE FROM orders WHERE order_id = ? AND customer_id = ?";
            $delete_order_stmt = $conn->prepare($delete_order_query);
            $delete_order_stmt->bind_param("ii", $order_id_to_delete, $user_id);
            
            if ($delete_order_stmt->execute()) {
                $_SESSION['success_message'] = "Order #" . str_pad($order_id_to_delete, 6, '0', STR_PAD_LEFT) . " has been successfully cancelled.";
            } else {
                $_SESSION['error_message'] = "Failed to delete order. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Only orders with 'Pending' status can be cancelled.";
        }
    }
    
    header("Location: orders.php");
    exit;
}
// ========== END DELETE LOGIC ==========

// ========== CANCEL ORDER LOGIC (for order details page) ==========
if (isset($_GET['cancel_order']) && isset($_GET['order_id'])) {
    $order_id_to_cancel = intval($_GET['order_id']);
    
    $check_query = "SELECT status FROM orders WHERE order_id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $order_id_to_cancel, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $order_data = $check_result->fetch_assoc();
        if ($order_data['status'] === 'Pending') {
            // Update order status to 'Cancelled' instead of deleting
            $cancel_order_query = "UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND customer_id = ?";
            $cancel_order_stmt = $conn->prepare($cancel_order_query);
            $cancel_order_stmt->bind_param("ii", $order_id_to_cancel, $user_id);
            
            if ($cancel_order_stmt->execute()) {
                $_SESSION['success_message'] = "Order #" . str_pad($order_id_to_cancel, 6, '0', STR_PAD_LEFT) . " has been successfully cancelled.";
            } else {
                $_SESSION['error_message'] = "Failed to cancel order. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Only orders with 'Pending' status can be cancelled.";
        }
    }
    
    header("Location: orders.php?order_id=" . $order_id_to_cancel);
    exit;
}
// ========== END CANCEL ORDER LOGIC ==========

// Get all orders for this customer
$orders_query = "
    SELECT o.*, COUNT(oi.order_item_id) as item_count, 
           SUM(oi.quantity) as total_quantity 
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id 
    WHERE o.customer_id = '$user_id' 
    GROUP BY o.order_id 
    ORDER BY o.order_id DESC
";
$orders_result = $conn->query($orders_query);

// Get specific order details if order_id is provided
$order_id = $_GET['order_id'] ?? 0;
$order_details = null;
$order_items = [];

if ($order_id) {
    $order_details_query = "
        SELECT o.*, COUNT(oi.order_item_id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        WHERE o.order_id = '$order_id' AND o.customer_id = '$user_id'
        GROUP BY o.order_id
    ";
    $order_details_result = $conn->query($order_details_query);
    $order_details = $order_details_result->fetch_assoc();

    if ($order_details) {
        $order_items_query = "
            SELECT oi.*, p.name, p.image, p.category 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = '$order_id'
        ";
        $order_items_result = $conn->query($order_items_query);
        while ($item = $order_items_result->fetch_assoc()) {
            $order_items[] = $item;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - SpiceCeylon</title>
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
        
        /* Orders Container */
        .orders-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Header */
        .orders-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .orders-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .orders-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Order Card */
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        
        .order-card-header {
            background: rgba(184, 92, 56, 0.1);
            padding: 20px;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        .order-card-body {
            padding: 25px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .status-pending { background: rgba(149, 165, 166, 0.2); color: #636e72; }
        .status-confirmed { background: rgba(184, 92, 56, 0.2); color: var(--spice-red); }
        .status-processing { background: rgba(52, 152, 219, 0.2); color: var(--spice-blue); }
        .status-shipped { background: rgba(155, 89, 182, 0.2); color: #8e44ad; }
        .status-delivered { background: rgba(39, 174, 96, 0.2); color: var(--spice-green); }
        .status-cancelled { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        
        /* Order Items */
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            background: rgba(184, 92, 56, 0.05);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            margin-right: 20px;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-name {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .order-item-category {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            margin-right: 10px;
        }
        
        .order-item-quantity {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-item-quantity .badge {
            background: var(--spice-blue);
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
        }
        
        .order-item-price {
            font-weight: 700;
            color: var(--spice-green);
            font-size: 1.2rem;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
        }
        
        .timeline-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            position: relative;
            z-index: 2;
        }
        
        .timeline-dot.active {
            background: var(--spice-red);
            color: white;
            box-shadow: 0 0 0 4px rgba(184, 92, 56, 0.2);
        }
        
        .timeline-dot.completed {
            background: var(--spice-green);
            color: white;
        }
        
        .timeline-dot.pending {
            background: #95a5a6;
            color: white;
        }
        
        .timeline-dot.cancelled {
            background: #e74c3c;
            color: white;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-content h6 {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 2px;
        }
        
        .timeline-content p {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* Summary Card */
        .summary-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            border: 1px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .summary-header {
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .summary-header h4 {
            color: var(--spice-dark);
            font-weight: 600;
            margin: 0;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #e9ecef;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--spice-red);
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        /* Buttons */
        .btn-spice {
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-spice:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-spice-outline {
            color: var(--spice-red);
            border: 2px solid var(--spice-red);
            background: transparent;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-spice-outline:hover {
            background: var(--spice-red);
            color: white;
        }
        
        .btn-danger-outline {
            color: #e74c3c;
            border: 2px solid #e74c3c;
            background: transparent;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-danger-outline:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Empty State */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
            margin: 40px 0;
        }
        
        .empty-orders-icon {
            font-size: 5rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        .empty-orders h3 {
            color: var(--spice-dark);
            margin-bottom: 10px;
        }
        
        .empty-orders p {
            color: #666;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            background: var(--spice-red);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 20px;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
            border-left: 4px solid var(--spice-green);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .order-item {
                flex-direction: column;
                text-align: center;
            }
            
            .order-item-image {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .timeline-step {
                flex-direction: column;
                text-align: center;
            }
            
            .timeline-dot {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
        
        /* Cancelled Order Styling */
        .order-card.cancelled {
            opacity: 0.8;
            background: rgba(231, 76, 60, 0.02);
        }
        
        .cancelled-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(231, 76, 60, 0.05);
            pointer-events: none;
            z-index: 1;
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

    <!-- Orders Container -->
    <div class="orders-container">
        <div class="orders-header">
            <h1><i class="fas fa-history me-2"></i>My Orders</h1>
            <p>Track and manage all your spice purchases in one place</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Back to Orders List Button (for order details view) -->
        <?php if ($order_id && $order_details): ?>
            <div class="mb-4">
                <a href="orders.php" class="btn btn-spice-outline">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>
        <?php endif; ?>

        <?php if ($order_id && $order_details): ?>
            <!-- ========== ORDER DETAILS VIEW ========== -->
            <div class="order-card <?php echo $order_details['status'] === 'Cancelled' ? 'cancelled' : ''; ?>">
                <div class="order-card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Order #<?php echo str_pad($order_details['order_id'], 6, '0', STR_PAD_LEFT); ?></h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('F j, Y g:i A', strtotime($order_details['created_at'])); ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="status-badge status-<?php echo strtolower($order_details['status']); ?>">
                                <?php echo $order_details['status']; ?>
                            </span>
                            <!-- ADDED: Cancel button for order details page -->
                            <?php if ($order_details['status'] === 'Pending'): ?>
                                <button type="button" class="btn btn-danger-outline" data-bs-toggle="modal" data-bs-target="#cancelOrderModal<?php echo $order_details['order_id']; ?>">
                                    <i class="fas fa-times-circle me-2"></i>Cancel Order
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($order_details['status'] === 'Cancelled'): ?>
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This order has been cancelled. No further actions can be taken.
                    </div>
                <?php endif; ?>
                
                <div class="order-card-body">
                    <!-- Timeline -->
                    <div class="mb-5">
                        <h4 class="mb-4"><i class="fas fa-truck me-2"></i>Order Status</h4>
                        <div class="timeline">
                            <?php
                            $statuses = [
                                'Pending' => ['icon' => 'fa-clock', 'class' => 'pending'],
                                'Confirmed' => ['icon' => 'fa-check', 'class' => 'pending'],
                                'Processing' => ['icon' => 'fa-cog', 'class' => 'pending'],
                                'Shipped' => ['icon' => 'fa-shipping-fast', 'class' => 'pending'],
                                'Delivered' => ['icon' => 'fa-box-open', 'class' => 'pending'],
                                'Cancelled' => ['icon' => 'fa-times-circle', 'class' => 'cancelled']
                            ];
                            
                            $currentStatus = $order_details['status'];
                            
                            // If order is cancelled, show cancelled status only
                            if ($currentStatus === 'Cancelled') {
                                ?>
                                <div class="timeline-step">
                                    <div class="timeline-dot cancelled">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Cancelled</h6>
                                        <p class="text-danger"><i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i> Order Cancelled</p>
                                    </div>
                                </div>
                                <?php
                            } else {
                                // Show normal timeline for non-cancelled orders
                                foreach ($statuses as $status => $info): 
                                    if ($status === 'Cancelled') continue; // Skip cancelled in normal flow
                                    $isActive = $currentStatus == $status;
                                    $isCompleted = array_search($currentStatus, array_keys($statuses)) > array_search($status, array_keys($statuses));
                                    $dotClass = 'pending';
                                    if ($isActive) $dotClass = 'active';
                                    elseif ($isCompleted) $dotClass = 'completed';
                            ?>
                            <div class="timeline-step">
                                <div class="timeline-dot <?php echo $dotClass; ?>">
                                    <i class="fas <?php echo $info['icon']; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6><?php echo $status; ?></h6>
                                    <?php if ($isActive): ?>
                                        <p class="text-success"><i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i> Current Status</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            } 
                            ?>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="mb-5">
                        <h4 class="mb-4"><i class="fas fa-shopping-basket me-2"></i>Order Items</h4>
                        <div class="order-items">
                            <?php foreach ($order_items as $item): 
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
                            ?>
                            <div class="order-item">
                                <img src="<?php echo $image_path; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="order-item-image"
                                     onerror="this.src='../assets/images/default-spice.jpg'">
                                
                                <div class="order-item-details">
                                    <h5 class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <div class="mb-2">
                                        <span class="order-item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </div>
                                    <p class="order-item-quantity">
                                        <span class="badge"><?php echo $item['quantity']; ?> kg</span> 
                                        × Rs. <?php echo number_format($item['price'], 2); ?> / kg
                                    </p>
                                </div>
                                
                                <div class="order-item-price">
                                    Rs. <?php echo number_format($item['total_price'], 2); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Shipping Information -->
                            <div class="summary-card">
                                <div class="summary-header">
                                    <h4><i class="fas fa-map-marker-alt me-2"></i>Shipping Information</h4>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h6 class="text-muted mb-2">Recipient</h6>
                                        <p class="mb-0">
                                            <strong><?php echo htmlspecialchars($order_details['shipping_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($order_details['shipping_phone']); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Delivery Address</h6>
                                        <p class="mb-0">
                                            <?php echo htmlspecialchars($order_details['shipping_address']); ?><br>
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars($order_details['shipping_city']); ?> • 
                                                <?php echo htmlspecialchars($order_details['shipping_postal']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Order Totals -->
                            <div class="summary-card">
                                <div class="summary-header">
                                    <h4><i class="fas fa-receipt me-2"></i>Order Summary</h4>
                                </div>
                                
                                <div class="summary-item">
                                    <span>Subtotal</span>
                                    <span>Rs. <?php echo number_format($order_details['total_amount'], 2); ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span>Shipping Fee</span>
                                    <span>Rs. <?php echo number_format($order_details['shipping_fee'], 2); ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span>Payment Method</span>
                                    <span class="badge bg-secondary">
                                        <?php echo ucwords(str_replace('_', ' ', $order_details['payment_method'])); ?>
                                    </span>
                                </div>
                                
                                <div class="summary-total">
                                    <span>Total Amount</span>
                                    <span>Rs. <?php echo number_format($order_details['final_total'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ADDED: Cancel Order Modal for Order Details Page -->
            <div class="modal fade" id="cancelOrderModal<?php echo $order_details['order_id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                Cancel Order #<?php echo str_pad($order_details['order_id'], 6, '0', STR_PAD_LEFT); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Cancelling this order will update its status to "Cancelled". You can still view the order details afterwards.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Keep Order
                            </button>
                            <a href="orders.php?cancel_order=true&order_id=<?php echo $order_details['order_id']; ?>" 
                               class="btn btn-danger">
                                <i class="fas fa-ban me-1"></i>Cancel Order
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ========== ORDERS LIST VIEW ========== -->
            <?php if ($orders_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($order = $orders_result->fetch_assoc()): 
                        $statusClass = 'status-' . strtolower($order['status']);
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="order-card <?php echo $order['status'] === 'Cancelled' ? 'cancelled' : ''; ?>">
                            <div class="order-card-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h3 class="h4 mb-1">Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h3>
                                        <p class="text-muted mb-0 small">
                                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                        </p>
                                    </div>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-card-body">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Items</span>
                                        <span class="fw-bold"><?php echo $order['item_count']; ?> item(s)</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">Total Quantity</span>
                                        <span class="fw-bold"><?php echo $order['total_quantity']; ?> kg</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Payment</span>
                                        <span class="badge bg-secondary">
                                            <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div>
                                        <h4 class="text-success mb-0">Rs. <?php echo number_format($order['final_total'], 2); ?></h4>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <!-- CHANGED: Changed from delete to cancel modal -->
                                            <button type="button" class="btn btn-sm btn-danger-outline" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#cancelOrderModal<?php echo $order['order_id']; ?>"
                                                    title="Cancel Order">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-sm btn-spice">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- CHANGED: Cancel Order Modal (replaced delete modal) -->
                        <div class="modal fade" id="cancelOrderModal<?php echo $order['order_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                            Cancel Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <small>Only orders with "Pending" status can be cancelled.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Keep Order
                                        </button>
                                        <a href="orders.php?cancel_order=true&order_id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-danger">
                                            <i class="fas fa-ban me-1"></i>Cancel Order
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-orders">
                    <div class="empty-orders-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders. Explore our collection of authentic Sri Lankan spices and start your culinary journey!</p>
                    <a href="home.php" class="btn btn-spice">
                        <i class="fas fa-store me-2"></i>Browse Spices
                    </a>
                </div>
            <?php endif; ?>
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
                    <h4 class="mb-3">Order Support</h4>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> orders@spiceceylon.com</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Delivery Info</h4>
                    <p><i class="fas fa-shipping-fast me-2"></i>2-4 Business Days</p>
                    <p><i class="fas fa-map-marked-alt me-2"></i>Islandwide Delivery</p>
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
        
        // Add smooth transitions
        document.querySelectorAll('.order-card').forEach(card => {
            card.style.transition = 'all 0.3s ease';
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>