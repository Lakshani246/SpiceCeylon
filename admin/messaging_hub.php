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

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $receiver_role = $_POST['receiver_role'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $related_order_id = !empty($_POST['related_order_id']) ? $_POST['related_order_id'] : NULL;
    $related_product_id = !empty($_POST['related_product_id']) ? $_POST['related_product_id'] : NULL;
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message, related_order_id, related_product_id) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssii", $admin_id, $receiver_id, $receiver_role, $subject, $message, $related_order_id, $related_product_id);
    
    if ($stmt->execute()) {
        $success_msg = "Message sent successfully!";
    } else {
        $error_msg = "Failed to send message: " . $conn->error;
    }
}

// Handle marking message as read
if (isset($_GET['mark_read'])) {
    $message_id = $_GET['mark_read'];
    $conn->query("UPDATE messages SET is_read = TRUE WHERE id = $message_id");
    header("Location: messaging_hub.php");
    exit();
}

// Handle replying to message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_message'])) {
    $original_message_id = $_POST['original_message_id'];
    $reply_message = $_POST['reply_message_text'];
    
    // Get original message details
    $original = $conn->query("SELECT * FROM messages WHERE id = $original_message_id")->fetch_assoc();
    
    if ($original) {
        $subject = "Re: " . $original['subject'];
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, subject, message, related_order_id, related_product_id) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssii", $admin_id, $original['sender_id'], $original['sender_role'], $subject, $reply_message, $original['related_order_id'], $original['related_product_id']);
        
        if ($stmt->execute()) {
            // Mark original as read
            $conn->query("UPDATE messages SET is_read = TRUE WHERE id = $original_message_id");
            $success_msg = "Reply sent successfully!";
        }
    }
}

// Handle deleting message
if (isset($_GET['delete'])) {
    $message_id = $_GET['delete'];
    $conn->query("DELETE FROM messages WHERE id = $message_id");
    $success_msg = "Message deleted successfully!";
    header("Location: messaging_hub.php");
    exit();
}

// Get unread messages count
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_role = 'admin' AND is_read = FALSE")->fetch_assoc()['count'];

// Get users for dropdown
$customers = $conn->query("SELECT user_id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name");
$farmers = $conn->query("SELECT user_id, name, email FROM users WHERE role = 'farmer' AND status = 'active' ORDER BY name");

// Get recent orders for dropdown
$recent_orders = $conn->query("SELECT order_id, customer_id, final_total, status FROM orders ORDER BY order_id DESC LIMIT 10");

