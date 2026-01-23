<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get farmer data
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get statistics for farmer
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id")->fetch_assoc()['count'];
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id AND status='Pending'")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE assigned_farmer_id = $farmer_id AND status = 'Pending'")->fetch_assoc()['count'];

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    // Get all unread notifications for this farmer
    $unread_notif_query = "SELECT n.notification_id 
                          FROM notifications n
                          LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                          WHERE (n.target_roles = 'all' OR n.target_roles = 'farmers')
                          AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                          AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    $unread_notif_stmt = $conn->prepare($unread_notif_query);
    $unread_notif_stmt->bind_param("i", $farmer_id);
    $unread_notif_stmt->execute();
    $unread_notif_result = $unread_notif_stmt->get_result();
    
    // Mark each as read
    while ($notification = $unread_notif_result->fetch_assoc()) {
        $check_query = "SELECT status_id FROM user_notification_status WHERE notification_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing
            $update_query = "UPDATE user_notification_status SET is_read = TRUE, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
            $update_stmt->execute();
        } else {
            // Insert new
            $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, TRUE, NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
            $insert_stmt->execute();
        }
    }
    
    header('Location: notifications.php');
    exit();
}

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    $check_query = "SELECT status_id FROM user_notification_status WHERE notification_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $notification_id, $farmer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_query = "UPDATE user_notification_status SET is_read = TRUE, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $notification_id, $farmer_id);
        $update_stmt->execute();
    } else {
        $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, TRUE, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $notification_id, $farmer_id);
        $insert_stmt->execute();
    }
    
    header('Location: notifications.php');
    exit();
}

// Clear all notifications
if (isset($_GET['clear_all'])) {
    // Mark all as read first
    $clear_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) 
                   SELECT n.notification_id, ?, TRUE, NOW() 
                   FROM notifications n
                   LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                   WHERE (n.target_roles = 'all' OR n.target_roles = 'farmers')
                   AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                   AND (n.expires_at IS NULL OR n.expires_at > NOW())
                   ON DUPLICATE KEY UPDATE is_read = TRUE, read_at = NOW()";
    $clear_stmt = $conn->prepare($clear_query);
    $clear_stmt->bind_param("ii", $farmer_id, $farmer_id);
    $clear_stmt->execute();
    
    header('Location: notifications.php');
    exit();
}

// Get notifications for farmer
$notifications_query = "SELECT n.*, 
                       s.name as sender_name,
                       uns.is_read,
                       uns.read_at,
                       CASE 
                           WHEN n.target_roles = 'all' THEN 'All Users'
                           WHEN n.target_roles = 'customers' THEN 'Customers Only'
                           WHEN n.target_roles = 'farmers' THEN 'Farmers Only'
                           WHEN n.target_roles = 'admins' THEN 'Admins Only'
                           ELSE 'Specific User'
                       END as audience
                       FROM notifications n
                       LEFT JOIN users s ON n.sender_id = s.user_id AND n.sender_role = s.role
                       LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                       WHERE (n.target_roles = 'all' OR n.target_roles = 'farmers')
                       AND (n.expires_at IS NULL OR n.expires_at > NOW())
                       ORDER BY n.created_at DESC";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $farmer_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// Get unread notification count
$unread_notif_query = "SELECT COUNT(DISTINCT n.notification_id) as count 
                      FROM notifications n
                      LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                      WHERE (n.target_roles = 'all' OR n.target_roles = 'farmers')
                      AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                      AND (n.expires_at IS NULL OR n.expires_at > NOW())";
$unread_notif_stmt = $conn->prepare($unread_notif_query);
$unread_notif_stmt->bind_param("i", $farmer_id);
$unread_notif_stmt->execute();
$unread_notif_result = $unread_notif_stmt->get_result();
$unread_notif_count = $unread_notif_result->fetch_assoc()['count'];

