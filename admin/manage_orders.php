<?php
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$admin_id = $_SESSION['admin_id'];

// Get admin data
$admin_query = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();

// ========== FIXED: Handle actions - REMOVED 'view' from redirect actions ==========
if (isset($_GET['action']) && isset($_GET['id']) && $_GET['action'] != 'view') {
    $order_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'update_status') {
        if (isset($_POST['status'])) {
            $new_status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            // Update order status
            $update_query = $conn->prepare("UPDATE orders SET status = ?, admin_notes = ? WHERE order_id = ?");
            $update_query->bind_param("ssi", $new_status, $notes, $order_id);
            $update_query->execute();
            
            // Record status change
            $conn->query("INSERT INTO order_status_history (order_id, status, changed_by_admin, notes) VALUES ('$order_id', '$new_status', '$admin_id', '$notes')");
            
            $_SESSION['message'] = "Order #$order_id status updated to " . ucfirst($new_status) . "!";
            $_SESSION['message_type'] = 'success';
        }
    }
    elseif ($action == 'cancel') {
        $conn->query("UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE order_id = '$order_id'");
        $conn->query("INSERT INTO order_status_history (order_id, status, changed_by_admin, notes) VALUES ('$order_id', 'Cancelled', '$admin_id', 'Cancelled by admin')");
        $_SESSION['message'] = "Order #$order_id has been cancelled!";
        $_SESSION['message_type'] = 'warning';
    }
    elseif ($action == 'delete') {
        // Since no is_deleted column, just cancel it
        $conn->query("UPDATE orders SET status = 'Cancelled' WHERE order_id = '$order_id'");
        $_SESSION['message'] = "Order #$order_id has been cancelled!";
        $_SESSION['message_type'] = 'danger';
    }
    
    header("Location: manage_orders.php");
    exit;
}

// ========== ADDED: Handle AJAX request for order data ==========
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_order' && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    header('Content-Type: application/json');
    
    $response = ['success' => false];
    
    // Get order details
    $order_query = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                   FROM orders o 
                   JOIN users u ON o.customer_id = u.user_id 
                   WHERE o.order_id = '$order_id'";
    $order_result = $conn->query($order_query);
    
    if ($order_result && $order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        
        // Get order items
        $items_query = "SELECT oi.*, p.name as product_name, p.category, p.image 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.product_id 
                       WHERE oi.order_id = '$order_id'";
        $items_result = $conn->query($items_query);
        $items = [];
        $subtotal = 0;
        
        while ($item = $items_result->fetch_assoc()) {
            $subtotal += $item['total_price'];
            $items[] = $item;
        }
        
        $response['success'] = true;
        $response['order'] = $order;
        $response['items'] = $items;
        $response['subtotal'] = $subtotal;
    }
    
    echo json_encode($response);
    exit;
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email 
          FROM orders o 
          JOIN users u ON o.customer_id = u.user_id 
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";

if ($status_filter && $status_filter != 'all') {
    $query .= " AND o.status = '$status_filter'";
    $count_query .= " AND o.status = '$status_filter'";
}

if ($date_from) {
    $query .= " AND DATE(o.created_at) >= '$date_from'";
    $count_query .= " AND DATE(o.created_at) >= '$date_from'";
}

if ($date_to) {
    $query .= " AND DATE(o.created_at) <= '$date_to'";
    $count_query .= " AND DATE(o.created_at) <= '$date_to'";
}

if ($search) {
    $query .= " AND (o.order_id LIKE '%$search%' OR u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR o.shipping_address LIKE '%$search%')";
    $count_query .= " AND (o.order_id LIKE '%$search%' OR o.customer_id IN (SELECT user_id FROM users WHERE name LIKE '%$search%' OR email LIKE '%$search%'))";
}

// Order by
$query .= " ORDER BY o.created_at DESC";

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_result = $conn->query($count_query);
$total_orders = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

$query .= " LIMIT $limit OFFSET $offset";

$orders_result = $conn->query($query);

// Get order statistics
$stats = [];
$statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Confirmed', 'Cancelled'];

foreach ($statuses as $status) {
    $stat_result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = '$status'");
    $stats[$status] = $stat_result->fetch_assoc()['count'];
}

// Get all orders count
$all_orders = array_sum($stats);

// Today's stats
$today = date('Y-m-d');
$today_result = $conn->query("SELECT COUNT(*) as count, SUM(total_amount) as revenue FROM orders WHERE DATE(created_at) = '$today'");
$today_orders = $today_result->fetch_assoc();

