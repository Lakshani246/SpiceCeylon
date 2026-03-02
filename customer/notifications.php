<?php
require_once '../config/db.php';
require_once '../config/auth_check.php';

// Redirect if not customer
if ($_SESSION['role'] !== 'customer') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$customer_id = $_SESSION['user_id'];

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

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    // Get all unread notifications for this user
    $unread_notif_query = "SELECT n.notification_id 
                          FROM notifications n
                          LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                          WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                          AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                          AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    $unread_notif_stmt = $conn->prepare($unread_notif_query);
    $unread_notif_stmt->bind_param("i", $customer_id);
    $unread_notif_stmt->execute();
    $unread_notif_result = $unread_notif_stmt->get_result();
    
    // Mark each as read
    while ($notification = $unread_notif_result->fetch_assoc()) {
        $check_query = "SELECT status_id FROM user_notification_status WHERE notification_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $notification['notification_id'], $customer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing
            $update_query = "UPDATE user_notification_status SET is_read = TRUE, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $notification['notification_id'], $customer_id);
            $update_stmt->execute();
        } else {
            // Insert new
            $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, TRUE, NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ii", $notification['notification_id'], $customer_id);
            $insert_stmt->execute();
        }
    }
    
    // ADDED: Also mark announcements as read
    $unread_announce_query = "SELECT announcement_id FROM user_announcement_status WHERE user_id = ? AND is_read = 0";
    $unread_announce_stmt = $conn->prepare($unread_announce_query);
    $unread_announce_stmt->bind_param("i", $customer_id);
    $unread_announce_stmt->execute();
    $unread_announce_result = $unread_announce_stmt->get_result();
    
    while ($announcement = $unread_announce_result->fetch_assoc()) {
        $update_announce = "UPDATE user_announcement_status SET is_read = 1, read_at = NOW() WHERE announcement_id = ? AND user_id = ?";
        $update_announce_stmt = $conn->prepare($update_announce);
        $update_announce_stmt->bind_param("ii", $announcement['announcement_id'], $customer_id);
        $update_announce_stmt->execute();
    }
    
    header('Location: notifications.php');
    exit();
}

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    $check_query = "SELECT status_id FROM user_notification_status WHERE notification_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $notification_id, $customer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_query = "UPDATE user_notification_status SET is_read = TRUE, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $notification_id, $customer_id);
        $update_stmt->execute();
    } else {
        $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, TRUE, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $notification_id, $customer_id);
        $insert_stmt->execute();
    }
    
    header('Location: notifications.php');
    exit();
}

// ADDED: Mark announcement as read
if (isset($_GET['mark_announce_read']) && is_numeric($_GET['mark_announce_read'])) {
    $announcement_id = $_GET['mark_announce_read'];
    
    $update_query = "UPDATE user_announcement_status SET is_read = 1, read_at = NOW() WHERE announcement_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $announcement_id, $customer_id);
    $update_stmt->execute();
    
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
                   WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                   AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                   AND (n.expires_at IS NULL OR n.expires_at > NOW())
                   ON DUPLICATE KEY UPDATE is_read = TRUE, read_at = NOW()";
    $clear_stmt = $conn->prepare($clear_query);
    $clear_stmt->bind_param("ii", $customer_id, $customer_id);
    $clear_stmt->execute();
    
    // ADDED: Clear announcements
    $clear_announce = "UPDATE user_announcement_status SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
    $clear_announce_stmt = $conn->prepare($clear_announce);
    $clear_announce_stmt->bind_param("i", $customer_id);
    $clear_announce_stmt->execute();
    
    header('Location: notifications.php');
    exit();
}

