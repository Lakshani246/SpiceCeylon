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

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'farmers';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'view') {
        // Redirect to view page
        if ($active_tab == 'farmers' || $active_tab == 'customers') {
            header("Location: view_user.php?type=" . ($active_tab == 'farmers' ? 'farmer' : 'customer') . "&id=$user_id");
        } elseif ($active_tab == 'admins') {
            header("Location: view_admin.php?id=$user_id");
        }
        exit;
    }
    
    if ($action == 'approve') {
        if ($active_tab == 'farmers') {
            $conn->query("UPDATE users SET status = 'approved' WHERE user_id = '$user_id'");
            $_SESSION['message'] = "Farmer approved successfully! They can now add products.";
        }
    }
    elseif ($action == 'reject') {
        if ($active_tab == 'farmers') {
            $conn->query("UPDATE users SET status = 'rejected' WHERE user_id = '$user_id'");
            $_SESSION['message'] = "Farmer registration rejected.";
        }
    }
    elseif ($action == 'activate') {
        if ($active_tab == 'farmers' || $active_tab == 'customers') {
            $conn->query("UPDATE users SET status = 'active' WHERE user_id = '$user_id'");
            $_SESSION['message'] = "User activated successfully!";
        } elseif ($active_tab == 'admins' && $user_id != $admin_id) {
            $conn->query("UPDATE admins SET status = 'active' WHERE admin_id = '$user_id'");
            $_SESSION['message'] = "Admin activated successfully!";
        }
    }
    elseif ($action == 'deactivate') {
        if ($active_tab == 'farmers' || $active_tab == 'customers') {
            $conn->query("UPDATE users SET status = 'inactive' WHERE user_id = '$user_id'");
            $_SESSION['message'] = "User deactivated successfully!";
        } elseif ($active_tab == 'admins' && $user_id != $admin_id) {
            $conn->query("UPDATE admins SET status = 'inactive' WHERE admin_id = '$user_id'");
            $_SESSION['message'] = "Admin deactivated successfully!";
        }
    }
    elseif ($action == 'delete') {
        // Only allow delete for inactive users and non-super-admin admins
        if ($active_tab == 'farmers' || $active_tab == 'customers') {
            // Check if user is already inactive
            $user = $conn->query("SELECT status FROM users WHERE user_id = '$user_id'")->fetch_assoc();
            if ($user['status'] == 'inactive' || $user['status'] == 'rejected') {
                $conn->query("DELETE FROM users WHERE user_id = '$user_id'");
                $_SESSION['message'] = "User permanently deleted!";
            } else {
                $_SESSION['error'] = "Cannot delete active users. Deactivate first!";
            }
        } elseif ($active_tab == 'admins' && $user_id != $admin_id) {
            $target_admin = $conn->query("SELECT role, status FROM admins WHERE admin_id = '$user_id'")->fetch_assoc();
            if ($target_admin['role'] != 'super_admin' && $target_admin['status'] == 'inactive') {
                $conn->query("DELETE FROM admins WHERE admin_id = '$user_id'");
                $_SESSION['message'] = "Admin permanently deleted!";
            } else {
                $_SESSION['error'] = "Cannot delete active admins or super admins!";
            }
        }
    }
    
    header("Location: manage_users.php?tab=$active_tab");
    exit;
}

// Check if status column exists in users table
$status_check = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
$has_status_column = $status_check->num_rows > 0;

// Check if status column exists in admins table
$admin_status_check = $conn->query("SHOW COLUMNS FROM admins LIKE 'status'");
$has_admin_status_column = $admin_status_check->num_rows > 0;

// If status column doesn't exist in users, create it
if (!$has_status_column) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('pending', 'approved', 'active', 'inactive', 'rejected') NOT NULL DEFAULT 'pending'");
    $has_status_column = true;
}

// Get counts for each tab
$total_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer'")->fetch_assoc()['count'];
$pending_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='pending'")->fetch_assoc()['count'];
$approved_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='approved'")->fetch_assoc()['count'];
$active_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='active'")->fetch_assoc()['count'];
$rejected_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='rejected'")->fetch_assoc()['count'];
$inactive_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer' AND status='inactive'")->fetch_assoc()['count'];

$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer'")->fetch_assoc()['count'];
$active_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer' AND status='active'")->fetch_assoc()['count'];
$inactive_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer' AND status='inactive'")->fetch_assoc()['count'];

