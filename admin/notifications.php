<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
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

// Handle notification actions
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $conn->query("UPDATE user_notification_status SET is_read = 1, read_at = NOW() WHERE notification_id = $notification_id AND user_id IN (SELECT user_id FROM users WHERE role = 'customer' OR role = 'farmer')");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE user_notification_status SET is_read = 1, read_at = NOW() WHERE is_read = 0 AND user_id IN (SELECT user_id FROM users WHERE role = 'customer' OR role = 'farmer')");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM notifications WHERE notification_id = $notification_id");
    $conn->query("DELETE FROM user_notification_status WHERE notification_id = $notification_id");
    header("Location: notifications.php");
    exit();
}

if (isset($_POST['send_notification'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $message = $conn->real_escape_string($_POST['message']);
    $target_roles = $conn->real_escape_string($_POST['target_roles']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    
    $conn->query("INSERT INTO notifications (title, message, target_roles, sender_id, sender_role, is_important, created_at) 
                  VALUES ('$title', '$message', '$target_roles', $admin_id, 'admin', $is_important, NOW())");
    
    $notification_id = $conn->insert_id;
    
    // Create status entries for all target users
    if ($target_roles == 'all') {
        $conn->query("INSERT INTO user_notification_status (notification_id, user_id)
                      SELECT $notification_id, user_id FROM users WHERE status = 'active'");
    } elseif ($target_roles == 'customers') {
        $conn->query("INSERT INTO user_notification_status (notification_id, user_id)
                      SELECT $notification_id, user_id FROM users WHERE role = 'customer' AND status = 'active'");
    } elseif ($target_roles == 'farmers') {
        $conn->query("INSERT INTO user_notification_status (notification_id, user_id)
                      SELECT $notification_id, user_id FROM users WHERE role = 'farmer' AND status = 'active'");
    }
    
    header("Location: notifications.php?sent=1");
    exit();
}

// Get counts for different notification types
$total_notifications = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$system_notifications = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE sender_role = 'admin'")->fetch_assoc()['count'];
$unread_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id IN (SELECT admin_id FROM admins) AND is_read = 0")->fetch_assoc()['count'];
$product_requests = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE status = 'Pending'")->fetch_assoc()['count'];
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE admin_approved = 'pending'")->fetch_assoc()['count'];
$new_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'")->fetch_assoc()['count'];

// Get all system notifications (sent by admin)
$system_notifications_list = $conn->query("
    SELECT n.*, 
           (SELECT COUNT(*) FROM user_notification_status uns WHERE uns.notification_id = n.notification_id AND uns.is_read = 0) as unread_count,
           (SELECT COUNT(*) FROM user_notification_status uns WHERE uns.notification_id = n.notification_id) as total_recipients
    FROM notifications n
    WHERE n.sender_role = 'admin'
    ORDER BY n.created_at DESC
");

// Get user messages (from customers/farmers to admin)
$messages = $conn->query("
    SELECT m.*, u.name as sender_name, u.role as sender_role_name,
           o.order_id, o.status as order_status,
           p.name as product_name
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN orders o ON m.related_order_id = o.order_id
    LEFT JOIN products p ON m.related_product_id = p.product_id
    WHERE m.receiver_role = 'admin' AND m.receiver_id = (SELECT admin_id FROM admins WHERE admin_id = $admin_id)
    ORDER BY m.created_at DESC
    LIMIT 50
");

// Get product requests
$product_requests_list = $conn->query("
    SELECT pr.*, u.name as customer_name, u.email as customer_email,
           f.name as assigned_farmer_name
    FROM product_requests pr
    JOIN users u ON pr.customer_id = u.user_id
    LEFT JOIN users f ON pr.assigned_farmer_id = f.user_id
    ORDER BY pr.created_at DESC
    LIMIT 20
");

// Get pending products
$pending_products_list = $conn->query("
    SELECT p.*, u.name as farmer_name, u.email as farmer_email
    FROM products p
    JOIN users u ON p.farmer_id = u.user_id
    WHERE p.admin_approved = 'pending'
    ORDER BY p.created_at DESC
    LIMIT 20
");

// Get recent orders
$recent_orders = $conn->query("
    SELECT o.*, u.name as customer_name
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SpiceCeylon Admin</title>
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
            --spice-orange: #e67e22;
            --spice-teal: #1abc9c;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--spice-dark) 0%, #1a252f 100%);
            min-height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
            position: fixed;
            width: 250px;
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--spice-blue);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.green { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-icon.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-icon.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-icon.teal { background: linear-gradient(135deg, #1abc9c, #16a085); }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--spice-dark);
        }
        
        .stat-info p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Notification Cards */
        .notification-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .section-header h4 {
            margin: 0;
            color: var(--spice-dark);
            font-weight: 600;
        }
        
        .section-header h4 i {
            margin-right: 10px;
            color: var(--spice-red);
        }
        
        .notification-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:hover {
            background: #f1f3f5;
            transform: translateX(5px);
        }
        
        .notification-item.important {
            border-left-color: var(--spice-red);
            background: linear-gradient(90deg, rgba(184, 92, 56, 0.05), #f8f9fa);
        }
        
        .notification-item.unread {
            background: #fff3e0;
            border-left-color: var(--spice-gold);
        }
        
        .notification-item.message {
            border-left-color: var(--spice-blue);
        }
        
        .notification-item.request {
            border-left-color: var(--spice-purple);
        }
        
        .notification-item.order {
            border-left-color: var(--spice-green);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        
        .notification-icon.admin { background: var(--spice-red); }
        .notification-icon.customer { background: var(--spice-blue); }
        .notification-icon.farmer { background: var(--spice-green); }
        .notification-icon.system { background: var(--spice-purple); }
        .notification-icon.order { background: var(--spice-teal); }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-title .badge {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        .notification-message {
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .notification-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #718096;
        }
        
        .notification-meta i {
            margin-right: 3px;
            font-size: 0.7rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .notification-actions .btn-sm {
            padding: 3px 10px;
            font-size: 0.8rem;
            border-radius: 20px;
        }
        
        /* Send Notification Form */
        .send-notification-form {
            background: linear-gradient(135deg, #667eea0d, #764ba20d);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--spice-blue);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-send {
            background: linear-gradient(135deg, var(--spice-green), #219653);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        /* Tabs */
        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #718096;
            padding: 12px 20px;
            font-weight: 500;
            position: relative;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--spice-red);
            background: none;
        }
        
        .nav-tabs-custom .nav-link.active:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--spice-red);
        }
        
        .nav-tabs-custom .nav-link i {
            margin-right: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-item {
                flex-direction: column;
            }
            
            .notification-actions {
                margin-top: 10px;
            }
        }
        
        /* Status Badges */
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.processing { background: #cce5ff; color: #004085; }
        .status-badge.completed { background: #d4edda; color: #155724; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
        .status-badge.shipped { background: #d1ecf1; color: #0c5460; }
        .status-badge.delivered { background: #d4edda; color: #155724; }
        .status-badge.confirmed { background: #d4edda; color: #155724; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include 'sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2">
                                <i class="fas fa-bell me-2" style="color: var(--spice-red);"></i>
                                Notifications Center
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-clock me-1"></i> 
                                Stay updated with all system activities and user interactions
                            </p>
                        </div>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i> Refresh
                            </button>
                            <a href="?mark_all_read=1" class="btn btn-outline-success">
                                <i class="fas fa-check-double me-2"></i> Mark All Read
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_notifications; ?></h3>
                            <p>Total Notifications</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $unread_messages; ?></h3>
                            <p>Unread Messages</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $product_requests; ?></h3>
                            <p>Product Requests</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pending_products; ?></h3>
                            <p>Pending Products</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon teal">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $new_orders; ?></h3>
                            <p>New Orders</p>
                        </div>
                    </div>
                </div>
                
                <!-- Success Message -->
                <?php if(isset($_GET['sent'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Notification sent successfully to all target users!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Send Notification Form -->
                <div class="notification-section">
                    <div class="section-header">
                        <h4><i class="fas fa-paper-plane"></i> Send New Notification</h4>
                    </div>
                    
                    <div class="send-notification-form">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Title</label>
                                        <input type="text" class="form-control" name="title" placeholder="e.g., System Update" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Target Audience</label>
                                        <select class="form-select" name="target_roles" required>
                                            <option value="all">All Users (Customers + Farmers)</option>
                                            <option value="customers">Customers Only</option>
                                            <option value="farmers">Farmers Only</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="message" rows="3" placeholder="Enter your notification message..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_important" id="isImportant">
                                        <label class="form-check-label" for="isImportant">
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                            Mark as Important (will highlight for users)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <button type="submit" name="send_notification" class="btn btn-send">
                                        <i class="fas fa-paper-plane me-2"></i> Send Notification
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs-custom" id="notificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                            <i class="fas fa-bullhorn"></i> System Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button">
                            <i class="fas fa-envelope"></i> User Messages
                            <?php if($unread_messages > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button">
                            <i class="fas fa-clipboard-list"></i> Product Requests
                            <?php if($product_requests > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $product_requests; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#pending-products" type="button">
                            <i class="fas fa-boxes"></i> Pending Products
                            <?php if($pending_products > 0): ?>
                                <span class="badge bg-info ms-2"><?php echo $pending_products; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                            <i class="fas fa-shopping-cart"></i> Recent Orders
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- System Notifications Tab -->
                    <div class="tab-pane fade show active" id="system" role="tabpanel">
                        <div class="notification-section">
                            <div class="section-header">
                                <h4><i class="fas fa-bullhorn"></i> System Notifications</h4>
                                <div>
                                    <span class="me-2"><i class="fas fa-circle text-danger"></i> Important</span>
                                    <span><i class="fas fa-circle text-warning"></i> Unread by users</span>
                                </div>
                            </div>
                            
                            <?php if($system_notifications_list && $system_notifications_list->num_rows > 0): ?>
                                <?php while($notification = $system_notifications_list->fetch_assoc()): ?>
                                    <div class="notification-item <?php echo $notification['is_important'] ? 'important' : ''; ?>">
                                        <div class="notification-icon admin">
                                            <i class="fas fa-bullhorn"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if($notification['is_important']): ?>
                                                    <span class="badge bg-danger">Important</span>
                                                <?php endif; ?>
                                                <?php if($notification['unread_count'] > 0): ?>
                                                    <span class="badge bg-warning"><?php echo $notification['unread_count']; ?> unread</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="notification-message">
                                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                            </div>
                                            
                                            <div class="notification-meta">
                                                <span><i class="fas fa-users"></i> Target: <?php echo ucfirst($notification['target_roles']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></span>
                                                <span><i class="fas fa-check-circle"></i> Sent to <?php echo $notification['total_recipients']; ?> users</span>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <a href="?delete=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this notification?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No system notifications yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Messages Tab -->
                    <div class="tab-pane fade" id="messages" role="tabpanel">
                        <div class="notification-section">
                            <div class="section-header">
                                <h4><i class="fas fa-envelope"></i> Messages from Users</h4>
                            </div>
                            
                            <?php if($messages && $messages->num_rows > 0): ?>
                                <?php while($message = $messages->fetch_assoc()): ?>
                                    <div class="notification-item message <?php echo $message['is_read'] ? '' : 'unread'; ?>">
                                        <div class="notification-icon <?php echo $message['sender_role_name']; ?>">
                                            <i class="fas <?php echo $message['sender_role_name'] == 'customer' ? 'fa-user' : 'fa-tractor'; ?>"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?php if(!$message['is_read']): ?>
                                                    <span class="badge bg-warning">New</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?>
                                            </div>
                                            
                                            <div class="notification-message">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            </div>
                                            
                                            <div class="notification-meta">
                                                <span><i class="fas fa-user"></i> From: <?php echo htmlspecialchars($message['sender_name']); ?> (<?php echo ucfirst($message['sender_role_name']); ?>)</span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?></span>
                                                <?php if($message['related_order_id']): ?>
                                                    <span><i class="fas fa-shopping-cart"></i> Order #<?php echo $message['related_order_id']; ?></span>
                                                <?php endif; ?>
                                                <?php if($message['related_product_id']): ?>
                                                    <span><i class="fas fa-box"></i> Product: <?php echo htmlspecialchars($message['product_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <a href="?mark_read=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="reply_message.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary" title="Reply">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-envelope-open"></i>
                                    <p>No messages from users</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Product Requests Tab -->
                    <div class="tab-pane fade" id="requests" role="tabpanel">
                        <div class="notification-section">
                            <div class="section-header">
                                <h4><i class="fas fa-clipboard-list"></i> Product Requests from Customers</h4>
                            </div>
                            
                            <?php if($product_requests_list && $product_requests_list->num_rows > 0): ?>
                                <?php while($request = $product_requests_list->fetch_assoc()): ?>
                                    <div class="notification-item request">
                                        <div class="notification-icon customer">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                Request: <?php echo htmlspecialchars($request['product_name']); ?>
                                                <span class="badge status-badge <?php echo strtolower($request['status']); ?>">
                                                    <?php echo $request['status']; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="notification-message">
                                                <strong>Description:</strong> <?php echo nl2br(htmlspecialchars($request['description'])); ?><br>
                                                <strong>Quantity:</strong> <?php echo $request['quantity_requested']; ?> | 
                                                <strong>Urgency:</strong> <?php echo $request['urgency']; ?>
                                            </div>
                                            
                                            <div class="notification-meta">
                                                <span><i class="fas fa-user"></i> Customer: <?php echo htmlspecialchars($request['customer_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($request['created_at'])); ?></span>
                                                <?php if($request['assigned_farmer_name']): ?>
                                                    <span><i class="fas fa-tractor"></i> Assigned to: <?php echo htmlspecialchars($request['assigned_farmer_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <a href="product_requests.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-outline-primary" title="Manage Request">
                                                <i class="fas fa-cog"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>No product requests</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Products Tab -->
                    <div class="tab-pane fade" id="pending-products" role="tabpanel">
                        <div class="notification-section">
                            <div class="section-header">
                                <h4><i class="fas fa-boxes"></i> Products Awaiting Approval</h4>
                            </div>
                            
                            <?php if($pending_products_list && $pending_products_list->num_rows > 0): ?>
                                <?php while($product = $pending_products_list->fetch_assoc()): ?>
                                    <div class="notification-item">
                                        <div class="notification-icon farmer">
                                            <i class="fas fa-tractor"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </div>
                                            
                                            <div class="notification-message">
                                                <strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?> | 
                                                <strong>Price:</strong> Rs. <?php echo number_format($product['price'], 2); ?> | 
                                                <strong>Stock:</strong> <?php echo $product['stock']; ?>
                                            </div>
                                            
                                            <div class="notification-meta">
                                                <span><i class="fas fa-user"></i> Farmer: <?php echo htmlspecialchars($product['farmer_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> Submitted: <?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <a href="approve_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <p>No pending products - all products are approved!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Orders Tab -->
                    <div class="tab-pane fade" id="orders" role="tabpanel">
                        <div class="notification-section">
                            <div class="section-header">
                                <h4><i class="fas fa-shopping-cart"></i> Recent Orders</h4>
                            </div>
                            
                            <?php if($recent_orders && $recent_orders->num_rows > 0): ?>
                                <?php while($order = $recent_orders->fetch_assoc()): ?>
                                    <div class="notification-item order">
                                        <div class="notification-icon order">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                Order #<?php echo $order['order_id']; ?>
                                                <span class="badge status-badge <?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="notification-message">
                                                <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?> | 
                                                <strong>Total:</strong> Rs. <?php echo number_format($order['final_total'] ?: $order['total_amount'], 2); ?>
                                            </div>
                                            
                                            <div class="notification-meta">
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['shipping_city']); ?></span>
                                                <span><i class="fas fa-credit-card"></i> <?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Order">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="update_order_status.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-success" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>No orders yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notifications every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
        
        // Show toast for new notifications (optional)
        <?php if($unread_messages > 0): ?>
            // You can add a toast notification here if needed
        <?php endif; ?>
    </script>
</body>
</html>