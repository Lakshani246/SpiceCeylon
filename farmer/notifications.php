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
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Mark notifications as read
        $unread_notif_query = "SELECT n.notification_id 
                              FROM notifications n
                              LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                              WHERE (n.target_roles IN ('all', 'farmers') OR (n.target_roles = 'specific' AND n.target_user_id = ?))
                              AND (uns.is_read IS NULL OR uns.is_read = 0)
                              AND (n.expires_at IS NULL OR n.expires_at > NOW())";
        $unread_notif_stmt = $conn->prepare($unread_notif_query);
        $unread_notif_stmt->bind_param("ii", $farmer_id, $farmer_id);
        $unread_notif_stmt->execute();
        $unread_notif_result = $unread_notif_stmt->get_result();
        
        while ($notification = $unread_notif_result->fetch_assoc()) {
            $check_query = "SELECT status_id FROM user_notification_status WHERE notification_id = ? AND user_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $update_query = "UPDATE user_notification_status SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
                $update_stmt->execute();
            } else {
                $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW())";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("ii", $notification['notification_id'], $farmer_id);
                $insert_stmt->execute();
            }
        }
        
        // Mark announcements as read
        $unread_announce_query = "SELECT uas.status_id FROM user_announcement_status uas
                                  JOIN announcements a ON uas.announcement_id = a.announcement_id
                                  WHERE uas.user_id = ? AND uas.is_read = 0
                                  AND (a.target_roles IN ('all', 'farmers') OR (a.target_roles = 'specific' AND a.target_user_id = ?))
                                  AND a.status = 'active'
                                  AND (a.expires_at IS NULL OR a.expires_at > NOW())";
        $unread_announce_stmt = $conn->prepare($unread_announce_query);
        $unread_announce_stmt->bind_param("ii", $farmer_id, $farmer_id);
        $unread_announce_stmt->execute();
        $unread_announce_result = $unread_announce_stmt->get_result();
        
        while ($announcement = $unread_announce_result->fetch_assoc()) {
            $update_announce = "UPDATE user_announcement_status SET is_read = 1, read_at = NOW() WHERE status_id = ?";
            $update_announce_stmt = $conn->prepare($update_announce);
            $update_announce_stmt->bind_param("i", $announcement['status_id']);
            $update_announce_stmt->execute();
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
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
        $update_query = "UPDATE user_notification_status SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $notification_id, $farmer_id);
        $update_stmt->execute();
    } else {
        $insert_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $notification_id, $farmer_id);
        $insert_stmt->execute();
    }
    
    header('Location: notifications.php');
    exit();
}

// Mark announcement as read
if (isset($_GET['mark_announce_read']) && is_numeric($_GET['mark_announce_read'])) {
    $announcement_id = $_GET['mark_announce_read'];
    
    $check_query = "SELECT status_id FROM user_announcement_status WHERE announcement_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $announcement_id, $farmer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_query = "UPDATE user_announcement_status SET is_read = 1, read_at = NOW() WHERE announcement_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $announcement_id, $farmer_id);
        $update_stmt->execute();
    }
    
    header('Location: notifications.php');
    exit();
}