$total_admins = $conn->query("SELECT COUNT(*) as count FROM admins")->fetch_assoc()['count'];
$active_admins = $conn->query("SELECT COUNT(*) as count FROM admins WHERE status='active'")->fetch_assoc()['count'];
$inactive_admins = $conn->query("SELECT COUNT(*) as count FROM admins WHERE status='inactive'")->fetch_assoc()['count'];

// Get users based on active tab
if ($active_tab == 'farmers') {
    $query = "SELECT u.*, 
                     (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id) as total_products,
                     (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND status='approved') as approved_products,
                     (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND status='pending') as pending_products,
                     (SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE p.farmer_id = u.user_id) as total_orders
              FROM users u 
              WHERE role='farmer' 
              ORDER BY 
                CASE status 
                    WHEN 'pending' THEN 1
                    WHEN 'approved' THEN 2
                    WHEN 'active' THEN 3
                    WHEN 'inactive' THEN 4
                    WHEN 'rejected' THEN 5
                    ELSE 6
                END, 
                created_at DESC";
    $title = "Farmer Management";
    $icon = "fas fa-tractor";
} 
elseif ($active_tab == 'customers') {
    $query = "SELECT u.*, 
                     (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id) as total_orders,
                     (SELECT SUM(total_amount) FROM orders WHERE customer_id = u.user_id AND status='completed') as total_spent
              FROM users u 
              WHERE role='customer' 
              ORDER BY 
                CASE status 
                    WHEN 'active' THEN 1
                    ELSE 2
                END, 
                created_at DESC";
    $title = "Customer Management";
    $icon = "fas fa-user";
}
elseif ($active_tab == 'admins') {
    $query = "SELECT * FROM admins 
              ORDER BY 
                CASE role 
                    WHEN 'super_admin' THEN 1
                    WHEN 'admin' THEN 2
                    ELSE 3
                END,
                created_at DESC";
    $title = "Admin Management";
    $icon = "fas fa-user-shield";
}

