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

// Handle announcement sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_roles = $_POST['target_roles'];
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : NULL;
    $target_user_id = !empty($_POST['target_user_id']) ? $_POST['target_user_id'] : NULL;
    
    // Validate inputs
    if (empty($title) || empty($message) || empty($target_roles)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        // Insert notification
        $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_roles, target_user_id, sender_id, sender_role, is_important, expires_at) VALUES (?, ?, ?, ?, ?, 'admin', ?, ?)");
        $stmt->bind_param("sssiiss", $title, $message, $target_roles, $target_user_id, $admin_id, $is_important, $expires_at);
        
        if ($stmt->execute()) {
            $notification_id = $conn->insert_id;
            $success_msg = "Announcement sent successfully!";
            
            // Create notification status entries for all targeted users
            if ($target_roles != 'specific') {
                // Get all users based on target role
                $target_users_query = "";
                switch($target_roles) {
                    case 'all':
                        $target_users_query = "SELECT user_id FROM users";
                        break;
                    case 'customers':
                        $target_users_query = "SELECT user_id FROM users WHERE role='customer'";
                        break;
                    case 'farmers':
                        $target_users_query = "SELECT user_id FROM users WHERE role='farmer'";
                        break;
                    case 'admins':
                        // For admins, get from admins table
                        $target_users_query = "SELECT admin_id as user_id FROM admins WHERE status='active'";
                        break;
                }
                
                if (!empty($target_users_query)) {
                    $users_result = $conn->query($target_users_query);
                    while($user = $users_result->fetch_assoc()) {
                        $status_stmt = $conn->prepare("INSERT INTO user_notification_status (notification_id, user_id) VALUES (?, ?)");
                        $status_stmt->bind_param("ii", $notification_id, $user['user_id']);
                        $status_stmt->execute();
                    }
                }
            } else {
                // For specific user, create single entry
                if ($target_user_id) {
                    $status_stmt = $conn->prepare("INSERT INTO user_notification_status (notification_id, user_id) VALUES (?, ?)");
                    $status_stmt->bind_param("ii", $notification_id, $target_user_id);
                    $status_stmt->execute();
                }
            }
            
            // Clear form after successful submission
            $_POST = array();
        } else {
            $error_msg = "Failed to send announcement: " . $conn->error;
        }
    }
}

// Get statistics
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer'")->fetch_assoc()['count'];
$total_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer'")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM admins WHERE status='active'")->fetch_assoc()['count'];
$total_users = $total_customers + $total_farmers + $total_admins;

// Get announcement statistics
$total_announcements = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE sender_role = 'admin'")->fetch_assoc()['count'];
$today_announcements = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE sender_role = 'admin' AND DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$important_announcements = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE sender_role = 'admin' AND is_important = 1")->fetch_assoc()['count'];