// This month's stats
$month_start = date('Y-m-01');
$month_result = $conn->query("SELECT COUNT(*) as count, SUM(total_amount) as revenue FROM orders WHERE created_at >= '$month_start'");
$month_orders = $month_result->fetch_assoc();

// Get all order details for modals (pre-fetch all order data to avoid multiple queries)
$order_details_cache = [];
$order_items_cache = [];

if ($orders_result->num_rows > 0) {
    $orders_result->data_seek(0);
    while ($order = $orders_result->fetch_assoc()) {
        $order_id = $order['order_id'];
        
        // Get order details
        $detail_query = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                        FROM orders o 
                        JOIN users u ON o.customer_id = u.user_id 
                        WHERE o.order_id = '$order_id'";
        $detail_result = $conn->query($detail_query);
        $order_details_cache[$order_id] = $detail_result->fetch_assoc();
        
        // Get order items
        $items_query = "SELECT oi.*, p.name as product_name, p.category, p.image 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.product_id 
                       WHERE oi.order_id = '$order_id'";
        $items_result = $conn->query($items_query);
        $order_items_cache[$order_id] = [];
        while ($item = $items_result->fetch_assoc()) {
            $order_items_cache[$order_id][] = $item;
        }
    }
    $orders_result->data_seek(0); // Reset pointer
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - SpiceCeylon Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-purple: #9b59b6;
            --pending: #f39c12;
            --processing: #3498db;
            --shipped: #9b59b6;
            --delivered: #27ae60;
            --completed: #2ecc71;
            --confirmed: #1abc9c;
            --cancelled: #e74c3c;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--spice-dark) 0%, #1a252f 100%);
            min-height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 14px 20px;
            margin: 4px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(184, 92, 56, 0.2);
            border-left-color: var(--spice-red);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.2), rgba(39, 174, 96, 0.1));
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--spice-blue);
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .order-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .order-card.Pending { border-left: 4px solid var(--pending); }
        .order-card.Processing { border-left: 4px solid var(--processing); }
        .order-card.Shipped { border-left: 4px solid var(--shipped); }
        .order-card.Delivered { border-left: 4px solid var(--delivered); }
        .order-card.Completed { border-left: 4px solid var(--completed); }
        .order-card.Confirmed { border-left: 4px solid var(--confirmed); }
        .order-card.Cancelled { border-left: 4px solid var(--cancelled); }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-Pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-Processing { background: rgba(52, 152, 219, 0.15); color: var(--processing); }
        .badge-Shipped { background: rgba(155, 89, 182, 0.15); color: var(--shipped); }
        .badge-Delivered { background: rgba(39, 174, 96, 0.15); color: var(--delivered); }
        .badge-Completed { background: rgba(46, 204, 113, 0.15); color: var(--completed); }
        .badge-Confirmed { background: rgba(26, 188, 156, 0.15); color: var(--confirmed); }
        .badge-Cancelled { background: rgba(231, 76, 60, 0.15); color: var(--cancelled); }
        
        .stat-card {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
            color: white;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.all {
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        
        .stat-card.pending {
            background: linear-gradient(135deg, var(--pending), #e67e22);
        }
        
        .stat-card.processing {
            background: linear-gradient(135deg, var(--processing), #2980b9);
        }
        
        .stat-card.shipped {
            background: linear-gradient(135deg, var(--shipped), #8e44ad);
        }
        
        .stat-card.delivered {
            background: linear-gradient(135deg, var(--delivered), #219653);
        }
        
        .stat-card.completed {
            background: linear-gradient(135deg, var(--completed), #27ae60);
        }
        
        .stat-card.confirmed {
            background: linear-gradient(135deg, var(--confirmed), #16a085);
        }
        
        .stat-card.cancelled {
            background: linear-gradient(135deg, var(--cancelled), #c0392b);
        }
        
        .stat-card.active {
            box-shadow: 0 0 0 3px white, 0 0 0 6px var(--spice-red);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .action-buttons .btn {
            padding: 5px 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .pagination .page-link {
            color: var(--spice-dark);
            border: 1px solid #e9ecef;
            margin: 0 2px;
            border-radius: 8px;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--spice-blue), var(--spice-purple));
            border-color: transparent;
            color: white;
        }
        
        .order-id {
            font-weight: bold;
            color: var(--spice-red);
            font-size: 1.1rem;
        }
        
        .amount {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--spice-dark);
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        /* ========== BEAUTIFUL CENTERED MODAL STYLES ========== */
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: none;
            background: linear-gradient(135deg, var(--spice-red), #d35400);
            color: white;
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
            transition: all 0.3s;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        /* Order Details Card in Modal */
        .order-detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 4px solid var(--spice-blue);
        }
        
        .order-detail-card h6 {
            color: var(--spice-dark);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 12px;
        }
        
        .detail-label {
            width: 120px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .detail-value {
            flex: 1;
            color: var(--spice-dark);
            font-weight: 500;
        }
        
        /* Product Items Table in Modal */
        .product-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .product-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--spice-dark);
            border-radius: 8px 8px 0 0;
        }
        
        .product-table td {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--spice-dark);
        }
        
        .product-category {
            font-size: 0.8rem;
            color: #95a5a6;
        }
        
        /* ========== BEAUTIFUL COLORED ALERT MESSAGES ========== */
        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 350px;
            max-width: 450px;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 6px solid;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
                visibility: hidden;
            }
        }
        
        .alert-custom.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-custom.warning {
            background: linear-gradient(135deg, #fff3cd, #ffe8a1);
            border-left-color: #ffc107;
            color: #856404;
        }
        
        .alert-custom.danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .alert-custom.info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        
        .alert-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .alert-custom.success .alert-icon {
            background: #28a745;
            color: white;
        }
        
        .alert-custom.warning .alert-icon {
            background: #ffc107;
            color: white;
        }
        
        .alert-custom.danger .alert-icon {
            background: #dc3545;
            color: white;
        }
        
        .alert-custom.info .alert-icon {
            background: #17a2b8;
            color: white;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .alert-message {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .alert-close {
            color: inherit;
            opacity: 0.5;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        /* Center modal */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        
        @media (min-width: 576px) {
            .modal-dialog-centered {
                min-height: calc(100% - 1.75rem);
            }
        }
        
        /* ========== ADDED: Loading spinner for modal ========== */
        .modal-spinner {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--spice-red);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* ========== ADDED: Print styles ========== */
        @media print {
            .no-print, .sidebar, .action-buttons, .btn, .modal-header .btn-close, .modal-footer {
                display: none !important;
            }
            
            .modal {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
                padding: 0;
                overflow: visible;
            }
            
            .modal-dialog {
                margin: 0;
                max-width: 100%;
            }
            
            .modal-content {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body {
                background: white;
            }
            
            .order-detail-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-shopping-cart me-2" style="color: var(--spice-red);"></i>
                                Order Management
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Manage customer orders and track shipments
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-red), #d35400); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(184, 92, 56, 0.3);">
                            <i class="fas fa-shopping-cart me-1"></i> Total Orders: <?php echo number_format($all_orders); ?>
                        </div>
                    </div>
                </div>

                <!-- ========== BEAUTIFUL COLORED ALERT MESSAGES ========== -->
                <?php if(isset($_SESSION['message'])): 
                    $message_type = $_SESSION['message_type'] ?? 'success';
                    $title = $message_type == 'success' ? 'Success!' : ($message_type == 'warning' ? 'Warning!' : ($message_type == 'danger' ? 'Error!' : 'Info!'));
                    $icon = $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : ($message_type == 'danger' ? 'fa-times-circle' : 'fa-info-circle'));
                ?>
                <div class="alert-custom <?php echo $message_type; ?>" id="customAlert">
                    <div class="alert-icon">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title"><?php echo $title; ?></div>
                        <div class="alert-message"><?php echo $_SESSION['message']; ?></div>
                    </div>
                    <div class="alert-close" onclick="closeCustomAlert()">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <a href="manage_orders.php" class="text-decoration-none">
                            <div class="stat-card all <?php echo !$status_filter ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($all_orders); ?></div>
                                        <div class="stat-label">All Orders</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-chart-bar me-1"></i> Total orders in system
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Pending" class="text-decoration-none">
                            <div class="stat-card pending <?php echo $status_filter == 'Pending' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Pending']); ?></div>
                                        <div class="stat-label">Pending</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-clock me-1"></i> Awaiting processing
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Processing" class="text-decoration-none">
                            <div class="stat-card processing <?php echo $status_filter == 'Processing' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Processing']); ?></div>
                                        <div class="stat-label">Processing</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-cogs me-1"></i> Being prepared
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-spinner"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Shipped" class="text-decoration-none">
                            <div class="stat-card shipped <?php echo $status_filter == 'Shipped' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Shipped']); ?></div>
                                        <div class="stat-label">Shipped</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-truck me-1"></i> In transit
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Delivered" class="text-decoration-none">
                            <div class="stat-card delivered <?php echo $status_filter == 'Delivered' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Delivered']); ?></div>
                                        <div class="stat-label">Delivered</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-home me-1"></i> Arrived at destination
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Completed" class="text-decoration-none">
                            <div class="stat-card completed <?php echo $status_filter == 'Completed' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Completed']); ?></div>
                                        <div class="stat-label">Completed</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-check-double me-1"></i> Finished successfully
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Confirmed" class="text-decoration-none">
                            <div class="stat-card confirmed <?php echo $status_filter == 'Confirmed' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Confirmed']); ?></div>
                                        <div class="stat-label">Confirmed</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-user-check me-1"></i> Customer confirmed
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-handshake"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_orders.php?status=Cancelled" class="text-decoration-none">
                            <div class="stat-card cancelled <?php echo $status_filter == 'Cancelled' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($stats['Cancelled']); ?></div>
                                        <div class="stat-label">Cancelled</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-times-circle me-1"></i> Order cancelled
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <div class="row">
                        <div class="col-md-9">
                            <h5 class="mb-3">
                                <i class="fas fa-filter me-2" style="color: var(--spice-purple);"></i>
                                Filter Orders
                            </h5>
                            <form method="GET" action="manage_orders.php" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Order ID, customer name, email..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo !$status_filter ? 'selected' : ''; ?>>All Status</option>
                                        <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">From Date</label>
                                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">To Date</label>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="fas fa-filter me-1"></i> Filter
                                        </button>
                                        <a href="manage_orders.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light rounded p-4 h-100">
                                <h6 class="mb-3">
                                    <i class="fas fa-chart-line me-2" style="color: var(--spice-blue);"></i>
                                    Quick Overview
                                </h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Today's Orders:</span>
                                        <strong><?php echo number_format($today_orders['count'] ?? 0); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">This Month:</span>
                                        <strong><?php echo number_format($month_orders['count'] ?? 0); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Revenue:</span>
                                        <strong class="text-success">LKR <?php echo number_format(($month_orders['revenue'] ?? 0), 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--spice-red);"></i>
                            Orders List (<?php echo $total_orders; ?> orders)
                        </h5>
                        <?php if($status_filter && $status_filter != 'all'): ?>
                            <span class="badge bg-secondary">
                                <i class="fas fa-tag me-1"></i> 
                                Status: <?php echo htmlspecialchars($status_filter); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($orders_result->num_rows > 0): ?>
                        <?php while($order = $orders_result->fetch_assoc()): 
                            $item_count = $conn->query("SELECT COUNT(*) as count FROM order_items WHERE order_id = '{$order['order_id']}'")->fetch_assoc()['count'];
                        ?>
                        <div class="order-card <?php echo $order['status']; ?>">
                            <div class="row align-items-center">
                                <!-- Order Info -->
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <div style="margin-right: 15px;">
                                            <div class="order-id mb-1">#<?php echo $order['order_id']; ?></div>
                                            <div class="customer-info">
                                                <div class="small mb-1">
                                                    <i class="fas fa-user me-1 text-muted"></i> 
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="fas fa-calendar me-1"></i> 
                                                    <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Details -->
                                <div class="col-md-3">
                                    <div class="small">
                                        <div class="mb-1">
                                            <i class="fas fa-box me-1 text-muted"></i> 
                                            <?php echo $item_count; ?> item(s)
                                        </div>
                                        <div class="mb-1">
                                            <i class="fas fa-map-marker-alt me-1 text-muted"></i> 
                                            <?php echo substr(htmlspecialchars($order['shipping_address']), 0, 50); ?>
                                            <?php if(strlen($order['shipping_address']) > 50): ?>...<?php endif; ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-phone me-1 text-muted"></i> 
                                            <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-2">
                                    <?php 
                                    $status_class = 'badge-' . $order['status'];
                                    $status_icon = 'fa-circle';
                                    
                                    switch($order['status']) {
                                        case 'Pending': $status_icon = 'fa-clock'; break;
                                        case 'Processing': $status_icon = 'fa-cogs'; break;
                                        case 'Shipped': $status_icon = 'fa-shipping-fast'; break;
                                        case 'Delivered': $status_icon = 'fa-check-circle'; break;
                                        case 'Completed': $status_icon = 'fa-check-double'; break;
                                        case 'Confirmed': $status_icon = 'fa-user-check'; break;
                                        case 'Cancelled': $status_icon = 'fa-times-circle'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i> 
                                        <?php echo $order['status']; ?>
                                    </span>
                                </div>
                                
                                <!-- Amount & Payment -->
                                <div class="col-md-2">
                                    <div class="amount">LKR <?php echo number_format($order['total_amount'], 2); ?></div>
                                    <div class="small text-muted">
                                        <i class="fas fa-credit-card me-1"></i> 
                                        <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'cash_on_delivery')); ?>
                                        <?php if(isset($order['payment_status']) && $order['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success badge-sm ms-1">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning badge-sm ms-1">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-2">
                                    <div class="action-buttons d-flex flex-wrap justify-content-end">
                                        <!-- ========== FIXED: View Button with AJAX (no page refresh) ========== -->
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1 mb-1 view-order-btn" 
                                                data-order-id="<?php echo $order['order_id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <!-- Status Update Dropdown -->
                                        <div class="dropdown me-1 mb-1">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-edit"></i> Status
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php foreach ($statuses as $status): 
                                                    if ($status != $order['status']):
                                                ?>
                                                <li>
                                                    <form method="POST" action="manage_orders.php?action=update_status&id=<?php echo $order['order_id']; ?>" style="display: inline;">
                                                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                        <button type="submit" class="dropdown-item" onclick="return confirm('Change order status to <?php echo $status; ?>?')">
                                                            <i class="fas fa-arrow-right me-1"></i> 
                                                            Mark as <?php echo $status; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; endforeach; ?>
                                            </ul>
                                        </div>
                                        
                                        <!-- Cancel button for pending/processing orders -->
                                        <?php if($order['status'] == 'Pending' || $order['status'] == 'Processing'): ?>
                                            <a href="manage_orders.php?action=cancel&id=<?php echo $order['order_id']; ?>" 
                                               class="btn btn-outline-danger btn-sm mb-1"
                                               onclick="return confirm('Cancel order #<?php echo $order['order_id']; ?>?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h5 class="text-muted mb-3">No orders found</h5>
                            <p class="text-muted mb-4">
                                <?php 
                                if(!empty($search)) {
                                    echo "No orders found matching '" . htmlspecialchars($search) . "'";
                                } elseif($status_filter) {
                                    echo "No orders with status '" . htmlspecialchars($status_filter) . "' found.";
                                } else {
                                    echo "No orders in the system yet.";
                                }
                                ?>
                            </p>
                            <a href="manage_orders.php" class="btn btn-primary">
                                <i class="fas fa-redo me-1"></i> View All Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_orders.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                <i class="fas fa-chevron-left me-1"></i> Previous
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_orders.php?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_orders.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========== ADDED: Single AJAX Modal for Order Details ========== -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailModalLabel">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Order Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <div class="modal-spinner">
                        <div class="spinner"></div>
                        <p>Loading order details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss custom alerts after 5 seconds
        function closeCustomAlert() {
            document.getElementById('customAlert')?.remove();
        }
        
        setTimeout(function() {
            const alert = document.getElementById('customAlert');
            if(alert) {
                alert.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        
        // Highlight active stat card
        const currentStatus = "<?php echo $status_filter; ?>";
        if(currentStatus) {
            $(`.stat-card.${currentStatus.toLowerCase()}`).addClass('active');
        } else {
            $('.stat-card.all').addClass('active');
        }
        
        // Auto-set date ranges
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateFromInput = document.querySelector('input[name="date_from"]');
            const dateToInput = document.querySelector('input[name="date_to"]');
            
            if(dateFromInput && !dateFromInput.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateFromInput.value = thirtyDaysAgo.toISOString().split('T')[0];
            }
            
            if(dateToInput && !dateToInput.value) {
                dateToInput.value = today;
            }
        });
        
        // Auto-focus on search field if there's a search
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.has('search') && urlParams.get('search')) {
                document.querySelector('input[name="search"]').focus();
            }
        });
        
        // ========== ADDED: AJAX View Button Handler ==========
        $(document).ready(function() {
            $('.view-order-btn').on('click', function(e) {
                e.preventDefault(); // Prevent any default behavior
                const orderId = $(this).data('order-id');
                
                // Show modal with loading spinner
                $('#orderDetailModal').modal('show');
                
                // Load order details via AJAX
                $.ajax({
                    url: 'manage_orders.php?ajax=get_order&id=' + orderId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayOrderDetails(response);
                        } else {
                            $('#modalContent').html('<div class="alert alert-danger">Failed to load order details.</div>');
                        }
                    },
                    error: function() {
                        $('#modalContent').html('<div class="alert alert-danger">Error loading order details.</div>');
                    }
                });
            });
            
            function displayOrderDetails(data) {
                const order = data.order;
                const items = data.items;
                const subtotal = data.subtotal;
                
                let statusIcon = 'fa-circle';
                switch(order.status) {
                    case 'Pending': statusIcon = 'fa-clock'; break;
                    case 'Processing': statusIcon = 'fa-cogs'; break;
                    case 'Shipped': statusIcon = 'fa-shipping-fast'; break;
                    case 'Delivered': statusIcon = 'fa-check-circle'; break;
                    case 'Completed': statusIcon = 'fa-check-double'; break;
                    case 'Confirmed': statusIcon = 'fa-user-check'; break;
                    case 'Cancelled': statusIcon = 'fa-times-circle'; break;
                }
                
                let itemsHtml = '';
                items.forEach(item => {
                    const imagePath = '../assets/images/' + (item.image || 'default-spice.jpg');
                    itemsHtml += `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="${imagePath}" 
                                         alt="${item.product_name}" 
                                         class="product-image me-3"
                                         onerror="this.src='../assets/images/default-spice.jpg'">
                                    <div>
                                        <div class="product-name">${item.product_name}</div>
                                        <div class="product-category">${item.category}</div>
                                    </div>
                                </div>
                            </td>
                            <td>${item.category}</td>
                            <td>${item.quantity} kg</td>
                            <td>LKR ${parseFloat(item.price).toFixed(2)}</td>
                            <td><strong>LKR ${parseFloat(item.total_price).toFixed(2)}</strong></td>
                        </tr>
                    `;
                });
                
                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="order-detail-card">
                                <h6><i class="fas fa-info-circle me-2" style="color: #3498db;"></i>Order Information</h6>
                                <div class="detail-item">
                                    <span class="detail-label">Order ID:</span>
                                    <span class="detail-value">#${order.order_id}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Order Date:</span>
                                    <span class="detail-value">${new Date(order.created_at).toLocaleString()}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Order Status:</span>
                                    <span class="detail-value">
                                        <span class="status-badge badge-${order.status}">
                                            <i class="fas ${statusIcon} me-1"></i>
                                            ${order.status}
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Method:</span>
                                    <span class="detail-value">${order.payment_method.replace(/_/g, ' ')}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Status:</span>
                                    <span class="detail-value">
                                        ${order.payment_status == 'paid' ? 
                                            '<span class="badge bg-success">Paid</span>' : 
                                            '<span class="badge bg-warning">Pending</span>'}
                                    </span>
                                </div>
                                ${order.notes ? `
                                <div class="detail-item">
                                    <span class="detail-label">Customer Notes:</span>
                                    <span class="detail-value">${order.notes}</span>
                                </div>
                                ` : ''}
                                ${order.admin_notes ? `
                                <div class="detail-item">
                                    <span class="detail-label">Admin Notes:</span>
                                    <span class="detail-value">${order.admin_notes}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="order-detail-card">
                                <h6><i class="fas fa-user me-2" style="color: #27ae60;"></i>Customer Information</h6>
                                <div class="detail-item">
                                    <span class="detail-label">Name:</span>
                                    <span class="detail-value">${order.customer_name}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value">${order.customer_email}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value">${order.shipping_phone}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value">${order.shipping_address}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">City:</span>
                                    <span class="detail-value">${order.shipping_city}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Postal Code:</span>
                                    <span class="detail-value">${order.shipping_postal}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-detail-card mt-3">
                        <h6><i class="fas fa-box me-2" style="color: #9b59b6;"></i>Order Items</h6>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong>LKR ${parseFloat(subtotal).toFixed(2)}</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end">Shipping Fee:</td>
                                    <td>LKR ${parseFloat(order.shipping_fee).toFixed(2)}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td><strong class="text-success">LKR ${parseFloat(order.final_total || order.total_amount).toFixed(2)}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
                
                $('#modalContent').html(html);
            }
        });
    </script>
</body>
</html>