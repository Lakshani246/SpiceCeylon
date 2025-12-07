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
    
    // Check if order belongs to this customer and is still pending
    $check_query = "SELECT status FROM orders WHERE order_id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $order_id_to_delete, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $order_data = $check_result->fetch_assoc();
        if ($order_data['status'] === 'Pending') {
            // Delete order items first (due to foreign key constraint)
            $delete_items_query = "DELETE FROM order_items WHERE order_id = ?";
            $delete_items_stmt = $conn->prepare($delete_items_query);
            $delete_items_stmt->bind_param("i", $order_id_to_delete);
            $delete_items_stmt->execute();
            
            // Then delete the order
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
    
    // Redirect back to orders page
    header("Location: orders.php");
    exit;
}
// ========== END DELETE LOGIC ==========

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
            SELECT oi.*, p.name, p.image 
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
        
        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .order-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
            background: white;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 1rem;
            border-radius: 50px;
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid var(--light);
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
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 10px 25px;
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #e74c3c);
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
            border-radius: 10px;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(184, 92, 56, 0.05);
        }
        
        .section-header {
            border-bottom: 3px solid var(--light);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        
        .pending-dot { background-color: #6c757d; }
        .confirmed-dot { background-color: var(--primary); }
        .processing-dot { background-color: #17a2b8; }
        .shipped-dot { background-color: var(--warning); }
        .delivered-dot { background-color: var(--success); }
        
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .empty-state {
            padding: 4rem;
            text-align: center;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .table th {
            border: none;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .badge-pill {
            border-radius: 50px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="orders-container">
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
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="home.php" class="text-decoration-none"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="breadcrumb-item active"><i class="fas fa-shopping-bag me-1"></i>My Orders</li>
                </ol>
            </nav>
            
            <!-- Page Header -->
            <div class="section-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="fw-bold mb-2" style="color: var(--primary);">
                            <i class="fas fa-history me-2"></i>My Orders
                        </h1>
                        <p class="text-muted mb-0">View and manage your order history</p>
                    </div>
                    <a href="home.php" class="btn btn-outline-primary">
                        <i class="fas fa-store me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>

            <?php if ($order_id && $order_details): ?>
                <!-- ========== ORDER DETAILS VIEW ========== -->
                <div class="row">
                    <div class="col-12">
                        <!-- Back Button -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h2 class="fw-bold mb-1" style="color: var(--primary);">
                                    Order #<?php echo str_pad($order_details['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                </h2>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('F j, Y g:i A', strtotime($order_details['order_date'])); ?>
                                </p>
                            </div>
                            <div class="action-buttons">
                                <?php if ($order_details['status'] === 'Pending'): ?>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $order_details['order_id']; ?>">
                                        <i class="fas fa-trash me-1"></i>Cancel Order
                                    </button>
                                <?php endif; ?>
                                <a href="orders.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                                </a>
                            </div>
                        </div>

                        <!-- Order Status Timeline -->
                        <div class="card mb-4 order-card">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="fas fa-truck me-2"></i>Order Status</h4>
                            </div>
                            <div class="card-body">
                                <?php
                                $statuses = [
                                    'Pending' => ['icon' => 'fa-clock', 'color' => 'secondary', 'dot' => 'pending-dot'],
                                    'Confirmed' => ['icon' => 'fa-check', 'color' => 'primary', 'dot' => 'confirmed-dot'],
                                    'Processing' => ['icon' => 'fa-cog', 'color' => 'info', 'dot' => 'processing-dot'],
                                    'Shipped' => ['icon' => 'fa-truck', 'color' => 'warning', 'dot' => 'shipped-dot'],
                                    'Delivered' => ['icon' => 'fa-box', 'color' => 'success', 'dot' => 'delivered-dot']
                                ];
                                
                                $current_status = $order_details['status'];
                                ?>
                                <div class="d-flex justify-content-between text-center flex-wrap">
                                    <?php foreach ($statuses as $status => $info): ?>
                                        <div class="flex-fill px-2 mb-3">
                                            <div class="mb-2">
                                                <span class="timeline-dot <?php echo $info['dot']; ?>"></span>
                                                <i class="fas <?php echo $info['icon']; ?> fa-2x text-<?php 
                                                    echo array_search($status, array_keys($statuses)) <= array_search($current_status, array_keys($statuses)) 
                                                    ? $info['color'] : 'secondary'; 
                                                ?>"></i>
                                            </div>
                                            <small class="fw-bold d-block"><?php echo $status; ?></small>
                                            <?php if ($status === $current_status): ?>
                                                <small class="text-success"><i class="fas fa-check me-1"></i>Current</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items Table -->
                        <div class="card mb-4 order-card">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="fas fa-shopping-basket me-2"></i>Order Items</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="10%">Image</th>
                                                <th width="30%">Product</th>
                                                <th width="15%" class="text-center">Quantity</th>
                                                <th width="15%" class="text-end">Unit Price</th>
                                                <th width="15%" class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <img src="../assets/images/<?php echo $item['image']; ?>" 
                                                         alt="<?php echo $item['name']; ?>" 
                                                         class="order-item-image">
                                                </td>
                                                <td>
                                                    <h6 class="mb-1 fw-bold"><?php echo $item['name']; ?></h6>
                                                    <small class="text-muted">Product ID: <?php echo $item['product_id']; ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary rounded-pill"><?php echo $item['quantity']; ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <span class="text-muted">Rs. </span><?php echo number_format($item['price'], 2); ?>
                                                </td>
                                                <td class="text-end fw-bold" style="color: var(--primary);">
                                                    Rs. <?php echo number_format($item['total_price'], 2); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Order Totals -->
                                <div class="p-4 border-top">
                                    <div class="row">
                                        <div class="col-md-8"></div>
                                        <div class="col-md-4">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Subtotal:</span>
                                                <span class="fw-bold">Rs. <?php echo number_format($order_details['total_amount'], 2); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Shipping Fee:</span>
                                                <span class="fw-bold">Rs. <?php echo number_format($order_details['shipping_fee'], 2); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Payment Method:</span>
                                                <span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $order_details['payment_method'])); ?></span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold fs-5 pt-2">
                                                <span>Total Amount:</span>
                                                <span style="color: var(--primary);">Rs. <?php echo number_format($order_details['final_total'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Information -->
                        <div class="card order-card">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Shipping Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Recipient Details</h6>
                                        <p class="mb-3">
                                            <i class="fas fa-user me-2"></i>
                                            <strong><?php echo htmlspecialchars($order_details['shipping_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-2"></i>
                                                <?php echo htmlspecialchars($order_details['shipping_phone']); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">Delivery Address</h6>
                                        <p class="mb-0">
                                            <i class="fas fa-home me-2"></i>
                                            <?php echo htmlspecialchars($order_details['shipping_address']); ?><br>
                                            <span class="ms-4">
                                                <?php echo htmlspecialchars($order_details['shipping_city']); ?>, 
                                                <?php echo htmlspecialchars($order_details['shipping_postal']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ========== ORDERS LIST VIEW ========== -->
                <?php if ($orders_result->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($order = $orders_result->fetch_assoc()): ?>
                            <div class="col-xl-4 col-lg-6 mb-4">
                                <div class="card order-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1 fw-bold" style="color: var(--primary);">
                                                    Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                                </h5>
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                </p>
                                            </div>
                                            <span class="badge bg-<?php 
                                                switch($order['status']) {
                                                    case 'Delivered': echo 'success'; break;
                                                    case 'Shipped': echo 'warning'; break;
                                                    case 'Processing': echo 'info'; break;
                                                    case 'Confirmed': echo 'primary'; break;
                                                    case 'Pending': echo 'secondary'; break;
                                                    default: echo 'light text-dark';
                                                }
                                            ?> status-badge rounded-pill"><?php echo $order['status']; ?></span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-box me-1"></i>
                                                <?php echo $order['item_count']; ?> item(s) • 
                                                <i class="fas fa-hashtag ms-2 me-1"></i>
                                                <?php echo $order['total_quantity']; ?> total units
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1">Payment:</small>
                                            <span class="badge bg-light text-dark">
                                                <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <div>
                                                <h4 class="text-primary mb-0">Rs. <?php echo number_format($order['final_total'], 2); ?></h4>
                                            </div>
                                            <div class="action-buttons">
                                                <?php if ($order['status'] === 'Pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $order['order_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Delete Confirmation Modal -->
                                <div class="modal fade" id="deleteModal<?php echo $order['order_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Cancellation</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to cancel <strong>Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>?</p>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <small>Only orders with "Pending" status can be cancelled. This action cannot be undone.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </button>
                                                <a href="orders.php?delete_order=true&order_id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-danger">
                                                    <i class="fas fa-trash me-1"></i>Yes, Cancel Order
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
                    <div class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                            <h3 class="mb-3 fw-bold" style="color: var(--primary);">No Orders Yet</h3>
                            <p class="text-muted mb-4">You haven't placed any orders. Start exploring our spices collection!</p>
                            <a href="home.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-store me-2"></i>Start Shopping
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

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
    </script>
</body>
</html>
<?php
$conn->close();
?>