// Get recent announcements
$recent_announcements = $conn->query("
    SELECT n.*, 
           (SELECT COUNT(*) FROM user_notification_status uns WHERE uns.notification_id = n.notification_id AND uns.is_read = TRUE) as read_count
    FROM notifications n
    WHERE n.sender_role = 'admin'
    ORDER BY n.created_at DESC
    LIMIT 5
");

// Get users for specific targeting
$all_users = $conn->query("SELECT user_id, name, email, role FROM users ORDER BY role, name");

// Get today's date for display
$today = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Announcement - SpiceCeylon Admin</title>
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
            border-left: 5px solid var(--spice-purple);
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
        
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
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
            background: linear-gradient(135deg, var(--spice-purple), #8e44ad);
        }
        
        .stat-card.customers {
            background: linear-gradient(135deg, var(--spice-blue), #2980b9);
        }
        
        .stat-card.farmers {
            background: linear-gradient(135deg, var(--spice-green), #219653);
        }
        
        .stat-card.admins {
            background: linear-gradient(135deg, var(--spice-gold), #e67e22);
        }
        
        .stat-card.announcements {
            background: linear-gradient(135deg, var(--spice-red), #d35400);
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
        
        .announcement-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .announcement-card:hover {
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.1);
        }
        
        .announcement-card.important {
            border-left: 4px solid var(--spice-red);
            background: #fff8f8;
        }
        
        .btn-announce {
            background: linear-gradient(135deg, var(--spice-purple), #8e44ad);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-announce:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(155, 89, 182, 0.3);
            color: white;
        }
        
        .target-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-all {
            background: rgba(155, 89, 182, 0.1);
            color: var(--spice-purple);
        }
        
        .badge-customers {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
        }
        
        .badge-farmers {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
        }
        
        .badge-admins {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
        }
        
        .badge-specific {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
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
        
        .form-switch .form-check-input:checked {
            background-color: var(--spice-purple);
            border-color: var(--spice-purple);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(184, 92, 56, 0.04);
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
                                <i class="fas fa-bullhorn me-2" style="color: var(--spice-purple);"></i>
                                Send Announcement
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Welcome back, <strong><?php echo htmlspecialchars($admin['username']); ?></strong>! 
                                Broadcast important messages to your users.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-purple), #8e44ad); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3);" class="time-display">
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
                    <div class="col-md-2">
                        <div class="stat-card all">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo $total_users; ?></div>
                                    <div class="stat-label">Total Users</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-user me-1"></i> 
                                        <?php echo $total_customers; ?> customers
                                        <br>
                                        <i class="fas fa-tractor me-1"></i> 
                                        <?php echo $total_farmers; ?> farmers
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="stat-card customers">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo $total_customers; ?></div>
                                    <div class="stat-label">Customers</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-shopping-cart me-1"></i> 
                                        Can receive announcements
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="stat-card farmers">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo $total_farmers; ?></div>
                                    <div class="stat-label">Farmers</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-tractor me-1"></i> 
                                        Can receive announcements
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-tractor"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="stat-card admins">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo $total_admins; ?></div>
                                    <div class="stat-label">Admins</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-user-shield me-1"></i> 
                                        System administrators
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="stat-card announcements">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo $total_announcements; ?></div>
                                    <div class="stat-label">Total Sent</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar-day me-1"></i> 
                                        <?php echo $today_announcements; ?> today
                                        <br>
                                        <i class="fas fa-exclamation me-1"></i> 
                                        <?php echo $important_announcements; ?> important
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <!-- Empty card or additional stat -->
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="row">
                    <!-- Compose Announcement -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-edit me-2"></i>Compose Announcement
                            </h5>
                            
                            <form method="POST" action="" id="announcementForm">
                                <div class="mb-3">
                                    <label class="form-label">Announcement Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" placeholder="Enter announcement title" 
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Message Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="message" rows="6" placeholder="Type your announcement message here..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                    <div class="form-text">This message will be sent to all selected recipients.</div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Target Audience <span class="text-danger">*</span></label>
                                        <select class="form-select" id="targetRoles" name="target_roles" required>
                                            <option value="">Select target audience</option>
                                            <option value="all" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'all') ? 'selected' : ''; ?>>All Users (<?php echo $total_users; ?>)</option>
                                            <option value="customers" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'customers') ? 'selected' : ''; ?>>Customers Only (<?php echo $total_customers; ?>)</option>
                                            <option value="farmers" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'farmers') ? 'selected' : ''; ?>>Farmers Only (<?php echo $total_farmers; ?>)</option>
                                            <option value="admins" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'admins') ? 'selected' : ''; ?>>Admins Only (<?php echo $total_admins; ?>)</option>
                                            <option value="specific" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'specific') ? 'selected' : ''; ?>>Specific User</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Expiration Date (Optional)</label>
                                        <input type="date" class="form-control" name="expires_at" 
                                               value="<?php echo isset($_POST['expires_at']) ? htmlspecialchars($_POST['expires_at']) : ''; ?>" 
                                               min="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">Announcement will expire after this date.</div>
                                    </div>
                                </div>
                                
                                <!-- Specific User Selection -->
                                <div class="mb-3" id="specificUserDiv" style="display: <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'specific') ? 'block' : 'none'; ?>;">
                                    <label class="form-label">Select Specific User <span class="text-danger">*</span></label>
                                    <select class="form-select" name="target_user_id" id="targetUserSelect" <?php echo (isset($_POST['target_roles']) && $_POST['target_roles'] == 'specific') ? 'required' : ''; ?>>
                                        <option value="">Select user</option>
                                        <?php 
                                        $all_users->data_seek(0);
                                        while($user = $all_users->fetch_assoc()): 
                                            $selected = (isset($_POST['target_user_id']) && $_POST['target_user_id'] == $user['user_id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['email']; ?>) - <?php echo ucfirst($user['role']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="isImportant" name="is_important" value="1" 
                                               <?php echo (isset($_POST['is_important']) && $_POST['is_important'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="isImportant">
                                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                                            Mark as Important Announcement
                                        </label>
                                    </div>
                                    <div class="form-text text-warning">
                                        Important announcements will be highlighted to users and marked with a warning icon.
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                        <i class="fas fa-times me-2"></i> Clear Form
                                    </button>
                                    <button type="submit" name="send_announcement" class="btn btn-announce">
                                        <i class="fas fa-paper-plane me-2"></i> Send Announcement
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Statistics & Recent Announcements -->
                    <div class="col-md-4">
                        <!-- Quick Tips -->
                        <div class="analytics-card mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-lightbulb me-2"></i>Quick Tips
                            </h6>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Best Practices:</strong>
                                <ul class="mb-0 mt-2 small">
                                    <li>Keep announcements concise and clear</li>
                                    <li>Use specific, attention-grabbing titles</li>
                                    <li>Target the right audience</li>
                                    <li>Set expiration for time-sensitive info</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important Notes:</strong>
                                <ul class="mb-0 mt-2 small">
                                    <li>Test important announcements first</li>
                                    <li>Avoid sending too frequently</li>
                                    <li>Check spelling and grammar</li>
                                    <li>Archive old announcements regularly</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Recent Announcements -->
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Announcements
                                </h6>
                                <a href="view_notifications.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i> View All
                                </a>
                            </div>
                            
                            <?php if($recent_announcements->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while($ann = $recent_announcements->fetch_assoc()): 
                                    $badge_class = 'badge-' . $ann['target_roles'];
                                    $target_count = 0;
                                    
                                    // Calculate target count
                                    switch($ann['target_roles']) {
                                        case 'all': $target_count = $total_users; break;
                                        case 'customers': $target_count = $total_customers; break;
                                        case 'farmers': $target_count = $total_farmers; break;
                                        case 'admins': $target_count = $total_admins; break;
                                        case 'specific': $target_count = 1; break;
                                    }
                                ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <?php if($ann['is_important']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                            <?php else: ?>
                                                <i class="fas fa-bullhorn text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($ann['title']); ?></div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <span class="target-badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($ann['target_roles']); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php if($target_count > 0): ?>
                                                        <?php echo $ann['read_count']; ?>/<?php echo $target_count; ?> read
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo time_ago($ann['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-bullhorn fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No announcements sent yet</p>
                                <p class="text-muted small">Send your first announcement!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- User Breakdown -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-users me-2"></i>User Breakdown
                            </h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center p-4 rounded" style="background: rgba(155, 89, 182, 0.1);">
                                        <div class="fw-bold fs-3" style="color: var(--spice-purple);"><?php echo $total_users; ?></div>
                                        <div class="text-muted">Total Users</div>
                                        <small class="text-muted">All platform users</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-4 rounded" style="background: rgba(52, 152, 219, 0.1);">
                                        <div class="fw-bold fs-3" style="color: var(--spice-blue);"><?php echo $total_customers; ?></div>
                                        <div class="text-muted">Customers</div>
                                        <small class="text-muted">Spice buyers</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-4 rounded" style="background: rgba(39, 174, 96, 0.1);">
                                        <div class="fw-bold fs-3" style="color: var(--spice-green);"><?php echo $total_farmers; ?></div>
                                        <div class="text-muted">Farmers</div>
                                        <small class="text-muted">Spice producers</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-4 rounded" style="background: rgba(243, 156, 18, 0.1);">
                                        <div class="fw-bold fs-3" style="color: var(--spice-gold);"><?php echo $total_admins; ?></div>
                                        <div class="text-muted">Admins</div>
                                        <small class="text-muted">System administrators</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show/hide specific user selection
            $('#targetRoles').change(function() {
                if ($(this).val() === 'specific') {
                    $('#specificUserDiv').slideDown();
                    $('#targetUserSelect').prop('required', true);
                } else {
                    $('#specificUserDiv').slideUp();
                    $('#targetUserSelect').prop('required', false);
                }
            });
            
            // Initialize based on current selection (for form repopulation)
            if ($('#targetRoles').val() === 'specific') {
                $('#specificUserDiv').show();
                $('#targetUserSelect').prop('required', true);
            }
            
            // Character counter for message
            $('textarea[name="message"]').on('input', function() {
                var length = $(this).val().length;
                $('#charCount').text(length + ' characters');
            });
            
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
            
            // Form validation
            $('#announcementForm').submit(function(e) {
                var title = $('input[name="title"]').val().trim();
                var message = $('textarea[name="message"]').val().trim();
                var target = $('#targetRoles').val();
                
                if (!title || !message || !target) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                if (target === 'specific') {
                    var specificUser = $('#targetUserSelect').val();
                    if (!specificUser) {
                        e.preventDefault();
                        alert('Please select a specific user.');
                        return false;
                    }
                }
                
                return true;
            });
        });
        
        function clearForm() {
            document.getElementById('announcementForm').reset();
            $('#specificUserDiv').hide();
            $('#targetUserSelect').prop('required', false);
        }
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