// CHANGED: Get notifications and announcements for customer
$notifications_query = "SELECT 
                       'notification' as type,
                       n.notification_id as id,
                       n.title,
                       n.message,
                       n.created_at,
                       n.expires_at,
                       n.is_important,
                       n.sender_id,
                       n.sender_role,
                       s.name as sender_name,
                       uns.is_read,
                       uns.read_at,
                       CASE 
                           WHEN n.target_roles = 'all' THEN 'All Users'
                           WHEN n.target_roles = 'customers' THEN 'Customers Only'
                           ELSE 'System'
                       END as audience
                       FROM notifications n
                       LEFT JOIN users s ON n.sender_id = s.user_id AND n.sender_role = s.role
                       LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                       WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                       AND (n.expires_at IS NULL OR n.expires_at > NOW())
                       
                       UNION ALL
                       
                       SELECT 
                       'announcement' as type,
                       a.announcement_id as id,
                       a.title,
                       a.message,
                       a.created_at,
                       a.expires_at,
                       a.is_important,
                       a.created_by as sender_id,
                       'admin' as sender_role,
                       adm.username as sender_name,
                       uas.is_read,
                       uas.read_at,
                       CASE 
                           WHEN a.target_roles = 'all' THEN 'All Users'
                           WHEN a.target_roles = 'customers' THEN 'Customers Only'
                           WHEN a.target_roles = 'farmers' THEN 'Farmers Only'
                           WHEN a.target_roles = 'admins' THEN 'Admins Only'
                           ELSE a.target_roles
                       END as audience
                       FROM announcements a
                       LEFT JOIN admins adm ON a.created_by = adm.admin_id
                       LEFT JOIN user_announcement_status uas ON a.announcement_id = uas.announcement_id AND uas.user_id = ?
                       WHERE (a.target_roles = 'all' OR a.target_roles = 'customers')
                       AND (a.status = 'active')
                       AND (a.expires_at IS NULL OR a.expires_at > NOW())
                       
                       ORDER BY created_at DESC";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("ii", $customer_id, $customer_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// CHANGED: Get unread notification count (both notifications and announcements)
$unread_notif_query = "SELECT 
                       (SELECT COUNT(DISTINCT n.notification_id) 
                        FROM notifications n
                        LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                        WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                        AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                        AND (n.expires_at IS NULL OR n.expires_at > NOW())) +
                       (SELECT COUNT(DISTINCT a.announcement_id) 
                        FROM announcements a
                        LEFT JOIN user_announcement_status uas ON a.announcement_id = uas.announcement_id AND uas.user_id = ?
                        WHERE (a.target_roles = 'all' OR a.target_roles = 'customers')
                        AND (uas.is_read IS NULL OR uas.is_read = FALSE)
                        AND (a.status = 'active')
                        AND (a.expires_at IS NULL OR a.expires_at > NOW())) as count";
