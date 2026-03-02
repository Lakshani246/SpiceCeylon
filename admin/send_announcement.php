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

// Handle announcement sending - FIXED: Same page submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_announcement'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $message = $conn->real_escape_string($_POST['message']);
    $target_roles = $conn->real_escape_string($_POST['target_roles']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $announcement_type = $conn->real_escape_string($_POST['announcement_type']);
    $expires_at = !empty($_POST['expires_at']) ? "'" . $_POST['expires_at'] . " 23:59:59'" : "NULL";
    $target_user_id = !empty($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
    
    // Insert into announcements table
    $sql = "INSERT INTO announcements (title, message, target_roles, target_user_id, created_by, is_important, announcement_type, expires_at, status, created_at) 
            VALUES ('$title', '$message', '$target_roles', " . ($target_user_id ?: 'NULL') . ", $admin_id, $is_important, '$announcement_type', $expires_at, 'active', NOW())";
    
    if ($conn->query($sql)) {
        $announcement_id = $conn->insert_id;
        $success_message = "Announcement sent successfully!";
        
        // Create status entries for all target users
        $recipient_count = 0;
        
        if ($target_roles == 'all') {
            $result = $conn->query("SELECT user_id FROM users WHERE status = 'active'");
            while($row = $result->fetch_assoc()) {
                $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, {$row['user_id']})");
                $recipient_count++;
            }
            // Also add admins
            $result = $conn->query("SELECT admin_id as user_id FROM admins WHERE status = 'active'");
            while($row = $result->fetch_assoc()) {
                $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, {$row['user_id']})");
                $recipient_count++;
            }
            $success_message .= " Sent to $recipient_count recipients.";
            
        } elseif ($target_roles == 'customers') {
            $result = $conn->query("SELECT user_id FROM users WHERE role = 'customer' AND status = 'active'");
            while($row = $result->fetch_assoc()) {
                $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, {$row['user_id']})");
                $recipient_count++;
            }
            $success_message .= " Sent to $recipient_count customers.";
            
        } elseif ($target_roles == 'farmers') {
            $result = $conn->query("SELECT user_id FROM users WHERE role = 'farmer' AND status = 'active'");
            while($row = $result->fetch_assoc()) {
                $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, {$row['user_id']})");
                $recipient_count++;
            }
            $success_message .= " Sent to $recipient_count farmers.";
            
        } elseif ($target_roles == 'admins') {
            $result = $conn->query("SELECT admin_id as user_id FROM admins WHERE status = 'active'");
            while($row = $result->fetch_assoc()) {
                $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, {$row['user_id']})");
                $recipient_count++;
            }
            $success_message .= " Sent to $recipient_count admins.";
            
        } elseif ($target_roles == 'specific' && $target_user_id > 0) {
            $conn->query("INSERT INTO user_announcement_status (announcement_id, user_id) VALUES ($announcement_id, $target_user_id)");
            $success_message .= " Sent to specific user.";
        }
        
    } else {
        $error_message = "Failed to send announcement: " . $conn->error;
    }
}

// Handle announcement actions
if (isset($_GET['delete'])) {
    $announcement_id = (int)$_GET['delete'];
    // First delete from user_announcement_status
    $conn->query("DELETE FROM user_announcement_status WHERE announcement_id = $announcement_id");
    // Then delete from announcements
    $conn->query("DELETE FROM announcements WHERE announcement_id = $announcement_id");
    $success_message = "Announcement deleted successfully!";
}

if (isset($_GET['archive'])) {
    $announcement_id = (int)$_GET['archive'];
    $conn->query("UPDATE announcements SET status = 'archived' WHERE announcement_id = $announcement_id");
    $success_message = "Announcement archived successfully!";
}

if (isset($_GET['activate'])) {
    $announcement_id = (int)$_GET['activate'];
    $conn->query("UPDATE announcements SET status = 'active' WHERE announcement_id = $announcement_id");
    $success_message = "Announcement activated successfully!";
}

// Get counts for different announcement stats
$total_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements")->fetch_assoc()['count'];
$active_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE status = 'active'")->fetch_assoc()['count'];
$important_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_important = 1")->fetch_assoc()['count'];
$expired_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE expires_at < NOW() AND expires_at IS NOT NULL")->fetch_assoc()['count'];

// Get user counts for audience stats
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'")->fetch_assoc()['count'];
$total_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'farmer' AND status = 'active'")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM admins WHERE status = 'active'")->fetch_assoc()['count'];
$total_users = $total_customers + $total_farmers + $total_admins;

