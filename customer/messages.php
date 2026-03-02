<?php
require_once '../config/db.php';
require_once '../config/auth_check.php';

// Redirect if not customer
if ($_SESSION['role'] !== 'customer') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$customer_id = $_SESSION['user_id'];

// ========== FIXED: Get success/error messages from session first ==========
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get customer data
$customer_query = "SELECT * FROM users WHERE user_id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();

// Get cart count
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];

// Get wishlist count
$wishlist_query = "SELECT COUNT(*) as wishlist_count FROM wishlist WHERE customer_id = ?";
$wishlist_stmt = $conn->prepare($wishlist_query);
$wishlist_stmt->bind_param("i", $customer_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();
$wishlist_count = $wishlist_result->fetch_assoc()['wishlist_count'];

// Get unread message count
$unread_query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND receiver_role = 'customer' AND is_read = FALSE";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $customer_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];

// Get notification count for admin actions on requests (today's updates)
$notification_query = "
    SELECT COUNT(*) as count 
    FROM request_history 
    WHERE request_id IN (SELECT request_id FROM product_requests WHERE customer_id = ?)
    AND DATE(changed_at) = CURDATE()
";
$notification_stmt = $conn->prepare($notification_query);
$notification_stmt->bind_param("i", $customer_id);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();
$new_notifications_count = $notification_result->fetch_assoc()['count'];

// ========== FIXED: Handle message sending with better validation ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'] ?? '';
    $receiver_role = $_POST['receiver_role'] ?? 'admin';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $related_order_id = !empty($_POST['related_order_id']) ? $_POST['related_order_id'] : null;
    $related_product_id = !empty($_POST['related_product_id']) ? $_POST['related_product_id'] : null;
    
    if (!empty($message) && !empty($subject) && !empty($receiver_id)) {
        // Validate receiver exists
        $valid_receiver = false;
        if ($receiver_role === 'admin') {
            $check_query = "SELECT admin_id FROM admins WHERE admin_id = ? AND status = 'active'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $receiver_id);
            $check_stmt->execute();
            $valid_receiver = $check_stmt->get_result()->num_rows > 0;
        } elseif ($receiver_role === 'farmer') {
            $check_query = "SELECT user_id FROM users WHERE user_id = ? AND role = 'farmer' AND status = 'active'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $receiver_id);
            $check_stmt->execute();
            $valid_receiver = $check_stmt->get_result()->num_rows > 0;
        }
        
        if ($valid_receiver) {
            $insert_query = "INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message, related_order_id, related_product_id) 
                             VALUES (?, 'customer', ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iisssii", $customer_id, $receiver_id, $receiver_role, $subject, $message, $related_order_id, $related_product_id);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Message sent successfully to " . ($receiver_role === 'farmer' ? 'farmer' : 'admin') . "!";
            } else {
                $_SESSION['error_message'] = "Failed to send message. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid recipient selected. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Please fill in all required fields.";
    }
    
    // Redirect to same page with tab parameter
    $redirect_url = 'messages.php';
    if (isset($_GET['tab'])) {
        $redirect_url .= '?tab=' . $_GET['tab'];
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Mark message as read if viewing
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $message_id = $_GET['read'];
    $mark_read_query = "UPDATE messages SET is_read = TRUE WHERE id = ? AND receiver_id = ? AND receiver_role = 'customer'";
    $mark_read_stmt = $conn->prepare($mark_read_query);
    $mark_read_stmt->bind_param("ii", $message_id, $customer_id);
    $mark_read_stmt->execute();
    
    // Stay on same page
    $redirect_url = 'messages.php?tab=messages';
    header('Location: ' . $redirect_url);
    exit();
}

// ========== FIXED: Handle delete message with session message ==========
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = $_GET['delete'];
    
    // Verify message belongs to user
    $verify_query = "SELECT id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("iii", $message_id, $customer_id, $customer_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        $delete_query = "DELETE FROM messages WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $message_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Message deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete message. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Message not found or you don't have permission to delete it.";
    }
    
    $redirect_url = 'messages.php';
    if (isset($_GET['tab'])) {
        $redirect_url .= '?tab=' . $_GET['tab'];
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Get all messages for this customer (both sent and received)
$messages_query = "SELECT m.*, 
                   s.name as sender_name,
                   r.name as receiver_name,
                   p.name as product_name,
                   o.order_id as order_number
                   FROM messages m
                   LEFT JOIN users s ON m.sender_id = s.user_id AND m.sender_role = s.role
                   LEFT JOIN users r ON m.receiver_id = r.user_id AND m.receiver_role = r.role
                   LEFT JOIN products p ON m.related_product_id = p.product_id
                   LEFT JOIN orders o ON m.related_order_id = o.order_id
                   WHERE (m.sender_id = ? AND m.sender_role = 'customer') 
                   OR (m.receiver_id = ? AND m.receiver_role = 'customer')
                   ORDER BY m.created_at DESC";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->bind_param("ii", $customer_id, $customer_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

// Get available admins for dropdown
$admins_query = "SELECT admin_id, username as name FROM admins WHERE status = 'active'";
$admins_result = $conn->query($admins_query);

// Get recent orders for quick reference
$recent_orders_query = "SELECT order_id, status FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5";
$recent_orders_stmt = $conn->prepare($recent_orders_query);
$recent_orders_stmt->bind_param("i", $customer_id);
$recent_orders_stmt->execute();
$recent_orders_result = $recent_orders_stmt->get_result();

// Get customer's product requests with farmer details
$customer_requests_query = "
    SELECT pr.*, 
           f.name as farmer_name,
           f.email as farmer_email,
           f.user_id as farmer_id
    FROM product_requests pr
    LEFT JOIN users f ON pr.assigned_farmer_id = f.user_id
    WHERE pr.customer_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 10
";
$customer_requests_stmt = $conn->prepare($customer_requests_query);
$customer_requests_stmt->bind_param("i", $customer_id);
$customer_requests_stmt->execute();
$customer_requests_result = $customer_requests_stmt->get_result();

// Get admin notifications about requests
$request_notifications_query = "
    SELECT rh.*, 
           pr.product_name,
           pr.status as current_status,
           pr.description,
           pr.quantity_requested,
           pr.customer_id,
           a.username as admin_name,
           f.name as farmer_name,
           f.user_id as farmer_id
    FROM request_history rh
    JOIN product_requests pr ON rh.request_id = pr.request_id
    LEFT JOIN admins a ON rh.changed_by_admin = a.admin_id
    LEFT JOIN users f ON pr.assigned_farmer_id = f.user_id
    WHERE pr.customer_id = ?
    ORDER BY rh.changed_at DESC
    LIMIT 10
";
$request_notifications_stmt = $conn->prepare($request_notifications_query);
$request_notifications_stmt->bind_param("i", $customer_id);
$request_notifications_stmt->execute();
$request_notifications_result = $request_notifications_stmt->get_result();

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - SpiceCeylon</title>
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
        }
        
        .dashboard-container {
            min-height: calc(100vh - 120px);
        }
        
        /* Sidebar Styling */
        .sidebar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 20px 0;
            height: fit-content;
        }
        
        .sidebar .nav-link {
            color: var(--spice-dark);
            padding: 12px 25px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: var(--spice-red);
            color: white !important;
            box-shadow: 0 4px 12px rgba(184, 92, 56, 0.3);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Card Styling */
        .dashboard-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .dashboard-card .card-header {
            background: white;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding: 20px;
            font-weight: 600;
            color: var(--spice-dark);
            font-size: 1.1rem;
        }
        
        .dashboard-card .card-body {
            padding: 25px;
        }
        
        /* Message Styling */
        .message-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .message-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .message-item.unread {
            background: rgba(52, 152, 219, 0.05);
            border-left: 4px solid var(--spice-blue);
        }
        
        .message-item.read {
            background: white;
            border-left: 4px solid #ddd;
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
        }
        
        .message-receiver {
            font-size: 0.9rem;
            color: #666;
        }
        
        .message-subject {
            font-weight: 600;
            color: var(--spice-red);
            margin: 10px 0;
            font-size: 1.1rem;
        }
        
        .message-content {
            color: #444;
            line-height: 1.6;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .message-meta {
            font-size: 0.85rem;
            color: #888;
            margin-top: 10px;
        }
        
        .message-actions {
            margin-top: 15px;
        }
        
        .message-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-incoming {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
        }
        
        .badge-outgoing {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
        }
        
        /* Notification Styling */
        .notification-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .notification-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-item.new {
            background: rgba(52, 152, 219, 0.05);
            border-left: 4px solid var(--spice-blue);
        }
        
        .notification-item.updated {
            background: rgba(39, 174, 96, 0.05);
            border-left: 4px solid var(--spice-green);
        }
        
        .notification-item.farmer-assigned {
            background: rgba(155, 89, 182, 0.05);
            border-left: 4px solid #9b59b6;
        }
        
        .notification-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-notification {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
        }
        
        .badge-farmer {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-status-pending { background: rgba(243, 156, 18, 0.1); color: var(--spice-gold); }
        .badge-status-reviewed { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        .badge-status-approved { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .badge-status-rejected { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
        .badge-status-completed { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }
        
        /* Request Card */
        .request-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .request-card.assigned {
            border-left: 4px solid var(--spice-green);
        }
        
        .request-card.pending {
            border-left: 4px solid var(--spice-gold);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
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
        
        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--spice-red);
            background: rgba(184, 92, 56, 0.05);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--spice-red);
            border-bottom: 3px solid var(--spice-red);
            background: transparent;
        }
        
        .notification-indicator {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            background: var(--spice-blue);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            
            .message-item {
                padding: 15px;
            }
            
            .notification-item {
                padding: 15px;
            }
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--spice-dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Alert messages */
        .alert {
            border-radius: 10px;
            border-left-width: 4px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            border-left-color: var(--spice-green);
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            border-left-color: #e74c3c;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            border-left-color: var(--spice-blue);
            background-color: #d1ecf1;
            color: #0c5460;
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
            <div class="collapse navbar-collapse" id="navbarNav">
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

    <!-- Dashboard Content -->
    <div class="container-fluid mt-4 dashboard-container">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <div class="text-center mb-4">
                        <div class="profile-img-container">
                            <img class="profile-img" 
                                src="<?php 
                                    if (!empty($customer['profile_image']) && file_exists('../assets/images/profile_images/' . $customer['profile_image'])) {
                                        echo '../assets/images/profile_images/' . $customer['profile_image'];
                                    } else {
                                        echo '../assets/images/default-profile.jpg';
                                    }
                                ?>" 
                                alt="Profile"
                                onerror="this.src='../assets/images/default-profile.jpg'">
                        </div>
                        <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($customer['name']); ?></h5>
                        <p class="text-muted small">Customer</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">
                                <i class="fas fa-store"></i>
                                Shop Spices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                My Cart
                                <?php if($cart_count > 0): ?>
                                    <span class="badge bg-primary ms-1"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-clipboard-list"></i>
                                My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="wishlist.php">
                                <i class="fas fa-heart"></i>
                                My Wishlist
                                <?php if($wishlist_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $wishlist_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="messages.php">
                                <i class="fas fa-envelope"></i>
                                My Messages
                                <?php if($unread_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notifications.php">
                                <i class="fas fa-bell"></i>
                                Notifications
                                <?php if($new_notifications_count > 0): ?>
                                    <span class="badge bg-warning ms-1"><?php echo $new_notifications_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="request.php">
                                <i class="fas fa-plus-circle"></i>
                                Request Product
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_requests.php">
                                <i class="fas fa-inbox"></i>
                                My Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user-cog"></i>
                                Profile Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Header -->
                <div class="welcome-banner" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(155, 89, 182, 0.1));">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h1 class="display-6"><i class="fas fa-envelope me-2"></i> My Messages & Updates</h1>
                            <p class="lead">Communicate with admins and farmers, track your requests</p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <button class="btn btn-spice" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i>
                                New Message
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ========== FIXED: Success/Error Messages Display ========== -->
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mt-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'messages' ? 'active' : ''; ?>" href="?tab=messages">
                            <i class="fas fa-envelope me-2"></i>Messages
                            <?php if($unread_count > 0): ?>
                                <span class="notification-indicator"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>" href="?tab=notifications">
                            <i class="fas fa-bell me-2"></i>Request Updates
                            <?php if($new_notifications_count > 0): ?>
                                <span class="notification-indicator"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'requests' ? 'active' : ''; ?>" href="?tab=requests">
                            <i class="fas fa-inbox me-2"></i>My Requests
                        </a>
                    </li>
                </ul>

                <?php if($active_tab == 'messages'): ?>
                <!-- Messages Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.2));">
                            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.2); color: var(--spice-blue);">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <div class="stat-value"><?php echo $messages_result->num_rows; ?></div>
                            <div class="stat-label">Total Messages</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.2));">
                            <div class="stat-icon" style="background: rgba(39, 174, 96, 0.2); color: var(--spice-green);">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <div class="stat-value"><?php echo $messages_result->num_rows - $unread_count; ?></div>
                            <div class="stat-label">Read Messages</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(184, 92, 56, 0.1), rgba(184, 92, 56, 0.2));">
                            <div class="stat-icon" style="background: rgba(184, 92, 56, 0.2); color: var(--spice-red);">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-value"><?php echo $unread_count; ?></div>
                            <div class="stat-label">Unread Messages</div>
                        </div>
                    </div>
                </div>

                <!-- Messages List -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-comments me-2"></i>All Messages
                        <div class="float-end">
                            <div class="btn-group" role="group">
                                <a href="?tab=messages&filter=all" class="btn btn-sm <?php echo !isset($_GET['filter']) || $_GET['filter'] == 'all' ? 'btn-spice' : 'btn-outline-secondary'; ?>">All</a>
                                <a href="?tab=messages&filter=unread" class="btn btn-sm <?php echo isset($_GET['filter']) && $_GET['filter'] == 'unread' ? 'btn-spice' : 'btn-outline-secondary'; ?>">Unread</a>
                                <a href="?tab=messages&filter=sent" class="btn btn-sm <?php echo isset($_GET['filter']) && $_GET['filter'] == 'sent' ? 'btn-spice' : 'btn-outline-secondary'; ?>">Sent</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if($messages_result->num_rows > 0): ?>
                            <div class="messages-list">
                                <?php 
                                $messages_result->data_seek(0);
                                while($message = $messages_result->fetch_assoc()): 
                                    $is_sent = $message['sender_id'] == $customer_id;
                                    $is_unread = !$message['is_read'] && !$is_sent;
                                ?>
                                <div class="message-item <?php echo $is_unread ? 'unread' : 'read'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="message-badge <?php echo $is_sent ? 'badge-outgoing' : 'badge-incoming'; ?>">
                                                <i class="fas fa-<?php echo $is_sent ? 'paper-plane' : 'inbox'; ?> me-1"></i>
                                                <?php echo $is_sent ? 'Sent' : 'Received'; ?>
                                            </span>
                                            <?php if($is_unread): ?>
                                                <span class="message-badge" style="background: rgba(52, 152, 219, 0.1); color: var(--spice-blue);">
                                                    <i class="fas fa-circle me-1"></i>New
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-meta">
                                            <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="message-sender">
                                        <i class="fas fa-user me-1"></i>
                                        <strong><?php echo $is_sent ? 'To: ' . htmlspecialchars($message['receiver_name'] ?? 'Admin') : 'From: ' . htmlspecialchars($message['sender_name'] ?? 'Admin'); ?></strong>
                                    </div>
                                    
                                    <div class="message-subject">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($message['subject']); ?>
                                    </div>
                                    
                                    <?php if($message['related_order_id']): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-info">
                                                <i class="fas fa-receipt me-1"></i>Order #<?php echo str_pad($message['related_order_id'], 6, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($message['related_product_id']): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-warning">
                                                <i class="fas fa-pepper-hot me-1"></i>Product: <?php echo htmlspecialchars($message['product_name'] ?? ''); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                    
                                    <div class="message-actions">
                                        <?php if(!$is_sent && $is_unread): ?>
                                            <a href="?tab=messages&read=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check me-1"></i>Mark as Read
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?tab=messages&reply=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#replyModal" 
                                           data-subject="Re: <?php echo htmlspecialchars($message['subject']); ?>"
                                           data-receiver-id="<?php echo $is_sent ? $message['receiver_id'] : $message['sender_id']; ?>"
                                           data-receiver-role="<?php echo $is_sent ? $message['receiver_role'] : $message['sender_role']; ?>">
                                            <i class="fas fa-reply me-1"></i>Reply
                                        </a>
                                        
                                        <a href="?tab=messages&delete=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this message?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <h4 class="text-muted mb-3">No messages yet</h4>
                                <p class="text-muted mb-4">Start a conversation with our admin team or check back later for updates.</p>
                                <button class="btn btn-spice" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                    <i class="fas fa-plus me-1"></i>Send Your First Message
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif($active_tab == 'notifications'): ?>
                <!-- Request Notifications -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-bell me-2"></i>Request Updates & Notifications
                    </div>
                    <div class="card-body">
                        <?php if($request_notifications_result->num_rows > 0): ?>
                            <div class="notifications-list">
                                <?php 
                                $request_notifications_result->data_seek(0);
                                while($notification = $request_notifications_result->fetch_assoc()): 
                                    $is_new = date('Y-m-d', strtotime($notification['changed_at'])) == date('Y-m-d');
                                    $notification_class = $is_new ? 'new' : '';
                                    
                                    // Determine additional class based on action
                                    if(strpos($notification['notes'] ?? '', 'Assigned to farmer') !== false) {
                                        $notification_class .= ' farmer-assigned';
                                    } elseif($notification['new_status'] == 'Updated') {
                                        $notification_class .= ' updated';
                                    }
                                ?>
                                <div class="notification-item <?php echo $notification_class; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="notification-badge badge-notification">
                                                <i class="fas fa-bell me-1"></i>
                                                Request Update
                                            </span>
                                            <?php if($notification['farmer_name']): ?>
                                                <span class="notification-badge badge-farmer">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    Farmer Assigned
                                                </span>
                                            <?php endif; ?>
                                            <?php if($is_new): ?>
                                                <span class="notification-badge" style="background: rgba(52, 152, 219, 0.1); color: var(--spice-blue);">
                                                    <i class="fas fa-star me-1"></i>New
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($notification['changed_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6 class="mb-2">
                                            <i class="fas fa-box me-2" style="color: var(--spice-red);"></i>
                                            <?php echo htmlspecialchars($notification['product_name']); ?>
                                        </h6>
                                        
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <span class="badge-status badge-status-<?php echo strtolower($notification['current_status']); ?>">
                                                <i class="fas fa-<?php 
                                                    if($notification['current_status'] == 'Pending') echo 'clock';
                                                    elseif($notification['current_status'] == 'Reviewed') echo 'eye';
                                                    elseif($notification['current_status'] == 'Approved') echo 'check';
                                                    elseif($notification['current_status'] == 'Rejected') echo 'times';
                                                    elseif($notification['current_status'] == 'Completed') echo 'check-double';
                                                ?> me-1"></i>
                                                <?php echo $notification['current_status']; ?>
                                            </span>
                                            
                                            <?php if($notification['quantity_requested']): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-hashtag me-1"></i>
                                                Qty: <?php echo $notification['quantity_requested']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="alert alert-light">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <i class="fas fa-info-circle" style="color: var(--spice-blue);"></i>
                                                </div>
                                                <div>
                                                    <strong>Status Changed:</strong> 
                                                    <span class="text-<?php 
                                                        if($notification['new_status'] == 'Approved') echo 'success';
                                                        elseif($notification['new_status'] == 'Rejected') echo 'danger';
                                                        else echo 'info';
                                                    ?>">
                                                        <?php echo $notification['old_status']; ?> → <?php echo $notification['new_status']; ?>
                                                    </span>
                                                    <?php if($notification['admin_name']): ?>
                                                        <br><small>By: <?php echo htmlspecialchars($notification['admin_name']); ?></small>
                                                    <?php endif; ?>
                                                    
                                                    <?php if(isset($notification['notes'])): ?>
                                                        <br><small>Note: <?php echo htmlspecialchars($notification['notes']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if($notification['farmer_name']): ?>
                                        <div class="alert alert-success">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <i class="fas fa-user-tie" style="color: var(--spice-green);"></i>
                                                </div>
                                                <div>
                                                    <strong>Farmer Assigned:</strong> <?php echo htmlspecialchars($notification['farmer_name']); ?>
                                                    <br><small>Your request has been assigned to a farmer for fulfillment.</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if($notification['description']): ?>
                                        <div class="mt-2">
                                            <p class="small text-muted mb-1">Your Request Description:</p>
                                            <p class="small"><?php echo htmlspecialchars($notification['description']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <a href="my_requests.php?view=<?php echo $notification['request_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Request Details
                                        </a>
                                        <?php if($notification['farmer_name']): ?>
                                        <button class="btn btn-sm btn-outline-success message-farmer-btn" 
                                                data-farmer-id="<?php echo $notification['farmer_id']; ?>"
                                                data-farmer-name="<?php echo htmlspecialchars($notification['farmer_name']); ?>"
                                                data-product-name="<?php echo htmlspecialchars($notification['product_name']); ?>"
                                                data-request-id="<?php echo $notification['request_id']; ?>">
                                            <i class="fas fa-comment me-1"></i>Message Farmer
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-bell-slash"></i>
                                </div>
                                <h4 class="text-muted mb-3">No request updates yet</h4>
                                <p class="text-muted mb-4">Your product requests haven't been updated yet. Check back later or make a new request.</p>
                                <a href="request.php" class="btn btn-spice">
                                    <i class="fas fa-plus-circle me-1"></i>Make a Request
                                </a>
                                <a href="my_requests.php" class="btn btn-spice-outline ms-2">
                                    <i class="fas fa-inbox me-1"></i>View My Requests
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif($active_tab == 'requests'): ?>
                <!-- My Requests -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-inbox me-2"></i>My Product Requests
                        <div class="float-end">
                            <a href="request.php" class="btn btn-sm btn-spice">
                                <i class="fas fa-plus me-1"></i>New Request
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if($customer_requests_result->num_rows > 0): ?>
                            <div class="requests-list">
                                <?php 
                                $customer_requests_result->data_seek(0);
                                while($request = $customer_requests_result->fetch_assoc()): 
                                    $status_class = strtolower($request['status']);
                                ?>
                                <div class="request-card <?php echo $status_class; ?> <?php echo $request['assigned_farmer_id'] ? 'assigned' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($request['product_name']); ?></h6>
                                            <div class="small text-muted mb-2">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge-status badge-status-<?php echo $status_class; ?>">
                                                <i class="fas fa-<?php 
                                                    if($request['status'] == 'Pending') echo 'clock';
                                                    elseif($request['status'] == 'Reviewed') echo 'eye';
                                                    elseif($request['status'] == 'Approved') echo 'check';
                                                    elseif($request['status'] == 'Rejected') echo 'times';
                                                    elseif($request['status'] == 'Completed') echo 'check-double';
                                                ?> me-1"></i>
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="small">
                                                <p class="mb-1"><strong>Quantity:</strong> <?php echo $request['quantity_requested']; ?></p>
                                                <?php if($request['description']): ?>
                                                <p class="mb-1"><strong>Description:</strong> <?php echo htmlspecialchars(substr($request['description'], 0, 100)); ?><?php if(strlen($request['description']) > 100): ?>...<?php endif; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small">
                                                <?php if($request['farmer_name']): ?>
                                                <p class="mb-1"><strong>Assigned Farmer:</strong> <?php echo htmlspecialchars($request['farmer_name']); ?></p>
                                                <?php if($request['farmer_email']): ?>
                                                <p class="mb-1"><strong>Farmer Email:</strong> <?php echo htmlspecialchars($request['farmer_email']); ?></p>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <?php if($request['assigned_farmer_id']): ?>
                                        <button class="btn btn-sm btn-outline-success message-assigned-farmer-btn"
                                                data-farmer-id="<?php echo $request['farmer_id']; ?>"
                                                data-farmer-name="<?php echo htmlspecialchars($request['farmer_name'] ?? 'Farmer'); ?>"
                                                data-product-name="<?php echo htmlspecialchars($request['product_name']); ?>"
                                                data-request-id="<?php echo $request['request_id']; ?>">
                                            <i class="fas fa-comment me-1"></i>Message Farmer
                                        </button>
                                        <?php endif; ?>
                                        <a href="my_requests.php?view=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="my_requests.php" class="btn btn-spice">
                                    <i class="fas fa-inbox me-1"></i>View All Requests
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h4 class="text-muted mb-3">No product requests yet</h4>
                                <p class="text-muted mb-4">You haven't made any product requests yet. Start by making your first request!</p>
                                <a href="request.php" class="btn btn-spice">
                                    <i class="fas fa-plus-circle me-1"></i>Make Your First Request
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: var(--spice-dark); color: white; padding: 50px 0 20px; margin-top: 80px;">
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
                        <a href="home.php" class="text-light text-decoration-none d-block mb-2">Home</a>
                        <a href="dashboard.php" class="text-light text-decoration-none d-block mb-2">Dashboard</a>
                        <a href="cart.php" class="text-light text-decoration-none d-block mb-2">Shopping Cart</a>
                        <a href="orders.php" class="text-light text-decoration-none d-block mb-2">My Orders</a>
                        <a href="about.php" class="text-light text-decoration-none d-block mb-2">About Us</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Need Help?</h4>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Colombo, Sri Lanka</p>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> support@spiceceylon.com</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Sat: 8:00 AM - 6:00 PM</p>
                </div>
            </div>
            <hr class="mt-4 mb-3">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SpiceCeylon. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--spice-red); color: white;">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>New Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">To</label>
                            <select class="form-select" name="receiver_role" id="receiverRole" required>
                                <option value="">-- Select Recipient --</option>
                                <option value="admin">Admin Team</option>
                                <?php while($admin = $admins_result->fetch_assoc()): ?>
                                    <option value="admin" data-admin-id="<?php echo $admin['admin_id']; ?>"><?php echo htmlspecialchars($admin['name']); ?> (Admin)</option>
                                <?php endwhile; ?>
                            </select>
                            <input type="hidden" name="receiver_id" id="receiverId" value="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" required placeholder="What is this message about?">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Related Order (Optional)</label>
                                <select class="form-select" name="related_order_id">
                                    <option value="">-- Select Order --</option>
                                    <?php 
                                    $recent_orders_result->data_seek(0);
                                    while($order = $recent_orders_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $order['order_id']; ?>">Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?> (<?php echo $order['status']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Related Product (Optional)</label>
                                <input type="number" class="form-control" name="related_product_id" placeholder="Product ID">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="6" required placeholder="Type your message here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-spice">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--spice-blue); color: white;">
                    <h5 class="modal-title"><i class="fas fa-reply me-2"></i>Reply to Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="receiver_id" id="reply_receiver_id">
                        <input type="hidden" name="receiver_role" id="reply_receiver_role">
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="reply_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="6" required placeholder="Type your reply here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-spice">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========== FIXED: Message Farmer Modal with proper form action ========== -->
    <div class="modal fade" id="messageFarmerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--spice-green); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Message Farmer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="messages.php<?php echo isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : ''; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="receiver_role" value="farmer">
                        <input type="hidden" name="receiver_id" id="message_farmer_id">
                        <input type="hidden" name="subject" id="message_subject">
                        
                        <div class="mb-3">
                            <label class="form-label">Farmer</label>
                            <input type="text" class="form-control" id="message_farmer_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Regarding Product</label>
                            <input type="text" class="form-control" id="message_product_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="6" required placeholder="Type your message to the farmer..."></textarea>
                            <div class="form-text">You can discuss product details, delivery options, pricing, or ask questions.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-spice">Send Message to Farmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle reply modal
        const replyModal = document.getElementById('replyModal');
        if (replyModal) {
            replyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const subject = button.getAttribute('data-subject');
                const receiverId = button.getAttribute('data-receiver-id');
                const receiverRole = button.getAttribute('data-receiver-role');
                
                document.getElementById('reply_subject').value = subject;
                document.getElementById('reply_receiver_id').value = receiverId;
                document.getElementById('reply_receiver_role').value = receiverRole;
            });
        }

        // Handle recipient selection
        document.querySelector('select[name="receiver_role"]')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const adminId = selectedOption.getAttribute('data-admin-id');
            
            if (adminId) {
                document.getElementById('receiverId').value = adminId;
            } else {
                document.getElementById('receiverId').value = '1'; // Default to admin
            }
        });

        // ========== FIXED: Handle message farmer button clicks ==========
        document.addEventListener('click', function(e) {
            if (e.target.closest('.message-farmer-btn') || e.target.closest('.message-assigned-farmer-btn')) {
                const button = e.target.closest('button');
                const farmerId = button.getAttribute('data-farmer-id') || '';
                const farmerName = button.getAttribute('data-farmer-name') || 'Farmer';
                const productName = button.getAttribute('data-product-name') || 'Unknown Product';
                const requestId = button.getAttribute('data-request-id') || '';
                
                // Set values in the modal
                document.getElementById('message_farmer_id').value = farmerId;
                document.getElementById('message_farmer_name').value = farmerName;
                document.getElementById('message_product_name').value = productName;
                document.getElementById('message_subject').value = "Regarding your request: " + productName;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('messageFarmerModal'));
                modal.show();
            }
        });

        // Auto-refresh notifications and messages
        function refreshData() {
            // Simple check for new messages
            fetch(window.location.href + '&check_updates=1')
                .then(response => response.text())
                .then(html => {
                    console.log('Checked for updates');
                })
                .catch(error => console.error('Error refreshing data:', error));
        }

        // Check for updates every 60 seconds
        setInterval(refreshData, 60000);

        // Initial check on page load
        $(document).ready(function() {
            // Mark messages as read when viewed
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('read')) {
                setTimeout(() => {
                    window.history.replaceState({}, document.title, window.location.pathname + '?tab=' + urlParams.get('tab'));
                }, 100);
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>

<?php
// Close connections
$customer_stmt->close();
$cart_stmt->close();
$wishlist_stmt->close();
$unread_stmt->close();
$messages_stmt->close();
$recent_orders_stmt->close();
$notification_stmt->close();
$request_notifications_stmt->close();
$customer_requests_stmt->close();
$conn->close();
?>