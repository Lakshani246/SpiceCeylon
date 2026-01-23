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

// Get assigned requests count
$assigned_requests_query = $conn->prepare("SELECT COUNT(*) as count FROM product_requests WHERE assigned_farmer_id = ? AND status IN ('Approved', 'Pending')");
$assigned_requests_query->bind_param("i", $farmer_id);
$assigned_requests_query->execute();
$assigned_requests_result = $assigned_requests_query->get_result();
$pending_requests = $assigned_requests_result->fetch_assoc()['count'];

// Get unread message count
$unread_query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND receiver_role = 'farmer' AND is_read = FALSE";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $farmer_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];

// Check for new assignments from admin
if (isset($_GET['check_assignments'])) {
    $new_assignments_query = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM product_requests 
        WHERE assigned_farmer_id = ? 
        AND status = 'Approved' 
        AND DATE(updated_at) = CURDATE()
    ");
    $new_assignments_query->bind_param("i", $farmer_id);
    $new_assignments_query->execute();
    $new_assignments_result = $new_assignments_query->get_result();
    $new_assignments_today = $new_assignments_result->fetch_assoc()['count'];
    
    echo json_encode(['new_assignments' => $new_assignments_today]);
    exit();
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'] ?? 1; // Default to admin
    $receiver_role = $_POST['receiver_role'] ?? 'admin';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $related_order_id = $_POST['related_order_id'] ?? null;
    $related_product_id = $_POST['related_product_id'] ?? null;
    
    if (!empty($message) && !empty($subject)) {
        $insert_query = "INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message, related_order_id, related_product_id) 
                         VALUES (?, 'farmer', ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iisssii", $farmer_id, $receiver_id, $receiver_role, $subject, $message, $related_order_id, $related_product_id);
        
        if ($insert_stmt->execute()) {
            $success_message = "Message sent successfully!";
        } else {
            $error_message = "Failed to send message. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Mark message as read if viewing
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $message_id = $_GET['read'];
    $mark_read_query = "UPDATE messages SET is_read = TRUE WHERE id = ? AND receiver_id = ? AND receiver_role = 'farmer'";
    $mark_read_stmt = $conn->prepare($mark_read_query);
    $mark_read_stmt->bind_param("ii", $message_id, $farmer_id);
    $mark_read_stmt->execute();
}

// Handle delete message
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = $_GET['delete'];
    $delete_query = "DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("iii", $message_id, $farmer_id, $farmer_id);
    $delete_stmt->execute();
    
    header('Location: messages.php');
    exit();
}

// Get all messages for this farmer (both sent and received)
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
                   WHERE (m.sender_id = ? AND m.sender_role = 'farmer') 
                   OR (m.receiver_id = ? AND m.receiver_role = 'farmer')
                   ORDER BY m.created_at DESC";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->bind_param("ii", $farmer_id, $farmer_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

// Get recent orders for quick reference
$recent_orders_query = "SELECT DISTINCT o.order_id, o.status 
                       FROM orders o 
                       JOIN order_items oi ON o.order_id = oi.order_id 
                       JOIN products p ON oi.product_id = p.product_id 
                       WHERE p.farmer_id = ? 
                       ORDER BY o.created_at DESC 
                       LIMIT 5";
$recent_orders_stmt = $conn->prepare($recent_orders_query);
$recent_orders_stmt->bind_param("i", $farmer_id);
$recent_orders_stmt->execute();
$recent_orders_result = $recent_orders_stmt->get_result();

// Get farmer's products for reference
$farmer_products = $conn->query("SELECT product_id, name FROM products WHERE farmer_id = $farmer_id LIMIT 10");

// Get newly assigned requests from admin
$assigned_requests_query = "
    SELECT pr.*, 
           u.name as customer_name,
           u.email as customer_email,
           pr.created_at as request_date,
           pr.updated_at as assigned_date
    FROM product_requests pr
    JOIN users u ON pr.customer_id = u.user_id
    WHERE pr.assigned_farmer_id = ? 
    AND pr.status IN ('Approved', 'Pending')
    ORDER BY pr.updated_at DESC
    LIMIT 5
";
$assigned_stmt = $conn->prepare($assigned_requests_query);
$assigned_stmt->bind_param("i", $farmer_id);
$assigned_stmt->execute();
$assigned_requests = $assigned_stmt->get_result();
$total_assigned_requests = $assigned_requests->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Farmer Dashboard</title>
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
        
        .farmer-profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--farmer-green);
            margin: 0 auto 20px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--farmer-green);
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
            border-left: 4px solid var(--farmer-blue);
        }
        
        .message-item.read {
            background: white;
            border-left: 4px solid #ddd;
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--farmer-dark);
            margin-bottom: 5px;
        }
        
        .message-receiver {
            font-size: 0.9rem;
            color: #666;
        }
        
        .message-subject {
            font-weight: 600;
            color: var(--farmer-green);
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
            color: var(--farmer-green);
        }
        
        .badge-outgoing {
            background: rgba(139, 69, 19, 0.1);
            color: var(--farmer-brown);
        }
        
        /* Assigned Request Card */
        .assigned-request-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
            border-left: 4px solid var(--farmer-gold);
        }
        
        .assigned-request-card:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.1);
            transform: translateY(-2px);
        }
        
        .assigned-request-card.new {
            border-left: 4px solid var(--farmer-green);
            background: rgba(39, 174, 96, 0.05);
        }
        
        .request-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-assigned {
            background: rgba(52, 152, 219, 0.1);
            color: var(--farmer-blue);
        }
        
        .badge-urgent {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
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
            
            .message-item {
                padding: 15px;
            }
            
            .assigned-request-card {
                padding: 12px;
            }
        }
        
        /* Message Stats */
        .message-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .message-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-total .message-stat-icon { background: rgba(139, 69, 19, 0.1); color: var(--farmer-brown); }
        .stat-incoming .message-stat-icon { background: rgba(39, 174, 96, 0.1); color: var(--farmer-green); }
        .stat-outgoing .message-stat-icon { background: rgba(52, 152, 219, 0.1); color: var(--farmer-blue); }
        .stat-unread .message-stat-icon { background: rgba(243, 156, 18, 0.1); color: var(--farmer-gold); }
        .stat-assigned .message-stat-icon { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }
        
        .message-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--farmer-dark);
            margin-bottom: 5px;
        }
        
        .message-stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Notification Alert */
        .new-assignment-alert {
            animation: pulse 2s infinite;
            border-left: 4px solid var(--farmer-green);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(39, 174, 96, 0); }
            100% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0); }
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
                        <a class="nav-link active" href="messages.php">
                            <i class="fas fa-envelope me-2"></i>
                            Messages
                            <?php if($unread_count > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
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
                                <i class="fas fa-envelope me-2" style="color: var(--farmer-green);"></i>
                                Messages & Assignments
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Communicate with customers and admins. View requests assigned by admin.
                            </p>
                        </div>
                        <div>
                            <button class="btn btn-farmer" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i>
                                New Message
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Message Stats -->
                <div class="row mb-4">
                    <?php
                    // Calculate message stats
                    $total_messages = $messages_result->num_rows;
                    $incoming_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = $farmer_id AND receiver_role = 'farmer'")->fetch_assoc()['count'];
                    $outgoing_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE sender_id = $farmer_id AND sender_role = 'farmer'")->fetch_assoc()['count'];
                    
                    // Get today's new assignments
                    $new_assignments_today = $conn->prepare("
                        SELECT COUNT(*) as count 
                        FROM product_requests 
                        WHERE assigned_farmer_id = ? 
                        AND status = 'Approved' 
                        AND DATE(updated_at) = CURDATE()
                    ");
                    $new_assignments_today->bind_param("i", $farmer_id);
                    $new_assignments_today->execute();
                    $new_assignments_result = $new_assignments_today->get_result();
                    $new_assignments_count = $new_assignments_result->fetch_assoc()['count'];
                    ?>
                    <div class="col-md-3">
                        <div class="message-stat-card stat-assigned">
                            <div class="message-stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="message-stat-value"><?php echo $pending_requests; ?></div>
                            <div class="message-stat-label">Assigned Requests</div>
                            <?php if($new_assignments_count > 0): ?>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i><?php echo $new_assignments_count; ?> new today
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="message-stat-card stat-total">
                            <div class="message-stat-icon">
                                <i class="fas fa-envelope-open-text"></i>
                            </div>
                            <div class="message-stat-value"><?php echo $total_messages; ?></div>
                            <div class="message-stat-label">Total Messages</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="message-stat-card stat-incoming">
                            <div class="message-stat-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <div class="message-stat-value"><?php echo $incoming_messages; ?></div>
                            <div class="message-stat-label">Incoming</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="message-stat-card stat-outgoing">
                            <div class="message-stat-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="message-stat-value"><?php echo $outgoing_messages; ?></div>
                            <div class="message-stat-label">Sent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="message-stat-card stat-unread">
                            <div class="message-stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="message-stat-value"><?php echo $unread_count; ?></div>
                            <div class="message-stat-label">Unread Messages</div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Requests from Admin -->
                <div class="analytics-card mb-4 new-assignment-alert" id="assignmentsSection">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2" style="color: var(--farmer-green);"></i>
                            Requests Assigned by Admin
                        </h5>
                        <div>
                            <span class="badge bg-warning me-2" id="newAssignmentsBadge" style="display: none;">
                                <i class="fas fa-star me-1"></i>New!
                            </span>
                            <a href="customer_requests.php" class="btn btn-sm btn-farmer">
                                <i class="fas fa-external-link-alt me-1"></i>Manage Requests
                            </a>
                        </div>
                    </div>
                    
                    <?php if($assigned_requests->num_rows > 0): ?>
                        <div class="assigned-requests-list">
                            <?php 
                            $assigned_requests->data_seek(0);
                            $counter = 0;
                            while($req = $assigned_requests->fetch_assoc()): 
                                $is_new = date('Y-m-d', strtotime($req['assigned_date'])) == date('Y-m-d');
                                $counter++;
                            ?>
                            <div class="assigned-request-card <?php echo $is_new ? 'new' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-start mb-2">
                                            <div class="me-3">
                                                <div style="width: 40px; height: 40px; background: <?php echo $is_new ? 'rgba(39, 174, 96, 0.1)' : 'rgba(243, 156, 18, 0.1)'; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-box" style="color: <?php echo $is_new ? 'var(--farmer-green)' : 'var(--farmer-gold)'; ?>;"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($req['product_name']); ?></h6>
                                                <div class="small text-muted mb-2">
                                                    <i class="fas fa-user me-1"></i>
                                                    Customer: <?php echo htmlspecialchars($req['customer_name']); ?>
                                                    <?php if(isset($req['customer_email'])): ?>
                                                        <span class="ms-2">
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <?php echo htmlspecialchars($req['customer_email']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <span class="badge bg-<?php echo $req['status'] == 'Approved' ? 'success' : 'warning'; ?>">
                                                        <i class="fas fa-<?php echo $req['status'] == 'Approved' ? 'check' : 'clock'; ?> me-1"></i>
                                                        <?php echo $req['status']; ?>
                                                    </span>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-hashtag me-1"></i>
                                                        Qty: <?php echo $req['quantity_requested']; ?>
                                                    </span>
                                                    <?php if(isset($req['urgency']) && $req['urgency'] == 'High'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        High Priority
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if($req['description']): ?>
                                                <p class="small text-muted mb-0">
                                                    <i class="fas fa-comment me-1"></i>
                                                    <?php echo substr(htmlspecialchars($req['description']), 0, 120); ?>
                                                    <?php if(strlen($req['description']) > 120): ?>...<?php endif; ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end ms-3">
                                        <div class="mb-2">
                                            <?php if($is_new): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-star me-1"></i>NEW
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group">
                                            <a href="customer_requests.php?view=<?php echo $req['request_id']; ?>" 
                                               class="btn btn-sm btn-farmer">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            <a href="messages.php?reply_to_customer=<?php echo $req['customer_id']; ?>&request=<?php echo $req['request_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="modal" 
                                               data-bs-target="#replyToCustomerModal"
                                               data-customer-id="<?php echo $req['customer_id']; ?>"
                                               data-customer-name="<?php echo htmlspecialchars($req['customer_name']); ?>"
                                               data-product-name="<?php echo htmlspecialchars($req['product_name']); ?>">
                                                <i class="fas fa-reply me-1"></i>Reply
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="small text-muted mt-2 pt-2 border-top">
                                    <i class="fas fa-calendar me-1"></i>
                                    Requested: <?php echo date('M d, Y', strtotime($req['request_date'])); ?>
                                    <?php if($req['assigned_date']): ?>
                                        | <i class="fas fa-user-tie ms-2 me-1"></i>
                                        Assigned: <?php echo date('M d, Y h:i A', strtotime($req['assigned_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="customer_requests.php" class="btn btn-farmer">
                                <i class="fas fa-inbox me-1"></i>View All Assigned Requests (<?php echo $pending_requests; ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Requests Assigned Yet</h5>
                            <p class="text-muted">You haven't been assigned any requests from admin yet.</p>
                            <a href="customer_requests.php" class="btn btn-farmer-outline">
                                <i class="fas fa-sync-alt me-1"></i>Check for New Requests
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Messages List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-comments me-2" style="color: var(--farmer-green);"></i>
                            Messages
                        </h5>
                        <div>
                            <div class="btn-group" role="group">
                                <a href="?filter=all" class="btn btn-sm <?php echo !isset($_GET['filter']) || $_GET['filter'] == 'all' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">All</a>
                                <a href="?filter=unread" class="btn btn-sm <?php echo isset($_GET['filter']) && $_GET['filter'] == 'unread' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">Unread</a>
                                <a href="?filter=sent" class="btn btn-sm <?php echo isset($_GET['filter']) && $_GET['filter'] == 'sent' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">Sent</a>
                                <a href="?filter=received" class="btn btn-sm <?php echo isset($_GET['filter']) && $_GET['filter'] == 'received' ? 'btn-farmer' : 'btn-outline-secondary'; ?>">Received</a>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($messages_result->num_rows > 0): ?>
                        <div class="messages-list">
                            <?php 
                            $messages_result->data_seek(0);
                            while($message = $messages_result->fetch_assoc()): 
                                $is_sent = $message['sender_id'] == $farmer_id;
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
                                            <span class="message-badge" style="background: rgba(52, 152, 219, 0.1); color: var(--farmer-blue);">
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
                                    <strong><?php echo $is_sent ? 'To: ' . htmlspecialchars($message['receiver_name'] ?? 'Admin') : 'From: ' . htmlspecialchars($message['sender_name'] ?? 'Customer'); ?></strong>
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
                                        <a href="?read=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check me-1"></i>Mark as Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="messages.php?reply=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#replyModal"
                                       data-subject="Re: <?php echo htmlspecialchars($message['subject']); ?>"
                                       data-receiver-id="<?php echo $is_sent ? $message['receiver_id'] : $message['sender_id']; ?>"
                                       data-receiver-role="<?php echo $is_sent ? $message['receiver_role'] : $message['sender_role']; ?>">
                                        <i class="fas fa-reply me-1"></i>Reply
                                    </a>
                                    
                                    <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this message?')">
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
                            <p class="text-muted mb-4">Start a conversation with admin team or customers</p>
                            <button class="btn btn-farmer" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i>Send Your First Message
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--farmer-green); color: white;">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>New Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">To</label>
                            <select class="form-select" name="receiver_role" id="receiverRole" required>
                                <option value="admin">Admin Team</option>
                                <option value="customer">Customer</option>
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
                                <select class="form-select" name="related_product_id">
                                    <option value="">-- Select Product --</option>
                                    <?php while($product = $farmer_products->fetch_assoc()): ?>
                                        <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="6" required placeholder="Type your message here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-farmer">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--farmer-blue); color: white;">
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
                        <button type="submit" name="send_message" class="btn btn-farmer">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reply to Customer Modal -->
    <div class="modal fade" id="replyToCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--farmer-brown); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Reply to Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="receiver_id" id="reply_customer_id">
                        <input type="hidden" name="receiver_role" value="customer">
                        
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <input type="text" class="form-control" id="reply_customer_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Related Request/Product</label>
                            <input type="text" class="form-control" id="reply_product_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="reply_customer_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="6" required placeholder="Type your message to the customer..."></textarea>
                            <div class="form-text">You can discuss product details, availability, pricing, or delivery options.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-farmer">Send to Customer</button>
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

        // Handle reply to customer modal
        const replyToCustomerModal = document.getElementById('replyToCustomerModal');
        if (replyToCustomerModal) {
            replyToCustomerModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const customerId = button.getAttribute('data-customer-id');
                const customerName = button.getAttribute('data-customer-name');
                const productName = button.getAttribute('data-product-name');
                
                document.getElementById('reply_customer_id').value = customerId;
                document.getElementById('reply_customer_name').value = customerName;
                document.getElementById('reply_product_name').value = productName;
                document.getElementById('reply_customer_subject').value = "Regarding your request: " + productName;
            });
        }

        // Auto-refresh for new assignments and messages
        function checkForNewAssignments() {
            $.ajax({
                url: 'messages.php?check_assignments=1',
                method: 'GET',
                success: function(data) {
                    try {
                        const response = JSON.parse(data);
                        if (response.new_assignments > 0) {
                            // Show new assignments badge
                            $('#newAssignmentsBadge').show().html(`<i class="fas fa-star me-1"></i>${response.new_assignments} New`);
                            
                            // Add pulse animation
                            $('#assignmentsSection').addClass('new-assignment-alert');
                            
                            // Update sidebar badge
                            updateRequestsBadge();
                        }
                    } catch (e) {
                        console.error('Error parsing assignment data:', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking assignments:', error);
                }
            });
        }

        function updateRequestsBadge() {
            $.ajax({
                url: '../actions/get_farmer_stats.php',
                method: 'GET',
                data: { farmer_id: <?php echo $farmer_id; ?> },
                success: function(data) {
                    try {
                        const response = JSON.parse(data);
                        const badge = document.querySelector('.nav-link[href="customer_requests.php"] .badge');
                        if (response.assigned_requests > 0) {
                            if (badge) {
                                badge.textContent = response.assigned_requests;
                            } else {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'badge bg-warning float-end';
                                newBadge.textContent = response.assigned_requests;
                                document.querySelector('.nav-link[href="customer_requests.php"]').appendChild(newBadge);
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                        
                        // Update message badge
                        const msgBadge = document.querySelector('.nav-link[href="messages.php"] .badge');
                        if (response.unread_messages > 0) {
                            if (msgBadge) {
                                msgBadge.textContent = response.unread_messages;
                            } else {
                                const newMsgBadge = document.createElement('span');
                                newMsgBadge.className = 'badge bg-danger float-end';
                                newMsgBadge.textContent = response.unread_messages;
                                document.querySelector('.nav-link[href="messages.php"]').appendChild(newMsgBadge);
                            }
                        } else if (msgBadge) {
                            msgBadge.remove();
                        }
                    } catch (e) {
                        console.error('Error parsing stats data:', e);
                    }
                }
            });
        }

        // Check for updates every 30 seconds
        setInterval(function() {
            checkForNewAssignments();
            updateRequestsBadge();
        }, 30000);

        // Initial check on page load
        $(document).ready(function() {
            checkForNewAssignments();
            updateRequestsBadge();
        });
    </script>
</body>
</html>