// Get all announcements with read statistics
$announcements_list = $conn->query("
    SELECT a.*, adm.username as admin_name,
           (SELECT COUNT(*) FROM user_announcement_status uas WHERE uas.announcement_id = a.announcement_id) as total_recipients,
           (SELECT COUNT(*) FROM user_announcement_status uas WHERE uas.announcement_id = a.announcement_id AND uas.is_read = 1) as read_count
    FROM announcements a
    LEFT JOIN admins adm ON a.created_by = adm.admin_id
    ORDER BY 
        CASE a.status 
            WHEN 'active' THEN 1 
            WHEN 'archived' THEN 2 
            ELSE 3 
        END,
        a.created_at DESC
");

// Get users for specific targeting
$all_users = $conn->query("SELECT user_id, name, email, role FROM users WHERE status = 'active' ORDER BY role, name");

// Announcement types
$announcement_types = ['general', 'maintenance', 'promotion', 'update', 'event', 'alert'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - SpiceCeylon Admin</title>
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
            border-left: 5px solid var(--spice-purple);
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
        
        .stat-icon.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-icon.green { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-icon.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-icon.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        
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
        
        /* Announcement Cards */
        .announcement-section {
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
            color: var(--spice-purple);
        }
        
        /* Send Announcement Form */
        .send-announcement-form {
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
            border-color: var(--spice-purple);
            box-shadow: 0 0 0 0.2rem rgba(155, 89, 182, 0.25);
        }
        
        .btn-send {
            background: linear-gradient(135deg, var(--spice-purple), #8e44ad);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(155, 89, 182, 0.4);
            color: white;
        }
        
        /* Announcement Items */
        .announcement-item {
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
        
        .announcement-item:hover {
            background: #f1f3f5;
            transform: translateX(5px);
        }
        
        .announcement-item.important {
            border-left-color: var(--spice-red);
            background: linear-gradient(90deg, rgba(184, 92, 56, 0.05), #f8f9fa);
        }
        
        .announcement-item.active {
            border-left-color: var(--spice-green);
        }
        
        .announcement-item.archived {
            border-left-color: #95a5a6;
            opacity: 0.7;
        }
        
        .announcement-item.maintenance { border-left-color: var(--spice-orange); }
        .announcement-item.promotion { border-left-color: var(--spice-green); }
        .announcement-item.update { border-left-color: var(--spice-blue); }
        .announcement-item.event { border-left-color: var(--spice-purple); }
        .announcement-item.alert { border-left-color: var(--spice-red); }
        
        .announcement-icon {
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
        
        .announcement-icon.general { background: var(--spice-purple); }
        .announcement-icon.maintenance { background: var(--spice-orange); }
        .announcement-icon.promotion { background: var(--spice-green); }
        .announcement-icon.update { background: var(--spice-blue); }
        .announcement-icon.event { background: var(--spice-gold); }
        .announcement-icon.alert { background: var(--spice-red); }
        
        .announcement-content {
            flex: 1;
        }
        
        .announcement-title {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .announcement-title .badge {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        .announcement-message {
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .announcement-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #718096;
            flex-wrap: wrap;
        }
        
        .announcement-meta i {
            margin-right: 3px;
            font-size: 0.7rem;
        }
        
        .announcement-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .announcement-actions .btn-sm {
            padding: 3px 10px;
            font-size: 0.8rem;
            border-radius: 20px;
        }
        
        /* Target Badges */
        .target-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-all { background: rgba(155, 89, 182, 0.1); color: var(--spice-purple); }
        .badge-customers { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        .badge-farmers { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .badge-admins { background: rgba(243, 156, 18, 0.1); color: var(--spice-gold); }
        .badge-specific { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
        
        /* Type Badges */
        .type-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .type-general { background: #e2e8f0; color: #4a5568; }
        .type-maintenance { background: #fed7d7; color: #9b2c2c; }
        .type-promotion { background: #c6f6d5; color: #22543d; }
        .type-update { background: #bee3f8; color: #2c5282; }
        .type-event { background: #e9d8fd; color: #553c9a; }
        .type-alert { background: #feebc8; color: #7b341e; }
        
        /* Status Badges */
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.archived { background: #e2e8f0; color: #4a5568; }
        .status-badge.expired { background: #f8d7da; color: #721c24; }
        
        /* Read Progress Bar */
        .read-progress {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .progress-bar-custom {
            width: 60px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--spice-green);
            border-radius: 2px;
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
        
        /* Alert Messages */
        .alert {
            border-radius: 10px;
            border-left-width: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            border-left-color: var(--spice-green);
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-warning {
            border-left-color: var(--spice-gold);
            background-color: #fff3cd;
            color: #856404;
        }
        
        .alert-info {
            border-left-color: var(--spice-blue);
            background-color: #d1ecf1;
            color: #0c5460;
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
            
            .announcement-item {
                flex-direction: column;
            }
            
            .announcement-actions {
                margin-top: 10px;
            }
            
            .announcement-title {
                flex-direction: column;
                align-items: flex-start;
            }
        }
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
                                <i class="fas fa-bullhorn me-2" style="color: var(--spice-purple);"></i>
                                Announcements Center
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-clock me-1"></i> 
                                Create and manage platform-wide announcements for users
                            </p>
                        </div>
                        <div>
                            <button class="btn btn-outline-primary me-2" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_announcements; ?></h3>
                            <p>Total Announcements</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $active_announcements; ?></h3>
                            <p>Active Now</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $important_announcements; ?></h3>
                            <p>Important</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-hourglass-end"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $expired_announcements; ?></h3>
                            <p>Expired</p>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Send Announcement Form -->
                <div class="announcement-section">
                    <div class="section-header">
                        <h4><i class="fas fa-paper-plane"></i> Create New Announcement</h4>
                    </div>
                    
                    <div class="send-announcement-form">
                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="announcementForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="title" placeholder="e.g., System Maintenance, New Feature Launch" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Announcement Type <span class="text-danger">*</span></label>
                                        <select class="form-select" name="announcement_type" required>
                                            <?php foreach($announcement_types as $type): ?>
                                                <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="message" rows="4" placeholder="Enter your announcement message..." required></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Target Audience <span class="text-danger">*</span></label>
                                        <select class="form-select" name="target_roles" id="targetRoles" required>
                                            <option value="all">All Users (<?php echo $total_users; ?> total)</option>
                                            <option value="customers">Customers Only (<?php echo $total_customers; ?>)</option>
                                            <option value="farmers">Farmers Only (<?php echo $total_farmers; ?>)</option>
                                            <option value="admins">Admins Only (<?php echo $total_admins; ?>)</option>
                                            <option value="specific">Specific User</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Expiration Date (Optional)</label>
                                        <input type="date" class="form-control" name="expires_at" min="<?php echo date('Y-m-d'); ?>">
                                        <small class="text-muted">Announcement will auto-archive after this date</small>
                                    </div>
                                </div>
                                
                                <!-- Specific User Selection -->
                                <div class="col-md-12" id="specificUserDiv" style="display: none;">
                                    <div class="form-group">
                                        <label class="form-label">Select Specific User <span class="text-danger">*</span></label>
                                        <select class="form-select" name="target_user_id" id="targetUserSelect">
                                            <option value="">Select a user</option>
                                            <?php 
                                            $all_users->data_seek(0);
                                            while($user = $all_users->fetch_assoc()): 
                                            ?>
                                                <option value="<?php echo $user['user_id']; ?>">
                                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['email']; ?>) - <?php echo ucfirst($user['role']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_important" id="isImportant" value="1">
                                        <label class="form-check-label" for="isImportant">
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                            Mark as Important (will be highlighted for users)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <button type="submit" name="send_announcement" class="btn btn-send">
                                        <i class="fas fa-paper-plane me-2"></i> Send Announcement
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Announcements List -->
                <div class="announcement-section">
                    <div class="section-header">
                        <h4><i class="fas fa-history"></i> Announcement History</h4>
                        <div>
                            <span class="me-2"><i class="fas fa-circle text-danger"></i> Important</span>
                            <span class="me-2"><i class="fas fa-circle text-success"></i> Active</span>
                            <span><i class="fas fa-circle text-secondary"></i> Archived</span>
                        </div>
                    </div>
                    
                    <?php if($announcements_list && $announcements_list->num_rows > 0): ?>
                        <?php while($announcement = $announcements_list->fetch_assoc()): 
                            $type_class = $announcement['announcement_type'] ?? 'general';
                            $target_class = 'badge-' . $announcement['target_roles'];
                            
                            // Check if expired
                            $is_expired = false;
                            if($announcement['expires_at'] && strtotime($announcement['expires_at']) < time()) {
                                $is_expired = true;
                            }
                            
                            // Determine status class
                            $status_class = 'active';
                            $status_text = 'Active';
                            if($announcement['status'] == 'archived') {
                                $status_class = 'archived';
                                $status_text = 'Archived';
                            } elseif($is_expired) {
                                $status_class = 'expired';
                                $status_text = 'Expired';
                            }
                            
                            // Calculate read percentage
                            $read_percent = 0;
                            if($announcement['total_recipients'] > 0) {
                                $read_percent = round(($announcement['read_count'] / $announcement['total_recipients']) * 100);
                            }
                        ?>
                            <div class="announcement-item 
                                <?php echo $type_class; ?> 
                                <?php echo $announcement['is_important'] ? 'important' : ''; ?> 
                                <?php echo $announcement['status']; ?>
                                <?php echo $is_expired ? 'archived' : ''; ?>">
                                
                                <div class="announcement-icon <?php echo $type_class; ?>">
                                    <?php
                                    switch($type_class) {
                                        case 'maintenance': echo '<i class="fas fa-tools"></i>'; break;
                                        case 'promotion': echo '<i class="fas fa-tag"></i>'; break;
                                        case 'update': echo '<i class="fas fa-sync-alt"></i>'; break;
                                        case 'event': echo '<i class="fas fa-calendar-alt"></i>'; break;
                                        case 'alert': echo '<i class="fas fa-exclamation"></i>'; break;
                                        default: echo '<i class="fas fa-bullhorn"></i>';
                                    }
                                    ?>
                                </div>
                                
                                <div class="announcement-content">
                                    <div class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                        <?php if($announcement['is_important']): ?>
                                            <span class="badge bg-danger">Important</span>
                                        <?php endif; ?>
                                        <span class="type-badge type-<?php echo $type_class; ?>">
                                            <?php echo ucfirst($type_class); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="announcement-message">
                                        <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                                    </div>
                                    
                                    <div class="announcement-meta">
                                        <span>
                                            <i class="fas fa-users"></i> 
                                            <span class="target-badge <?php echo $target_class; ?>">
                                                <?php echo ucfirst($announcement['target_roles']); ?>
                                            </span>
                                        </span>
                                        <span>
                                            <i class="fas fa-user"></i> 
                                            By: <?php echo htmlspecialchars($announcement['admin_name'] ?: 'Admin'); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?>
                                        </span>
                                        <?php if($announcement['expires_at']): ?>
                                            <span>
                                                <i class="fas fa-hourglass-end"></i> 
                                                Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if($announcement['total_recipients'] > 0): ?>
                                            <span class="read-progress">
                                                <i class="fas fa-envelope-open-text"></i>
                                                <span><?php echo $read_percent; ?>% read</span>
                                                <div class="progress-bar-custom">
                                                    <div class="progress-fill" style="width: <?php echo $read_percent; ?>%"></div>
                                                </div>
                                                <small class="text-muted">(<?php echo $announcement['read_count']; ?>/<?php echo $announcement['total_recipients']; ?>)</small>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="announcement-actions">
                                    <?php if($announcement['status'] == 'active' && !$is_expired): ?>
                                        <a href="?archive=<?php echo $announcement['announcement_id']; ?>" class="btn btn-sm btn-outline-warning" title="Archive" onclick="return confirm('Archive this announcement?')">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                    <?php elseif($announcement['status'] == 'archived' || $is_expired): ?>
                                        <a href="?activate=<?php echo $announcement['announcement_id']; ?>" class="btn btn-sm btn-outline-success" title="Activate" onclick="return confirm('Activate this announcement?')">
                                            <i class="fas fa-sync-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $announcement['announcement_id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this announcement permanently?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements yet</p>
                            <p class="small text-muted">Create your first announcement using the form above.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Audience Info -->
                <div class="announcement-section">
                    <div class="section-header">
                        <h4><i class="fas fa-users"></i> Audience Overview</h4>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3 rounded" style="background: rgba(155, 89, 182, 0.1);">
                                <div class="fw-bold fs-3" style="color: var(--spice-purple);"><?php echo $total_users; ?></div>
                                <div class="text-muted">Total Active Users</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 rounded" style="background: rgba(52, 152, 219, 0.1);">
                                <div class="fw-bold fs-3" style="color: var(--spice-blue);"><?php echo $total_customers; ?></div>
                                <div class="text-muted">Customers</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 rounded" style="background: rgba(39, 174, 96, 0.1);">
                                <div class="fw-bold fs-3" style="color: var(--spice-green);"><?php echo $total_farmers; ?></div>
                                <div class="text-muted">Farmers</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 rounded" style="background: rgba(243, 156, 18, 0.1);">
                                <div class="fw-bold fs-3" style="color: var(--spice-gold);"><?php echo $total_admins; ?></div>
                                <div class="text-muted">Admins</div>
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
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>