// Get notification stats
$total_notifications = $notifications_result->num_rows;
$read_notifications = $total_notifications - $unread_notif_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Farmer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --farmer-green: #27ae60;
            --farmer-dark: #2c3e50;
            --farmer-gold: #f39c12;
            --farmer-blue: #3498db;
            --farmer-brown: #8b4513;
            --pending: #f39c12;
            --approved: #27ae60;
            --rejected: #e74c3c;
            --processing: #3498db;
            --completed: #2ecc71;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2d6a4f 0%, #1b4332 100%);
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
            background: rgba(39, 174, 96, 0.2);
            border-left-color: var(--farmer-green);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.3), rgba(139, 69, 19, 0.2));
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
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
        
        /* Notification Styling */
        .notification-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .notification-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .notification-item.unread {
            background: rgba(243, 156, 18, 0.05);
            border-left: 4px solid var(--farmer-gold);
        }
        
        .notification-item.read {
            background: white;
            border-left: 4px solid #ddd;
            opacity: 0.8;
        }
        
        .notification-important {
            background: rgba(231, 76, 60, 0.05);
            border-left: 4px solid #e74c3c;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--farmer-dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .notification-content {
            color: #444;
            line-height: 1.6;
            margin: 10px 0;
        }
        
        .notification-meta {
            font-size: 0.85rem;
            color: #888;
            margin-top: 10px;
        }
        
        .notification-actions {
            margin-top: 15px;
        }
        
        .notification-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-unread {
            background: rgba(243, 156, 18, 0.1);
            color: var(--farmer-gold);
        }
        
        .badge-important {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .badge-audience {
            background: rgba(52, 152, 219, 0.1);
            color: var(--farmer-blue);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        /* Notification Indicator */
        .notification-dot {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--farmer-gold);
            display: none;
        }
        
        .notification-item.unread .notification-dot {
            display: block;
        }
        
        /* Notification Stats */
        .notification-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .notification-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-total .notification-stat-icon { background: rgba(139, 69, 19, 0.1); color: var(--farmer-brown); }
        .stat-read .notification-stat-icon { background: rgba(39, 174, 96, 0.1); color: var(--farmer-green); }
        .stat-unread .notification-stat-icon { background: rgba(243, 156, 18, 0.1); color: var(--farmer-gold); }
        .stat-important .notification-stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
        
        .notification-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--farmer-dark);
            margin-bottom: 5px;
        }
        
        .notification-stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Buttons */
        .btn-farmer {
            background: var(--farmer-green);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-farmer:hover {
            background: #219653;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-farmer-outline {
            color: var(--farmer-green);
            border: 2px solid var(--farmer-green);
            background: transparent;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-farmer-outline:hover {
            background: var(--farmer-green);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
                min-height: auto;
            }
            
            .notification-item {
                padding: 15px;
            }
        }
        
        /* Filter Buttons */
        .filter-buttons .btn {
            margin: 0 5px 5px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Farmer Sidebar -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="brand">
                    <h4 class="text-white mb-1">
                        <i class="fas fa-tractor me-2"></i>
                        Farmer Panel
                    </h4>
                    <small class="text-light opacity-75">SpiceCeylon</small>
                </div>
                
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_products.php">
                            <i class="fas fa-leaf me-2"></i>
                            My Products
                            <?php if($pending_products > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $pending_products; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_product.php">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add New Product
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_requests.php">
                            <i class="fas fa-inbox me-2"></i>
                            Customer Requests
                            <?php if($pending_requests > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $pending_requests; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-2"></i>
                            Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
                            <?php if($unread_notif_count > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $unread_notif_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_sales.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Sales Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-2"></i>
                            My Profile
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="../auth/logout.php" style="background: rgba(231, 76, 60, 0.1);">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
                
                <div class="mt-auto p-3 text-center text-light opacity-75 small">
                    <i class="fas fa-seedling me-1"></i>
                    Farmer ID: F<?php echo str_pad($farmer_id, 4, '0', STR_PAD_LEFT); ?>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-bell me-2" style="color: var(--farmer-gold);"></i>
                                Notifications
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Stay updated with system announcements, order updates, and important alerts for farmers
                            </p>
                        </div>
                        <div>
                            <?php if($unread_notif_count > 0): ?>
                                <a href="?mark_all_read" class="btn btn-farmer me-2">
                                    <i class="fas fa-check-double me-1"></i>
                                    Mark All as Read
                                </a>
                            <?php endif; ?>
                            <a href="?clear_all" class="btn btn-outline-secondary" onclick="return confirm('Clear all notifications? This will mark all as read.')">
                                <i class="fas fa-trash-alt me-1"></i>
                                Clear All
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Notification Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="notification-stat-card stat-total">
                            <div class="notification-stat-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $total_notifications; ?></div>
                            <div class="notification-stat-label">Total Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="notification-stat-card stat-read">
                            <div class="notification-stat-icon">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $read_notifications; ?></div>
                            <div class="notification-stat-label">Read Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="notification-stat-card stat-unread">
                            <div class="notification-stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $unread_notif_count; ?></div>
                            <div class="notification-stat-label">Unread Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php
                        // Count important notifications
                        $important_count = $conn->query("
                            SELECT COUNT(*) as count 
                            FROM notifications 
                            WHERE (target_roles = 'all' OR target_roles = 'farmers')
                            AND is_important = TRUE
                            AND (expires_at IS NULL OR expires_at > NOW())
                        ")->fetch_assoc()['count'];
                        ?>
                        <div class="notification-stat-card stat-important">
                            <div class="notification-stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $important_count; ?></div>
                            <div class="notification-stat-label">Important Alerts</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="analytics-card mb-4">
                    <div class="filter-buttons">
                        <a href="?filter=all" class="btn <?php echo !isset($_GET['filter']) || $_GET['filter'] == 'all' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-list me-1"></i>All
                        </a>
                        <a href="?filter=unread" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'unread' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-circle me-1"></i>Unread
                        </a>
                        <a href="?filter=important" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'important' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-exclamation-circle me-1"></i>Important
                        </a>
                        <a href="?filter=read" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'read' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-check-circle me-1"></i>Read
                        </a>
                        <a href="?filter=farmers" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'farmers' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-tractor me-1"></i>Farmers Only
                        </a>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn me-2" style="color: var(--farmer-brown);"></i>
                            System Notifications
                        </h5>
                        <div>
                            <span class="badge bg-warning"><?php echo $unread_notif_count; ?> Unread</span>
                        </div>
                    </div>
                    
                    <?php if($notifications_result->num_rows > 0): ?>
                        <div class="notifications-list">
                            <?php 
                            $notifications_result->data_seek(0);
                            while($notification = $notifications_result->fetch_assoc()): 
                                $is_read = $notification['is_read'] == 1;
                                $is_important = $notification['is_important'] == 1;
                                $is_expired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                            ?>
                            <div class="notification-item <?php echo !$is_read ? 'unread' : 'read'; ?> <?php echo $is_important ? 'notification-important' : ''; ?>">
                                <div class="notification-dot"></div>
                                
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <?php if($is_important): ?>
                                            <span class="notification-badge badge-important me-2">
                                                <i class="fas fa-exclamation-circle me-1"></i>Important
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="notification-badge badge-audience">
                                            <i class="fas fa-users me-1"></i><?php echo $notification['audience']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="notification-meta">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        <?php if($notification['expires_at']): ?>
                                            <br><small>Expires: <?php echo date('M j, Y', strtotime($notification['expires_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="notification-title">
                                    <i class="fas fa-<?php echo $is_important ? 'exclamation-triangle text-danger' : 'info-circle text-info'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                
                                <?php if($notification['sender_name']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>From: <?php echo htmlspecialchars($notification['sender_name']); ?>
                                            (<?php echo $notification['sender_role']; ?>)
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="notification-content">
                                    <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if(!$is_read): ?>
                                        <a href="?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check me-1"></i>Mark as Read
                                        </a>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>Read on <?php echo date('M j, Y', strtotime($notification['read_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if($is_expired): ?>
                                        <span class="badge bg-secondary ms-2">
                                            <i class="fas fa-clock me-1"></i>Expired
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($notification['target_roles'] == 'farmers'): ?>
                                        <span class="badge bg-primary ms-2">
                                            <i class="fas fa-tractor me-1"></i>Farmers
                                        </span>
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
                            <h4 class="text-muted mb-3">No notifications yet</h4>
                            <p class="text-muted mb-4">
                                You're all caught up! Check back later for updates about orders, products, and system announcements.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-seedling me-1"></i> 
                                SpiceCeylon Farmer Panel v1.0 • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-bell me-1"></i> 
                                Notifications: <span class="text-warning"><?php echo $unread_notif_count; ?> unread</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-tractor me-1"></i> 
                                Farmer: <?php echo htmlspecialchars($farmer['name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notification count
        setInterval(function() {
            $.ajax({
                url: '../actions/get_unread_count.php?type=notifications&role=farmer',
                method: 'GET',
                success: function(data) {
                    const response = JSON.parse(data);
                    const badge = document.querySelector('.nav-link[href="notifications.php"] .badge');
                    if (response.count > 0) {
                        if (badge) {
                            badge.textContent = response.count;
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-warning float-end';
                            newBadge.textContent = response.count;
                            document.querySelector('.nav-link[href="notifications.php"]').appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }
            });
        }, 30000);

        // Mark notification as read when clicked
        $('.notification-item.unread').on('click', function(e) {
            if (!$(e.target).closest('a').length) {
                const notificationId = $(this).find('a[href*="mark_read="]').attr('href');
                if (notificationId) {
                    const id = notificationId.split('=')[1];
                    
                    // Update UI immediately
                    $(this).removeClass('unread').addClass('read');
                    $(this).find('.notification-dot').hide();
                    
                    // Update badge count
                    const badge = $('.nav-link[href="notifications.php"] .badge');
                    if (badge.length) {
                        let count = parseInt(badge.text());
                        if (count > 1) {
                            badge.text(count - 1);
                        } else {
                            badge.remove();
                        }
                    }
                    
                    // Send request to mark as read
                    $.ajax({
                        url: `?mark_read=${id}`,
                        method: 'GET'
                    });
                }
            }
        });
    </script>
</body>
</html>