$unread_notif_stmt = $conn->prepare($unread_notif_query);
$unread_notif_stmt->bind_param("ii", $customer_id, $customer_id);
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
    <title>Notifications - SpiceCeylon</title>
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
            border-left: 4px solid var(--spice-gold);
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
        
        /* ADDED: Announcement type styling */
        .notification-item.announcement {
            border-left: 4px solid var(--spice-purple);
        }
        
        .notification-item.announcement.unread {
            background: rgba(155, 89, 182, 0.05);
            border-left: 4px solid var(--spice-purple);
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--spice-dark);
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
            color: var(--spice-gold);
        }
        
        .badge-important {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .badge-audience {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
        }
        
        /* ADDED: Announcement badge */
        .badge-announcement {
            background: rgba(155, 89, 182, 0.1);
            color: var(--spice-purple);
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
        
        /* Notification Indicator */
        .notification-dot {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--spice-gold);
            display: none;
        }
        
        .notification-item.unread .notification-dot {
            display: block;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            
            .notification-item {
                padding: 15px;
            }
        }
        
        /* Filter Buttons */
        .filter-buttons .btn {
            margin: 0 5px 5px 0;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        /* Stat Cards */
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- Navigation (KEEP EXACTLY AS IS) -->
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
            <!-- Sidebar (KEEP EXACTLY AS IS) -->
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
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope"></i>
                                My Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="notifications.php">
                                <i class="fas fa-bell"></i>
                                Notifications
                                <?php if($unread_notif_count > 0): ?>
                                    <span class="badge bg-warning ms-1"><?php echo $unread_notif_count; ?></span>
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
                <div class="welcome-banner" style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(155, 89, 182, 0.1));">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h1 class="display-6"><i class="fas fa-bell me-2"></i> Notifications</h1>
                            <p class="lead">Stay updated with system announcements and important alerts</p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <?php if($unread_notif_count > 0): ?>
                                <a href="?mark_all_read" class="btn btn-spice me-2">
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

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.2));">
                            <div class="stat-icon" style="background: rgba(243, 156, 18, 0.2); color: var(--spice-gold);">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_notifications; ?></div>
                            <div class="stat-label">Total Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.2));">
                            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.2); color: var(--spice-blue);">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <div class="stat-value"><?php echo $read_notifications; ?></div>
                            <div class="stat-label">Read Notifications</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.2));">
                            <div class="stat-icon" style="background: rgba(231, 76, 60, 0.2); color: #e74c3c;">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $unread_notif_count; ?></div>
                            <div class="stat-label">Unread Notifications</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="dashboard-card mb-4">
                    <div class="card-body">
                        <div class="filter-buttons">
                            <a href="?filter=all" class="btn <?php echo !isset($_GET['filter']) || $_GET['filter'] == 'all' ? 'btn-spice' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-list me-1"></i>All
                            </a>
                            <a href="?filter=unread" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'unread' ? 'btn-spice' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-circle me-1"></i>Unread
                            </a>
                            <a href="?filter=important" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'important' ? 'btn-spice' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-exclamation-circle me-1"></i>Important
                            </a>
                            <a href="?filter=read" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'read' ? 'btn-spice' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-check-circle me-1"></i>Read
                            </a>
                            <!-- ADDED: Announcement filter -->
                            <a href="?filter=announcements" class="btn <?php echo isset($_GET['filter']) && $_GET['filter'] == 'announcements' ? 'btn-spice' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-bullhorn me-1"></i>Announcements
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-bullhorn me-2"></i>System Notifications & Announcements
                        <div class="float-end">
                            <span class="badge bg-warning"><?php echo $unread_notif_count; ?> Unread</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if($notifications_result->num_rows > 0): ?>
                            <div class="notifications-list">
                                <?php 
                                $notifications_result->data_seek(0);
                                while($notification = $notifications_result->fetch_assoc()): 
                                    $is_read = $notification['is_read'] == 1;
                                    $is_important = $notification['is_important'] == 1;
                                    $is_expired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                                    $type = $notification['type']; // 'notification' or 'announcement'
                                ?>
                                <!-- CHANGED: Added conditional class for announcement type -->
                                <div class="notification-item <?php echo !$is_read ? 'unread' : 'read'; ?> <?php echo $is_important ? 'notification-important' : ''; ?> <?php echo $type; ?>">
                                    <div class="notification-dot"></div>
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <?php if($is_important): ?>
                                                <span class="notification-badge badge-important me-2">
                                                    <i class="fas fa-exclamation-circle me-1"></i>Important
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- ADDED: Type badge -->
                                            <?php if($type == 'announcement'): ?>
                                                <span class="notification-badge badge-announcement me-2">
                                                    <i class="fas fa-bullhorn me-1"></i>Announcement
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
                                        <i class="fas fa-<?php echo $is_important ? 'exclamation-triangle text-danger' : ($type == 'announcement' ? 'bullhorn text-purple' : 'info-circle text-info'); ?> me-2"></i>
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
                                            <!-- CHANGED: Different mark read links based on type -->
                                            <?php if($type == 'announcement'): ?>
                                                <a href="?mark_announce_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i>Mark as Read
                                                </a>
                                            <?php else: ?>
                                                <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i>Mark as Read
                                                </a>
                                            <?php endif; ?>
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
                                <p class="text-muted mb-4">You're all caught up! Check back later for updates.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer (KEEP EXACTLY AS IS) -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notification count
        setInterval(function() {
            fetch('../actions/get_unread_count.php?type=notifications')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.nav-link[href="notifications.php"] .badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-warning ms-1';
                            newBadge.textContent = data.count;
                            document.querySelector('.nav-link[href="notifications.php"]').appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
        }, 30000);

        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('a')) {
                    // CHANGED: Handle both notification and announcement links
                    const notificationLink = this.querySelector('a[href*="mark_read="]');
                    const announcementLink = this.querySelector('a[href*="mark_announce_read="]');
                    const markLink = notificationLink || announcementLink;
                    
                    if (markLink) {
                        const url = new URL(markLink.href, window.location.origin);
                        const params = new URLSearchParams(url.search);
                        const id = params.get('mark_read') || params.get('mark_announce_read');
                        
                        // Update UI immediately
                        this.classList.remove('unread');
                        this.classList.add('read');
                        this.querySelector('.notification-dot').style.display = 'none';
                        
                        // Update badge count
                        const badge = document.querySelector('.nav-link[href="notifications.php"] .badge');
                        if (badge) {
                            let count = parseInt(badge.textContent);
                            if (count > 1) {
                                badge.textContent = count - 1;
                            } else {
                                badge.remove();
                            }
                        }
                        
                        // Update stats
                        const unreadStat = document.querySelector('.stat-card:nth-child(3) .stat-value');
                        const readStat = document.querySelector('.stat-card:nth-child(2) .stat-value');
                        if (unreadStat && readStat) {
                            let unreadCount = parseInt(unreadStat.textContent);
                            let readCount = parseInt(readStat.textContent);
                            unreadStat.textContent = unreadCount - 1;
                            readStat.textContent = readCount + 1;
                        }
                        
                        // Send request to mark as read
                        fetch(`?${markLink.href.split('?')[1]}`, {method: 'GET'});
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close connections
$customer_stmt->close();
$cart_stmt->close();
$wishlist_stmt->close();
$notifications_stmt->close();
$unread_notif_stmt->close();
$conn->close();
?>