// Clear all notifications (delete read status)
if (isset($_GET['clear_all'])) {
    $conn->begin_transaction();
    
    try {
        // Mark all notifications as read first
        $mark_notif_query = "INSERT INTO user_notification_status (notification_id, user_id, is_read, read_at) 
                           SELECT n.notification_id, ?, 1, NOW() 
                           FROM notifications n
                           LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                           WHERE (n.target_roles IN ('all', 'farmers') OR (n.target_roles = 'specific' AND n.target_user_id = ?))
                           AND (uns.is_read IS NULL OR uns.is_read = 0)
                           AND (n.expires_at IS NULL OR n.expires_at > NOW())
                           ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()";
        $mark_notif_stmt = $conn->prepare($mark_notif_query);
        $mark_notif_stmt->bind_param("iii", $farmer_id, $farmer_id, $farmer_id);
        $mark_notif_stmt->execute();
        
        // Mark all announcements as read
        $mark_announce_query = "UPDATE user_announcement_status uas
                              JOIN announcements a ON uas.announcement_id = a.announcement_id
                              SET uas.is_read = 1, uas.read_at = NOW()
                              WHERE uas.user_id = ? AND uas.is_read = 0
                              AND (a.target_roles IN ('all', 'farmers') OR (a.target_roles = 'specific' AND a.target_user_id = ?))
                              AND a.status = 'active'
                              AND (a.expires_at IS NULL OR a.expires_at > NOW())";
        $mark_announce_stmt = $conn->prepare($mark_announce_query);
        $mark_announce_stmt->bind_param("ii", $farmer_id, $farmer_id);
        $mark_announce_stmt->execute();
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    
    header('Location: notifications.php');
    exit();
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get notifications and announcements for farmer
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
                       COALESCE(u.name, a_username.username) as sender_name,
                       COALESCE(uns.is_read, 0) as is_read,
                       uns.read_at,
                       CASE 
                           WHEN n.target_roles = 'all' THEN 'All Users'
                           WHEN n.target_roles = 'customers' THEN 'Customers Only'
                           WHEN n.target_roles = 'farmers' THEN 'Farmers Only'
                           WHEN n.target_roles = 'admins' THEN 'Admins Only'
                           WHEN n.target_roles = 'specific' THEN 'Personal'
                           ELSE 'Unknown'
                       END as audience,
                       n.target_roles as target_role,
                       n.target_user_id
                       FROM notifications n
                       LEFT JOIN users u ON n.sender_id = u.user_id AND n.sender_role = u.role
                       LEFT JOIN admins a_username ON n.sender_id = a_username.admin_id AND n.sender_role = 'admin'
                       LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                       WHERE (n.target_roles IN ('all', 'farmers') OR (n.target_roles = 'specific' AND n.target_user_id = ?))
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
                       COALESCE(uas.is_read, 0) as is_read,
                       uas.read_at,
                       CASE 
                           WHEN a.target_roles = 'all' THEN 'All Users'
                           WHEN a.target_roles = 'customers' THEN 'Customers Only'
                           WHEN a.target_roles = 'farmers' THEN 'Farmers Only'
                           WHEN a.target_roles = 'admins' THEN 'Admins Only'
                           WHEN a.target_roles = 'specific' THEN 'Personal'
                           ELSE 'Unknown'
                       END as audience,
                       a.target_roles as target_role,
                       a.target_user_id
                       FROM announcements a
                       LEFT JOIN admins adm ON a.created_by = adm.admin_id
                       LEFT JOIN user_announcement_status uas ON a.announcement_id = uas.announcement_id AND uas.user_id = ?
                       WHERE (a.target_roles IN ('all', 'farmers') OR (a.target_roles = 'specific' AND a.target_user_id = ?))
                       AND a.status = 'active'
                       AND (a.expires_at IS NULL OR a.expires_at > NOW())
                       
                       ORDER BY created_at DESC";

$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("iiii", $farmer_id, $farmer_id, $farmer_id, $farmer_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// Apply filter
$filtered_notifications = [];
while ($row = $notifications_result->fetch_assoc()) {
    $include = true;
    
    if ($filter == 'unread' && $row['is_read'] == 1) {
        $include = false;
    } elseif ($filter == 'read' && $row['is_read'] == 0) {
        $include = false;
    } elseif ($filter == 'important' && $row['is_important'] == 0) {
        $include = false;
    } elseif ($filter == 'farmers' && $row['target_role'] != 'farmers') {
        $include = false;
    } elseif ($filter == 'announcements' && $row['type'] != 'announcement') {
        $include = false;
    } elseif ($filter == 'notifications' && $row['type'] != 'notification') {
        $include = false;
    } elseif ($filter == 'personal' && $row['target_role'] != 'specific') {
        $include = false;
    }
    
    if ($include) {
        $filtered_notifications[] = $row;
    }
}

// Get unread notification count (both notifications and announcements)
$unread_notif_query = "SELECT 
                       COUNT(DISTINCT CASE 
                           WHEN n.notification_id IS NOT NULL AND (n.target_roles IN ('all', 'farmers') OR (n.target_roles = 'specific' AND n.target_user_id = ?))
                           AND (n.expires_at IS NULL OR n.expires_at > NOW())
                           AND (uns.is_read IS NULL OR uns.is_read = 0)
                           THEN n.notification_id END) as notif_count,
                       COUNT(DISTINCT CASE 
                           WHEN a.announcement_id IS NOT NULL AND (a.target_roles IN ('all', 'farmers') OR (a.target_roles = 'specific' AND a.target_user_id = ?))
                           AND a.status = 'active'
                           AND (a.expires_at IS NULL OR a.expires_at > NOW())
                           AND (uas.is_read IS NULL OR uas.is_read = 0)
                           THEN a.announcement_id END) as announce_count
                       FROM (SELECT 1 as dummy) dummy
                       LEFT JOIN notifications n ON 1=0
                       LEFT JOIN user_notification_status uns ON 1=0
                       LEFT JOIN announcements a ON 1=0
                       LEFT JOIN user_announcement_status uas ON 1=0
                       UNION
                       SELECT 
                       COUNT(DISTINCT n.notification_id) as notif_count,
                       COUNT(DISTINCT a.announcement_id) as announce_count
                       FROM users u
                       LEFT JOIN notifications n ON (n.target_roles IN ('all', 'farmers') OR (n.target_roles = 'specific' AND n.target_user_id = u.user_id))
                       LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = u.user_id
                       LEFT JOIN announcements a ON (a.target_roles IN ('all', 'farmers') OR (a.target_roles = 'specific' AND a.target_user_id = u.user_id)) AND a.status = 'active'
                       LEFT JOIN user_announcement_status uas ON a.announcement_id = uas.announcement_id AND uas.user_id = u.user_id
                       WHERE u.user_id = ?
                       AND (n.expires_at IS NULL OR n.expires_at > NOW())
                       AND (a.expires_at IS NULL OR a.expires_at > NOW())
                       AND ((uns.is_read IS NULL OR uns.is_read = 0) OR (uas.is_read IS NULL OR uas.is_read = 0))";

$unread_notif_stmt = $conn->prepare($unread_notif_query);
$unread_notif_stmt->bind_param("iii", $farmer_id, $farmer_id, $farmer_id);
$unread_notif_stmt->execute();
$unread_notif_result = $unread_notif_stmt->get_result();
$unread_data = $unread_notif_result->fetch_assoc();
$unread_notif_count = ($unread_data['notif_count'] ?? 0) + ($unread_data['announce_count'] ?? 0);

// Get notification stats
$total_notifications = count($filtered_notifications);
$read_notifications = 0;
$unread_count = 0;
$important_count = 0;

foreach ($filtered_notifications as $item) {
    if ($item['is_read'] == 1) {
        $read_notifications++;
    } else {
        $unread_count++;
    }
    
    if ($item['is_important'] == 1) {
        $important_count++;
    }
}

$conn->close();
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
            --farmer-purple: #9b59b6;
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
            position: fixed;
            width: 16.66666667%;
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
        
        .main-content {
            margin-left: 16.66666667%;
            width: 83.33333333%;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
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
        
        .notification-item.announcement {
            border-left: 4px solid var(--farmer-purple);
        }
        
        .notification-item.announcement.unread {
            background: rgba(155, 89, 182, 0.05);
            border-left: 4px solid var(--farmer-purple);
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
            display: inline-block;
            margin-right: 5px;
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
        
        .badge-announcement {
            background: rgba(155, 89, 182, 0.1);
            color: var(--farmer-purple);
        }
        
        .badge-notification {
            background: rgba(39, 174, 96, 0.1);
            color: var(--farmer-green);
        }
        
        .badge-personal {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
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
            text-decoration: none;
            display: inline-block;
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
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-farmer-outline:hover {
            background: var(--farmer-green);
            color: white;
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .filter-buttons .btn {
            margin: 0;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
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
                        <a class="nav-link" href="earnings.php">
                            <i class="fas fa-wallet me-2"></i>
                            Earnings Monitor
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
            <div class="main-content">
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
                            <div class="notification-stat-label">Read</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="notification-stat-card stat-unread">
                            <div class="notification-stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $unread_count; ?></div>
                            <div class="notification-stat-label">Unread</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="notification-stat-card stat-important">
                            <div class="notification-stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-stat-value"><?php echo $important_count; ?></div>
                            <div class="notification-stat-label">Important</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="analytics-card mb-4">
                    <div class="filter-buttons">
                        <a href="?filter=all" class="btn <?php echo $filter == 'all' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-list me-1"></i>All
                        </a>
                        <a href="?filter=unread" class="btn <?php echo $filter == 'unread' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-circle me-1"></i>Unread
                        </a>
                        <a href="?filter=read" class="btn <?php echo $filter == 'read' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-check-circle me-1"></i>Read
                        </a>
                        <a href="?filter=important" class="btn <?php echo $filter == 'important' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-exclamation-circle me-1"></i>Important
                        </a>
                        <a href="?filter=notifications" class="btn <?php echo $filter == 'notifications' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-bell me-1"></i>Notifications
                        </a>
                        <a href="?filter=announcements" class="btn <?php echo $filter == 'announcements' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-bullhorn me-1"></i>Announcements
                        </a>
                        <a href="?filter=farmers" class="btn <?php echo $filter == 'farmers' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-tractor me-1"></i>Farmers Only
                        </a>
                        <a href="?filter=personal" class="btn <?php echo $filter == 'personal' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-user me-1"></i>Personal
                        </a>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn me-2" style="color: var(--farmer-brown);"></i>
                            <?php 
                            if ($filter == 'unread') echo 'Unread Notifications';
                            elseif ($filter == 'read') echo 'Read Notifications';
                            elseif ($filter == 'important') echo 'Important Notifications';
                            elseif ($filter == 'notifications') echo 'System Notifications';
                            elseif ($filter == 'announcements') echo 'Announcements';
                            elseif ($filter == 'farmers') echo 'Farmer Notifications';
                            elseif ($filter == 'personal') echo 'Personal Notifications';
                            else echo 'All Notifications & Announcements';
                            ?>
                        </h5>
                        <div>
                            <span class="badge bg-warning"><?php echo $unread_count; ?> Unread</span>
                        </div>
                    </div>
                    
                    <?php if(count($filtered_notifications) > 0): ?>
                        <div class="notifications-list">
                            <?php foreach($filtered_notifications as $notification): 
                                $is_read = $notification['is_read'] == 1;
                                $is_important = $notification['is_important'] == 1;
                                $is_expired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                                $type = $notification['type'];
                            ?>
                            <div class="notification-item <?php echo !$is_read ? 'unread' : 'read'; ?> <?php echo $is_important ? 'notification-important' : ''; ?> <?php echo $type; ?>">
                                <div class="notification-dot"></div>
                                
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <?php if($is_important): ?>
                                            <span class="notification-badge badge-important">
                                                <i class="fas fa-exclamation-circle me-1"></i>Important
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if($type == 'announcement'): ?>
                                            <span class="notification-badge badge-announcement">
                                                <i class="fas fa-bullhorn me-1"></i>Announcement
                                            </span>
                                        <?php else: ?>
                                            <span class="notification-badge badge-notification">
                                                <i class="fas fa-bell me-1"></i>Notification
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if($notification['target_role'] == 'specific'): ?>
                                            <span class="notification-badge badge-personal">
                                                <i class="fas fa-user me-1"></i>Personal
                                            </span>
                                        <?php else: ?>
                                            <span class="notification-badge badge-audience">
                                                <i class="fas fa-users me-1"></i><?php echo $notification['audience']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="notification-meta">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        <?php if($notification['expires_at']): ?>
                                            <br><small class="text-muted">Expires: <?php echo date('M j, Y', strtotime($notification['expires_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="notification-title">
                                    <i class="fas fa-<?php echo $is_important ? 'exclamation-triangle text-danger' : ($type == 'announcement' ? 'bullhorn' : 'info-circle'); ?> me-2" 
                                       style="<?php echo $type == 'announcement' ? 'color: var(--farmer-purple);' : ''; ?>"></i>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                
                                <?php if($notification['sender_name']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>From: <?php echo htmlspecialchars($notification['sender_name']); ?>
                                            (<?php echo ucfirst($notification['sender_role']); ?>)
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="notification-content">
                                    <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if(!$is_read): ?>
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
                                        <?php if($notification['read_at']): ?>
                                            <small class="text-success">
                                                <i class="fas fa-check me-1"></i>Read on <?php echo date('M j, Y g:i A', strtotime($notification['read_at'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if($is_expired): ?>
                                        <span class="badge bg-secondary ms-2">
                                            <i class="fas fa-clock me-1"></i>Expired
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h4 class="text-muted mb-3">No notifications found</h4>
                            <p class="text-muted mb-4">
                                <?php if($filter != 'all'): ?>
                                    No notifications match your current filter. Try a different filter.
                                <?php else: ?>
                                    You're all caught up! Check back later for updates.
                                <?php endif; ?>
                            </p>
                            <?php if($filter != 'all'): ?>
                                <a href="notifications.php" class="btn btn-farmer">
                                    <i class="fas fa-redo me-2"></i>View All
                                </a>
                            <?php endif; ?>
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
                                SpiceCeylon Farmer Panel • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-bell me-1"></i> 
                                <span class="text-warning"><?php echo $unread_count; ?> unread</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-tractor me-1"></i> 
                                <?php echo htmlspecialchars($farmer['name']); ?>
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
        // Mark notification as read when clicked
        $('.notification-item.unread').on('click', function(e) {
            if (!$(e.target).closest('a').length && !$(e.target).closest('button').length) {
                const notificationLink = $(this).find('a[href*="mark_read="]').attr('href');
                const announcementLink = $(this).find('a[href*="mark_announce_read="]').attr('href');
                const markLink = notificationLink || announcementLink;
                
                if (markLink) {
                    window.location.href = markLink;
                }
            }
        });
    </script>
</body>
</html>