$users_result = $conn->query($query);
$total_users = $users_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SpiceCeylon Admin</title>
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
            --approved: #27ae60;
            --rejected: #e74c3c;
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
        
        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .user-card.pending { border-left: 4px solid var(--pending); }
        .user-card.approved { border-left: 4px solid var(--approved); }
        .user-card.active { border-left: 4px solid var(--spice-green); }
        .user-card.inactive { border-left: 4px solid #95a5a6; }
        .user-card.rejected { border-left: 4px solid var(--rejected); }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-approved { background: rgba(39, 174, 96, 0.15); color: var(--approved); }
        .badge-active { background: rgba(39, 174, 96, 0.15); color: var(--spice-green); }
        .badge-inactive { background: rgba(149, 165, 166, 0.15); color: #7f8c8d; }
        .badge-rejected { background: rgba(231, 76, 60, 0.15); color: var(--rejected); }
        
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
        
        .stat-card.farmers {
            background: linear-gradient(135deg, var(--spice-green), #219653);
        }
        
        .stat-card.customers {
            background: linear-gradient(135deg, var(--spice-blue), #2980b9);
        }
        
        .stat-card.admins {
            background: linear-gradient(135deg, var(--spice-gold), #e67e22);
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
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            transition: all 0.3s;
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--spice-dark);
            background-color: rgba(184, 92, 56, 0.05);
            border-bottom-color: #dee2e6;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--spice-red);
            border-bottom-color: var(--spice-red);
            background-color: rgba(184, 92, 56, 0.1);
            font-weight: 600;
        }
        
        .action-buttons .btn {
            padding: 5px 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .avatar-farmer { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .avatar-customer { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        .avatar-admin { background: rgba(243, 156, 18, 0.1); color: var(--spice-gold); }
        
        .product-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.85rem;
        }
        
        .product-stats .stat-item {
            display: inline-block;
            margin-right: 15px;
        }
        
        .stat-item .count {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .pending-count { color: var(--pending); }
        .approved-count { color: var(--spice-green); }
        
        .badge-sm {
            font-size: 0.7rem;
            padding: 3px 8px;
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
                                <i class="<?php echo $icon; ?> me-2" style="color: var(--spice-red);"></i>
                                <?php echo $title; ?>
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Manage <?php echo $active_tab; ?> accounts and permissions
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-red), #d35400); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(184, 92, 56, 0.3);">
                            <i class="fas fa-users me-1"></i> Total <?php echo $active_tab; ?>: <?php echo number_format($total_users); ?>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if(isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <a href="manage_users.php?tab=farmers" class="text-decoration-none">
                            <div class="stat-card farmers <?php echo $active_tab == 'farmers' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_farmers); ?></div>
                                        <div class="stat-label">Total Farmers</div>
                                        <div class="small opacity-75 mt-2">
                                            <?php if($pending_farmers > 0): ?>
                                                <span class="badge bg-warning me-1"><?php echo $pending_farmers; ?> pending</span>
                                            <?php endif; ?>
                                            <?php if($active_farmers > 0): ?>
                                                <span class="badge bg-success me-1"><?php echo $active_farmers; ?> active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-tractor"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="manage_users.php?tab=customers" class="text-decoration-none">
                            <div class="stat-card customers <?php echo $active_tab == 'customers' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_customers); ?></div>
                                        <div class="stat-label">Total Customers</div>
                                        <div class="small opacity-75 mt-2">
                                            <?php if($active_customers > 0): ?>
                                                <span class="badge bg-success me-1"><?php echo $active_customers; ?> active</span>
                                            <?php endif; ?>
                                            <?php if($inactive_customers > 0): ?>
                                                <span class="badge bg-secondary"><?php echo $inactive_customers; ?> inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="manage_users.php?tab=admins" class="text-decoration-none">
                            <div class="stat-card admins <?php echo $active_tab == 'admins' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_admins); ?></div>
                                        <div class="stat-label">Total Administrators</div>
                                        <div class="small opacity-75 mt-2">
                                            <?php if($active_admins > 0): ?>
                                                <span class="badge bg-success me-1"><?php echo $active_admins; ?> active</span>
                                            <?php endif; ?>
                                            <?php if($inactive_admins > 0): ?>
                                                <span class="badge bg-secondary"><?php echo $inactive_admins; ?> inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab == 'farmers' ? 'active' : ''; ?>" 
                           href="manage_users.php?tab=farmers">
                            <i class="fas fa-tractor me-2"></i> Farmers
                            <span class="badge <?php echo $active_tab == 'farmers' ? 'bg-light text-dark' : 'bg-secondary'; ?> ms-1">
                                <?php echo $total_farmers; ?>
                            </span>
                            <?php if($pending_farmers > 0 && $active_tab != 'farmers'): ?>
                            <span class="badge bg-warning ms-1"><?php echo $pending_farmers; ?> pending</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab == 'customers' ? 'active' : ''; ?>" 
                           href="manage_users.php?tab=customers">
                            <i class="fas fa-user me-2"></i> Customers
                            <span class="badge <?php echo $active_tab == 'customers' ? 'bg-light text-dark' : 'bg-secondary'; ?> ms-1">
                                <?php echo $total_customers; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab == 'admins' ? 'active' : ''; ?>" 
                           href="manage_users.php?tab=admins">
                            <i class="fas fa-user-shield me-2"></i> Administrators
                            <span class="badge <?php echo $active_tab == 'admins' ? 'bg-light text-dark' : 'bg-secondary'; ?> ms-1">
                                <?php echo $total_admins; ?>
                            </span>
                        </a>
                    </li>
                </ul>

                <!-- Users List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--spice-red);"></i>
                            <?php echo $title; ?> List (<?php echo $total_users; ?> <?php echo $active_tab; ?>)
                        </h5>
                        <div class="small text-muted">
                            <i class="fas fa-sort me-1"></i> 
                            Sorted by status then registration date
                        </div>
                    </div>
                    
                    <?php if($total_users > 0): ?>
                        <?php while($user = $users_result->fetch_assoc()): 
                            // Determine user type and set variables accordingly
                            if ($active_tab == 'admins') {
                                $name = $user['username'] ?? 'Administrator';
                                $id = $user['admin_id'] ?? 'N/A';
                                $id_type = 'Admin ID';
                                $status = $has_admin_status_column ? ($user['status'] ?? 'active') : 'active';
                                $role = $user['role'] ?? 'admin';
                            } else {
                                $name = $user['name'] ?? 'No Name';
                                $id = $user['user_id'] ?? 'N/A';
                                $id_type = 'User ID';
                                $status = $has_status_column ? ($user['status'] ?? 'pending') : 'pending';
                                $role = $user['role'] ?? 'user';
                            }
                        ?>
                        <div class="user-card <?php echo $status; ?>">
                            <div class="row align-items-center">
                                <!-- Avatar & Basic Info -->
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar avatar-<?php echo $active_tab; ?>">
                                            <i class="fas fa-<?php 
                                                echo $active_tab == 'farmers' ? 'tractor' : 
                                                     ($active_tab == 'customers' ? 'user' : 'user-shield');
                                            ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($name); ?></h6>
                                            <div class="small text-muted">
                                                <i class="fas fa-id-card me-1"></i> 
                                                <?php echo $id_type; ?>: #<?php echo $id; ?>
                                            </div>
                                            <?php if($active_tab == 'admins'): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-user-tag me-1"></i> 
                                                    Role: <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-calendar me-1"></i> 
                                                Joined: <?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Info -->
                                <div class="col-md-3">
                                    <div class="small">
                                        <div class="mb-1">
                                            <i class="fas fa-envelope me-1 text-muted"></i> 
                                            <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if($active_tab == 'admins'): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-at me-1 text-muted"></i> 
                                                <?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?>
                                            </div>
                                        <?php elseif(!empty($user['phone'])): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-phone me-1 text-muted"></i> 
                                                <?php echo htmlspecialchars($user['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($active_tab == 'customers' && isset($user['total_spent']) && $user['total_spent'] > 0): ?>
                                            <div>
                                                <i class="fas fa-money-bill-wave me-1 text-muted"></i> 
                                                LKR <?php echo number_format($user['total_spent'], 2); ?> spent
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-2">
                                    <?php 
                                    $status_class = 'badge-' . $status;
                                    $status_icon = 'fa-circle';
                                    
                                    switch($status) {
                                        case 'pending': 
                                            $status_icon = 'fa-clock';
                                            break;
                                        case 'approved': 
                                            $status_icon = 'fa-check-circle';
                                            break;
                                        case 'active': 
                                            $status_icon = 'fa-check-circle';
                                            break;
                                        case 'inactive': 
                                            $status_icon = 'fa-ban';
                                            break;
                                        case 'rejected': 
                                            $status_icon = 'fa-times-circle';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?> mb-2 d-inline-block">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i> 
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                    
                                    <?php if($active_tab == 'customers' && isset($user['total_orders'])): ?>
                                        <div class="small">
                                            <i class="fas fa-shopping-cart me-1"></i>
                                            <?php echo $user['total_orders']; ?> orders
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Additional Info -->
                                <div class="col-md-2">
                                    <?php if($active_tab == 'farmers'): ?>
                                        <div class="product-stats">
                                            <div class="stat-item">
                                                <div class="count"><?php echo $user['total_products'] ?? 0; ?></div>
                                                <div class="text-muted">Products</div>
                                            </div>
                                            <?php if(isset($user['approved_products'])): ?>
                                            <div class="stat-item">
                                                <div class="count approved-count"><?php echo $user['approved_products']; ?></div>
                                                <div class="text-muted">Approved</div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(isset($user['pending_products'])): ?>
                                            <div class="stat-item">
                                                <div class="count pending-count"><?php echo $user['pending_products']; ?></div>
                                                <div class="text-muted">Pending</div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(isset($user['total_orders'])): ?>
                                            <div class="stat-item">
                                                <div class="count"><?php echo $user['total_orders']; ?></div>
                                                <div class="text-muted">Orders</div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif($active_tab == 'customers'): ?>
                                        <div class="small">
                                            <?php if(isset($user['total_orders'])): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-shopping-cart me-1"></i> 
                                                <?php echo $user['total_orders']; ?> orders
                                            </div>
                                            <?php endif; ?>
                                            <?php if(isset($user['total_spent']) && $user['total_spent'] > 0): ?>
                                            <div>
                                                <i class="fas fa-star me-1"></i> 
                                                Regular customer
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif($active_tab == 'admins'): ?>
                                        <div class="small">
                                            <?php if($role == 'super_admin'): ?>
                                            <span class="badge bg-danger badge-sm mb-1">Super Admin</span>
                                            <?php elseif($role == 'admin'): ?>
                                            <span class="badge bg-primary badge-sm mb-1">Admin</span>
                                            <?php endif; ?>
                                            <?php if(isset($user['created_at'])): ?>
                                            <div class="text-muted">
                                                Since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-2">
                                    <div class="action-buttons d-flex flex-wrap justify-content-end">
                                        <!-- View Button -->
                                        <a href="manage_users.php?action=view&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                           class="btn btn-outline-primary btn-sm me-1 mb-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if($active_tab == 'farmers'): ?>
                                            <!-- PENDING Farmers -->
                                            <?php if($status == 'pending'): ?>
                                                <a href="manage_users.php?action=approve&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-success btn-sm me-1 mb-1"
                                                   onclick="return confirm('Approve this farmer? They can now add products.')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="manage_users.php?action=reject&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-warning btn-sm me-1 mb-1"
                                                   onclick="return confirm('Reject this farmer registration?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            
                                            <!-- APPROVED Farmers -->
                                            <?php elseif($status == 'approved'): ?>
                                                <a href="manage_users.php?action=activate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-success btn-sm me-1 mb-1"
                                                   onclick="return confirm('Activate this farmer account?')">
                                                    <i class="fas fa-play"></i> Activate
                                                </a>
                                                <a href="manage_users.php?action=deactivate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-warning btn-sm me-1 mb-1">
                                                    <i class="fas fa-pause"></i> Deactivate
                                                </a>
                                            
                                            <!-- ACTIVE Farmers -->
                                            <?php elseif($status == 'active'): ?>
                                                <a href="manage_users.php?action=deactivate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-warning btn-sm me-1 mb-1"
                                                   onclick="return confirm('Deactivate this farmer?')">
                                                    <i class="fas fa-pause"></i> Deactivate
                                                </a>
                                            
                                            <!-- INACTIVE/REJECTED Farmers -->
                                            <?php elseif($status == 'inactive' || $status == 'rejected'): ?>
                                                <a href="manage_users.php?action=activate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-success btn-sm me-1 mb-1"
                                                   onclick="return confirm('Activate this farmer?')">
                                                    <i class="fas fa-play"></i> Activate
                                                </a>
                                                <a href="manage_users.php?action=delete&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-danger btn-sm me-1 mb-1"
                                                   onclick="return confirm('Permanently delete this farmer? This cannot be undone!')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        
                                        <?php elseif($active_tab == 'customers'): ?>
                                            <!-- ACTIVE Customers -->
                                            <?php if($status == 'active'): ?>
                                                <a href="manage_users.php?action=deactivate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-warning btn-sm me-1 mb-1"
                                                   onclick="return confirm('Deactivate this customer?')">
                                                    <i class="fas fa-pause"></i> Deactivate
                                                </a>
                                            
                                            <!-- INACTIVE Customers -->
                                            <?php elseif($status == 'inactive'): ?>
                                                <a href="manage_users.php?action=activate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-success btn-sm me-1 mb-1"
                                                   onclick="return confirm('Activate this customer?')">
                                                    <i class="fas fa-play"></i> Activate
                                                </a>
                                                <a href="manage_users.php?action=delete&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                   class="btn btn-danger btn-sm me-1 mb-1"
                                                   onclick="return confirm('Permanently delete this customer? This cannot be undone!')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        
                                        <?php elseif($active_tab == 'admins'): ?>
                                            <!-- Only show actions for non-super-admin and not yourself -->
                                            <?php if($role != 'super_admin' && $id != $admin_id): ?>
                                                <?php if($status == 'active'): ?>
                                                    <a href="manage_users.php?action=deactivate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                       class="btn btn-warning btn-sm me-1 mb-1"
                                                       onclick="return confirm('Deactivate this admin?')">
                                                        <i class="fas fa-pause"></i> Deactivate
                                                    </a>
                                                <?php elseif($status == 'inactive'): ?>
                                                    <a href="manage_users.php?action=activate&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                       class="btn btn-success btn-sm me-1 mb-1"
                                                       onclick="return confirm('Activate this admin?')">
                                                        <i class="fas fa-play"></i> Activate
                                                    </a>
                                                    <a href="manage_users.php?action=delete&id=<?php echo $id; ?>&tab=<?php echo $active_tab; ?>" 
                                                       class="btn btn-danger btn-sm me-1 mb-1"
                                                       onclick="return confirm('Permanently delete this admin?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-info">Protected</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="text-muted mb-3">No <?php echo $active_tab; ?> found</h5>
                            <p class="text-muted mb-4">
                                There are no <?php echo $active_tab; ?> registered in the system yet.
                            </p>
                            <?php if($active_tab == 'admins'): ?>
                                <a href="add_admin.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Add New Admin
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
        
        // Highlight active stat card
        const currentTab = "<?php echo $active_tab; ?>";
        $('.stat-card').removeClass('active');
        $(`.stat-card.${currentTab}`).addClass('active');
    </script>
</body>
</html>