// Get today's date for display
$today = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging Center - SpiceCeylon Admin</title>
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
            --spice-teal: #1abc9c;
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
            border-left: 5px solid var(--spice-teal);
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
        
        .message-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .message-card:hover {
            box-shadow: 0 4px 12px rgba(26, 188, 156, 0.1);
            border-left: 4px solid var(--spice-teal);
        }
        
        .message-card.unread {
            background: #f8fdff;
            border-left: 4px solid var(--spice-blue);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.1);
        }
        
        .message-card.sent {
            background: #f8fff9;
            border-left: 4px solid var(--spice-green);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--spice-teal), #16a085);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .btn-message {
            background: linear-gradient(135deg, var(--spice-teal), #16a085);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(26, 188, 156, 0.3);
            color: white;
        }
        
        .tab-content {
            border: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tabs .nav-link.active {
            background: white;
            color: var(--spice-teal);
            border-bottom: 3px solid var(--spice-teal);
        }
        
        .message-subject {
            font-weight: 600;
            color: var(--spice-dark);
            font-size: 1.05rem;
        }
        
        .message-preview {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .time-ago {
            font-size: 0.8rem;
            color: #adb5bd;
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
        
        .message-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .reply-form {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(184, 92, 56, 0.04);
        }
        
        .badge-received {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
        }
        
        .badge-sent {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
        }
        
        .quick-action-btn {
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: var(--spice-dark);
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(184, 92, 56, 0.15);
            background: var(--spice-red);
            color: white;
            text-decoration: none;
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
                <!-- Header - Same as dashboard -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-comments me-2" style="color: var(--spice-teal);"></i>
                                Messaging Center
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Welcome back, <strong><?php echo htmlspecialchars($admin['username']); ?></strong>! 
                                Communicate with customers and farmers.
                                <?php if($unread_count > 0): ?>
                                    <span class="text-warning fw-bold">You have <?php echo $unread_count; ?> unread message(s).</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-teal), #16a085); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(26, 188, 156, 0.3);" class="time-display">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo $today; ?>
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock me-1"></i> <?php echo $current_time; ?>
                        </div>
                    </div>
                </div>

                <?php if(isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold fs-4"><?php echo $unread_count; ?></div>
                                    <div class="text-muted">Unread Messages</div>
                                </div>
                                <div class="display-6 opacity-50" style="color: var(--spice-teal);">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <?php 
                        $total_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE receiver_role = 'admin' OR sender_role = 'admin'")->fetch_assoc()['count'];
                        ?>
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold fs-4"><?php echo $total_messages; ?></div>
                                    <div class="text-muted">Total Messages</div>
                                </div>
                                <div class="display-6 opacity-50" style="color: var(--spice-blue);">
                                    <i class="fas fa-comments"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <?php 
                        $today_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE() AND (receiver_role = 'admin' OR sender_role = 'admin')")->fetch_assoc()['count'];
                        ?>
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold fs-4"><?php echo $today_messages; ?></div>
                                    <div class="text-muted">Today's Messages</div>
                                </div>
                                <div class="display-6 opacity-50" style="color: var(--spice-green);">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <?php 
                        $total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer' AND status='active'")->fetch_assoc()['count'];
                        $total_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='active'")->fetch_assoc()['count'];
                        ?>
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold fs-4"><?php echo $total_customers + $total_farmers; ?></div>
                                    <div class="text-muted">Active Users</div>
                                    <small class="text-muted">
                                        <?php echo $total_customers; ?> customers, <?php echo $total_farmers; ?> farmers
                                    </small>
                                </div>
                                <div class="display-6 opacity-50" style="color: var(--spice-purple);">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="analytics-card">
                            <ul class="nav nav-tabs mb-4" id="messagingTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" type="button" role="tab">
                                        <i class="fas fa-inbox me-2"></i>Inbox
                                        <?php if($unread_count > 0): ?>
                                            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                                        <i class="fas fa-paper-plane me-2"></i>Sent Messages
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="compose-tab" data-bs-toggle="tab" data-bs-target="#compose" type="button" role="tab">
                                        <i class="fas fa-edit me-2"></i>Compose New
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="messagingTabsContent">
                                
                                <!-- Inbox Tab -->
                                <div class="tab-pane fade show active" id="inbox" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="mb-0">
                                            <i class="fas fa-envelope me-2"></i>Received Messages
                                        </h5>
                                        <div class="text-muted small">
                                            <i class="fas fa-filter me-1"></i> Messages from customers and farmers
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    $inbox_messages = $conn->query("
                                        SELECT m.*, sender.name as sender_name, sender.role as sender_role
                                        FROM messages m
                                        JOIN users sender ON m.sender_id = sender.user_id
                                        WHERE m.receiver_role = 'admin'
                                        ORDER BY m.is_read ASC, m.created_at DESC
                                    ");
                                    
                                    if($inbox_messages->num_rows > 0): 
                                        while($msg = $inbox_messages->fetch_assoc()): 
                                    ?>
                                    <div class="message-card <?php echo !$msg['is_read'] ? 'unread' : ''; ?>" id="message-<?php echo $msg['id']; ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="d-flex align-items-center mb-1">
                                                    <div class="message-subject me-3">
                                                        <?php echo htmlspecialchars($msg['subject']); ?>
                                                    </div>
                                                    <?php if(!$msg['is_read']): ?>
                                                        <span class="badge bg-danger">New</span>
                                                    <?php endif; ?>
                                                    <span class="badge ms-2 <?php echo $msg['sender_role'] == 'customer' ? 'bg-primary' : 'bg-success'; ?>">
                                                        <?php echo ucfirst($msg['sender_role']); ?>
                                                    </span>
                                                </div>
                                                <div class="message-preview mb-1">
                                                    <?php echo htmlspecialchars(substr($msg['message'], 0, 150)); ?>...
                                                </div>
                                                <div class="time-ago">
                                                    <i class="far fa-clock me-1"></i> <?php echo time_ago($msg['created_at']); ?>
                                                    <span class="mx-2">•</span>
                                                    From: <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group">
                                                    <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                    <a href="?reply=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-reply me-1"></i> Reply
                                                    </a>
                                                    <a href="?mark_read=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-check me-1"></i> Mark Read
                                                    </a>
                                                    <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?');">
                                                        <i class="fas fa-trash me-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">No messages yet</h5>
                                        <p class="text-muted mb-4">
                                            Your inbox is empty. Customers and farmers can message you from their dashboards.
                                        </p>
                                        <button class="btn btn-primary" data-bs-toggle="tab" data-bs-target="#compose">
                                            <i class="fas fa-edit me-2"></i> Send First Message
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Sent Messages Tab -->
                                <div class="tab-pane fade" id="sent" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="mb-0">
                                            <i class="fas fa-paper-plane me-2"></i>Sent Messages
                                        </h5>
                                        <div class="text-muted small">
                                            <i class="fas fa-history me-1"></i> All messages you've sent
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    $sent_messages = $conn->query("
                                        SELECT m.*, receiver.name as receiver_name, receiver.role as receiver_role
                                        FROM messages m
                                        JOIN users receiver ON m.receiver_id = receiver.user_id
                                        WHERE m.sender_role = 'admin'
                                        ORDER BY m.created_at DESC
                                    ");
                                    
                                    if($sent_messages->num_rows > 0): 
                                        while($msg = $sent_messages->fetch_assoc()): 
                                    ?>
                                    <div class="message-card sent">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <div class="user-avatar" style="background: linear-gradient(135deg, var(--spice-green), #27ae60);">
                                                    <?php echo strtoupper(substr($msg['receiver_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center mb-1">
                                                    <div class="message-subject me-3">
                                                        <?php echo htmlspecialchars($msg['subject']); ?>
                                                    </div>
                                                    <span class="badge bg-success ms-2">Sent</span>
                                                    <span class="badge ms-2 <?php echo $msg['receiver_role'] == 'customer' ? 'bg-primary' : 'bg-success'; ?>">
                                                        To: <?php echo ucfirst($msg['receiver_role']); ?>
                                                    </span>
                                                </div>
                                                <div class="message-preview mb-1">
                                                    <?php echo htmlspecialchars(substr($msg['message'], 0, 150)); ?>...
                                                </div>
                                                <div class="time-ago">
                                                    <i class="far fa-clock me-1"></i> <?php echo time_ago($msg['created_at']); ?>
                                                    <span class="mx-2">•</span>
                                                    To: <?php echo htmlspecialchars($msg['receiver_name']); ?>
                                                    <?php if($msg['is_read']): ?>
                                                        <span class="mx-2">•</span>
                                                        <span class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i> Read
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="mx-2">•</span>
                                                        <span class="text-warning">
                                                            <i class="fas fa-clock me-1"></i> Unread
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                                <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?');">
                                                    <i class="fas fa-trash me-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">No sent messages</h5>
                                        <p class="text-muted mb-4">
                                            You haven't sent any messages yet.
                                        </p>
                                        <button class="btn btn-primary" data-bs-toggle="tab" data-bs-target="#compose">
                                            <i class="fas fa-edit me-2"></i> Send Your First Message
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Compose Tab -->
                                <div class="tab-pane fade" id="compose" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="mb-4">
                                                <i class="fas fa-edit me-2"></i>Compose New Message
                                            </h5>
                                            
                                            <form method="POST" action="">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Recipient Type</label>
                                                        <select class="form-select" id="recipientType" name="receiver_role" required>
                                                            <option value="">Select recipient type</option>
                                                            <option value="customer">Customer</option>
                                                            <option value="farmer">Farmer</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Recipient</label>
                                                        <select class="form-select" id="recipientSelect" name="receiver_id" required>
                                                            <option value="">Select recipient</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Subject</label>
                                                    <input type="text" class="form-control" name="subject" placeholder="Message subject" required>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Related Order (Optional)</label>
                                                        <select class="form-select" name="related_order_id">
                                                            <option value="">Select order</option>
                                                            <?php 
                                                            $recent_orders->data_seek(0);
                                                            while($order = $recent_orders->fetch_assoc()): ?>
                                                            <option value="<?php echo $order['order_id']; ?>">
                                                                Order #<?php echo $order['order_id']; ?> - Rs. <?php echo number_format($order['final_total'], 2); ?> (<?php echo $order['status']; ?>)
                                                            </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Related Product (Optional)</label>
                                                        <select class="form-select" name="related_product_id">
                                                            <option value="">Select product</option>
                                                            <?php 
                                                            $products = $conn->query("SELECT product_id, name FROM products WHERE status='Approved' ORDER BY name LIMIT 20");
                                                            while($product = $products->fetch_assoc()): ?>
                                                            <option value="<?php echo $product['product_id']; ?>">
                                                                <?php echo htmlspecialchars($product['name']); ?>
                                                            </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Message</label>
                                                    <textarea class="form-control" name="message" rows="8" placeholder="Type your message here..." required></textarea>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                                        <i class="fas fa-times me-2"></i> Clear
                                                    </button>
                                                    <button type="submit" name="send_message" class="btn btn-message">
                                                        <i class="fas fa-paper-plane me-2"></i> Send Message
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="analytics-card">
                                                <h6 class="mb-3">
                                                    <i class="fas fa-lightbulb me-2"></i>Quick Tips
                                                </h6>
                                                <div class="alert alert-info mb-3">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Best Practices:</strong>
                                                    <ul class="mb-0 mt-2 small">
                                                        <li>Be clear and specific in your subject</li>
                                                        <li>Keep messages concise and focused</li>
                                                        <li>Use proper greetings and closings</li>
                                                        <li>Check spelling and grammar</li>
                                                    </ul>
                                                </div>
                                                
                                                <div class="alert alert-warning mb-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Important:</strong>
                                                    <ul class="mb-0 mt-2 small">
                                                        <li>Never share sensitive information</li>
                                                        <li>Be professional at all times</li>
                                                        <li>Respond within 24 hours</li>
                                                        <li>Keep records of important conversations</li>
                                                    </ul>
                                                </div>
                                                
                                                <div class="text-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-history me-1"></i>
                                                        Messages are stored permanently in the database.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- View Single Message (if requested) -->
                <?php if(isset($_GET['view'])): 
                    $message_id = $_GET['view'];
                    $message = $conn->query("
                        SELECT m.*, 
                               sender.name as sender_name, sender.role as sender_role,
                               receiver.name as receiver_name, receiver.role as receiver_role
                        FROM messages m
                        LEFT JOIN users sender ON m.sender_id = sender.user_id
                        LEFT JOIN users receiver ON m.receiver_id = receiver.user_id
                        WHERE m.id = $message_id
                    ")->fetch_assoc();
                    
                    if($message):
                        // Mark as read
                        $conn->query("UPDATE messages SET is_read = TRUE WHERE id = $message_id");
                ?>
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-envelope-open me-2"></i>Message Details
                            </h5>
                            <p class="text-muted mb-0 small">
                                Message ID: #<?php echo $message['id']; ?>
                            </p>
                        </div>
                        <div>
                            <a href="messaging_hub.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> Back to Inbox
                            </a>
                        </div>
                    </div>
                    
                    <div class="message-detail">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($message['sender_name']); ?></div>
                                        <small class="text-muted">
                                            <span class="badge <?php echo $message['sender_role'] == 'customer' ? 'bg-primary' : ($message['sender_role'] == 'farmer' ? 'bg-success' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($message['sender_role']); ?>
                                            </span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-end">
                                    <div class="fw-bold text-muted small">Received</div>
                                    <div><?php echo date('F j, Y g:i A', strtotime($message['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">Subject: <?php echo htmlspecialchars($message['subject']); ?></h6>
                            <div class="bg-white p-4 rounded border">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reply Form -->
                    <div class="reply-form">
                        <h6 class="mb-3">
                            <i class="fas fa-reply me-2"></i>Reply to this Message
                        </h6>
                        <form method="POST" action="">
                            <input type="hidden" name="original_message_id" value="<?php echo $message['id']; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="reply_message_text" rows="4" placeholder="Type your reply here..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="reply_message" class="btn btn-message">
                                    <i class="fas fa-paper-plane me-2"></i> Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; endif; ?>
                
                <!-- Quick Reply (if requested) -->
                <?php if(isset($_GET['reply'])): 
                    $message_id = $_GET['reply'];
                    $message = $conn->query("SELECT * FROM messages WHERE id = $message_id")->fetch_assoc();
                    
                    if($message):
                ?>
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-reply me-2"></i>Quick Reply
                        </h5>
                        <a href="messaging_hub.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="original_message_id" value="<?php echo $message['id']; ?>">
                        
                        <div class="alert alert-info mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="fw-bold">Replying to:</div>
                                    <div class="small"><?php echo htmlspecialchars($message['subject']); ?></div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="text-muted small">Received: <?php echo date('M j, Y', strtotime($message['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Reply</label>
                            <textarea class="form-control" name="reply_message_text" rows="6" placeholder="Type your reply here..." required></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="messaging_hub.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Inbox
                            </a>
                            <button type="submit" name="reply_message" class="btn btn-message">
                                <i class="fas fa-paper-plane me-2"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate recipient dropdown based on type
        $(document).ready(function() {
            // User data from PHP
            var users = {
                customer: [
                    <?php 
                    $customers->data_seek(0);
                    while($customer = $customers->fetch_assoc()): 
                    ?>
                    {id: <?php echo $customer['user_id']; ?>, name: "<?php echo addslashes($customer['name']); ?>", email: "<?php echo $customer['email']; ?>"},
                    <?php endwhile; ?>
                ],
                farmer: [
                    <?php 
                    $farmers->data_seek(0);
                    while($farmer = $farmers->fetch_assoc()): 
                    ?>
                    {id: <?php echo $farmer['user_id']; ?>, name: "<?php echo addslashes($farmer['name']); ?>", email: "<?php echo $farmer['email']; ?>"},
                    <?php endwhile; ?>
                ]
            };
            
            $('#recipientType').change(function() {
                var type = $(this).val();
                var select = $('#recipientSelect');
                select.empty();
                select.append('<option value="">Select recipient</option>');
                
                if (type && users[type]) {
                    users[type].forEach(function(user) {
                        select.append('<option value="' + user.id + '">' + user.name + ' (' + user.email + ')</option>');
                    });
                }
            });
            
            // Auto-switch to compose tab
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('compose')) {
                var composeTab = new bootstrap.Tab(document.querySelector('#compose-tab'));
                composeTab.show();
            }
        });
        
        function clearForm() {
            document.querySelector('form').reset();
            $('#recipientSelect').empty().append('<option value="">Select recipient</option>');
        }
        
        // Auto-update time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateStr = now.toLocaleDateString('en-US', options);
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            $('.time-display').html(`
                <i class="fas fa-calendar-alt me-1"></i> ${dateStr}
                <span class="mx-2">|</span>
                <i class="fas fa-clock me-1"></i> ${timeStr}
            `);
        }
        
        setInterval(updateTime, 60000);
    </script>
    
    <?php
    // Helper function for time ago
    function time_ago($timestamp) {
        $time_ago = strtotime($timestamp);
        $current_time = time();
        $time_difference = $current_time - $time_ago;
        $seconds = $time_difference;
        
        $minutes = round($seconds / 60);
        $hours = round($seconds / 3600);
        $days = round($seconds / 86400);
        $weeks = round($seconds / 604800);
        $months = round($seconds / 2629440);
        $years = round($seconds / 31553280);
        
        if ($seconds <= 60) {
            return "Just now";
        } elseif ($minutes <= 60) {
            if ($minutes == 1) {
                return "1 minute ago";
            } else {
                return "$minutes minutes ago";
            }
        } elseif ($hours <= 24) {
            if ($hours == 1) {
                return "1 hour ago";
            } else {
                return "$hours hours ago";
            }
        } elseif ($days <= 7) {
            if ($days == 1) {
                return "Yesterday";
            } else {
                return "$days days ago";
            }
        } elseif ($weeks <= 4.3) {
            if ($weeks == 1) {
                return "1 week ago";
            } else {
                return "$weeks weeks ago";
            }
        } elseif ($months <= 12) {
            if ($months == 1) {
                return "1 month ago";
            } else {
                return "$months months ago";
            }
        } else {
            if ($years == 1) {
                return "1 year ago";
            } else {
                return "$years years ago";
            }
        }
    }
    ?>
</body>
</html>