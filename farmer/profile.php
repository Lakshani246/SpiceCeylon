<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$farmer_id = $_SESSION['user_id'];

// Get farmer data
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get farmer statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id")->fetch_assoc()['count'];
$approved_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id AND status='Approved' AND admin_approved='approved'")->fetch_assoc()['count'];
$total_sales = $conn->query("SELECT COALESCE(SUM(oi.total_price), 0) as total_sales FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE p.farmer_id = $farmer_id")->fetch_assoc()['total_sales'];
$total_orders = $conn->query("SELECT COUNT(DISTINCT oi.order_id) as order_count FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE p.farmer_id = $farmer_id")->fetch_assoc()['order_count'];

// Handle profile update
$update_success = false;
$update_error = false;
$error_message = '';

if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $farm_location = trim($_POST['farm_location']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $update_error = true;
        $error_message = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = true;
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email is already taken by another user
        $email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $email_check->bind_param("si", $email, $farmer_id);
        $email_check->execute();
        $email_result = $email_check->get_result();
        
        if ($email_result->num_rows > 0) {
            $update_error = true;
            $error_message = "This email is already registered by another user.";
        } else {
            // Handle profile image upload
            $profile_image = $farmer['profile_image'];
            
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $file_type = $_FILES['profile_image']['type'];
                $file_size = $_FILES['profile_image']['size'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = "user_" . $farmer_id . "_" . time() . "." . $file_extension;
                    $upload_path = "../assets/images/profile_images/" . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                        // Delete old profile image if not default
                        if ($profile_image != 'default-avatar.jpg' && file_exists("../assets/images/profile_images/" . $profile_image)) {
                            unlink("../assets/images/profile_images/" . $profile_image);
                        }
                        $profile_image = $new_filename;
                    }
                }
            }
            
            // Update farmer data
            $update_query = $conn->prepare("
                UPDATE users SET 
                name = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                farm_location = ?, 
                profile_image = ? 
                WHERE user_id = ?
            ");
            $update_query->bind_param("ssssssi", $name, $email, $phone, $address, $farm_location, $profile_image, $farmer_id);
            
            if ($update_query->execute()) {
                $update_success = true;
                // Refresh farmer data
                $farmer = $conn->query("SELECT * FROM users WHERE user_id = $farmer_id")->fetch_assoc();
                $_SESSION['user_name'] = $name;
            } else {
                $update_error = true;
                $error_message = "Error updating profile. Please try again.";
            }
        }
    }
}

// Handle password change
$password_success = false;
$password_error = false;
$password_message = '';

// List of common passwords to avoid
$common_passwords = [
    'password', 'password123', '123456', '12345678', '123456789', '12345', '1234567890',
    'qwerty', 'qwerty123', 'admin', 'admin123', 'letmein', 'welcome', 'monkey', 'dragon',
    'football', 'baseball', 'iloveyou', 'trustno1', 'abc123', 'password1', 'passw0rd',
    'zaq1zaq1', 'asdfgh', 'qwertyuiop', 'qwertyui', 'q1w2e3r4', '1qaz2wsx', '1q2w3e4r'
];

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $farmer['password'])) {
        if ($new_password === $confirm_password) {
            
            // Requirement 1: At least 6 characters
            if (strlen($new_password) < 6) {
                $password_error = true;
                $password_message = "Password must be at least 6 characters long.";
            }
            // Requirement 2: Should include letters and numbers
            elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $password_error = true;
                $password_message = "Password must contain both letters and numbers.";
            }
            // Requirement 3: Avoid common passwords
            elseif (in_array(strtolower($new_password), $common_passwords)) {
                $password_error = true;
                $password_message = "This password is too common. Please choose a stronger password.";
            }
            else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $password_query->bind_param("si", $hashed_password, $farmer_id);
                
                if ($password_query->execute()) {
                    $password_success = true;
                    $password_message = "Password changed successfully!";
                    // Refresh farmer data
                    $farmer = $conn->query("SELECT * FROM users WHERE user_id = $farmer_id")->fetch_assoc();
                } else {
                    $password_error = true;
                    $password_message = "Error updating password. Please try again.";
                }
            }
        } else {
            $password_error = true;
            $password_message = "New password and confirm password do not match.";
        }
    } else {
        $password_error = true;
        $password_message = "Current password is incorrect.";
    }
}

// Get recent activities
$recent_activities = $conn->query("
    SELECT 
        'Product Added' as activity_type,
        p.name as description,
        p.created_at as activity_date
    FROM products p
    WHERE p.farmer_id = $farmer_id
    UNION ALL
    SELECT 
        'Order Received' as activity_type,
        CONCAT('Order #', o.order_id) as description,
        o.created_at as activity_date
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE p.farmer_id = $farmer_id
    ORDER BY activity_date DESC
    LIMIT 10
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Farmer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --farmer-green: #27ae60;
            --farmer-dark: #2c3e50;
            --farmer-gold: #f39c12;
            --farmer-blue: #3498db;
            --farmer-brown: #8b4513;
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
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .profile-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            color: white;
            height: 100%;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
        
        .profile-avatar-container {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid var(--farmer-green);
            object-fit: cover;
            margin: 0 auto;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--farmer-green);
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 10px;
            right: calc(50% - 75px + 100px);
            background: var(--farmer-green);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--farmer-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--farmer-green);
            box-shadow: 0 0 0 0.25rem rgba(39, 174, 96, 0.25);
        }
        
        .btn-primary {
            background: var(--farmer-green);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-outline-primary {
            border-color: var(--farmer-green);
            color: var(--farmer-green);
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--farmer-green);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--farmer-green);
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .activity-product {
            background: rgba(39, 174, 96, 0.1);
            color: var(--farmer-green);
        }
        
        .activity-order {
            background: rgba(52, 152, 219, 0.1);
            color: var(--farmer-blue);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-active {
            background: rgba(39, 174, 96, 0.15);
            color: var(--farmer-green);
        }
        
        .badge-pending {
            background: rgba(243, 156, 18, 0.15);
            color: var(--farmer-gold);
        }
        
        .badge-inactive {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .password-input-group {
            position: relative;
        }
        
        .tab-content {
            padding: 25px 0;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--farmer-green);
            background-color: white;
            border-bottom: 3px solid var(--farmer-green);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--farmer-green);
            border-color: transparent;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1000;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--farmer-green);
            border-left: 4px solid var(--farmer-green);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
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
        
        .form-actions {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .alert-center {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                top: -100px;
                opacity: 0;
            }
            to {
                top: 20px;
                opacity: 1;
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
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i> My Orders
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
                        <a class="nav-link active" href="profile.php">
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
                                <i class="fas fa-user me-2" style="color: var(--farmer-green);"></i>
                                My Profile
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Manage your profile information and account settings.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--farmer-green), #219653); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(39, 174, 96, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages - Center of page -->
                <?php if($update_success): ?>
                <div class="alert alert-success alert-dismissible fade show alert-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Profile updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($update_error): ?>
                <div class="alert alert-danger alert-dismissible fade show alert-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($password_success): ?>
                <div class="alert alert-success alert-dismissible fade show alert-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $password_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($password_error): ?>
                <div class="alert alert-danger alert-dismissible fade show alert-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $password_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #219653);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                                    <div class="stat-label">Total Products</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-check-circle me-1"></i> 
                                        <?php echo $approved_products; ?> approved
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-leaf"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                                    <div class="stat-label">Total Orders</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-shopping-cart me-1"></i> 
                                        From customers
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($total_sales, 0); ?></div>
                                    <div class="stat-label">Total Sales</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-coins me-1"></i> 
                                        Lifetime revenue
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">
                                        <?php 
                                        $days_registered = floor((time() - strtotime($farmer['created_at'])) / (60 * 60 * 24));
                                        echo $days_registered > 0 ? $days_registered : 1;
                                        ?>
                                    </div>
                                    <div class="stat-label">Days Registered</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar me-1"></i> 
                                        Since <?php echo date('M d, Y', strtotime($farmer['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Content -->
                <div class="row">
                    <!-- Left Column: Profile Form -->
                    <div class="col-md-8">
                        <div class="profile-card">
                            <div class="profile-avatar-container">
                                <?php if(!empty($farmer['profile_image']) && $farmer['profile_image'] != 'default-avatar.jpg'): ?>
                                    <img src="../assets/images/profile_images/<?php echo $farmer['profile_image']; ?>" 
                                         alt="Profile" class="profile-avatar" id="profileAvatar">
                                <?php else: ?>
                                    <div class="profile-avatar">
                                        <i class="fas fa-tractor"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <ul class="nav nav-tabs" id="profileTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" 
                                            data-bs-target="#profile" type="button" role="tab">
                                        <i class="fas fa-user-edit me-2"></i> Profile Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" 
                                            data-bs-target="#password" type="button" role="tab">
                                        <i class="fas fa-lock me-2"></i> Change Password
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="profileTabContent">
                                <!-- Profile Information Tab -->
                                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="update_profile" value="1">
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Full Name *</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?php echo htmlspecialchars($farmer['name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email Address *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($farmer['email']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Phone Number</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?php echo htmlspecialchars($farmer['phone']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="profile_image" class="form-label">Profile Picture</label>
                                                    <input type="file" class="form-control" id="profile_image" name="profile_image" 
                                                           accept="image/*">
                                                    <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="address" class="form-label">Address</label>
                                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($farmer['address']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="farm_location" class="form-label">Farm Location</label>
                                                    <input type="text" class="form-control" id="farm_location" name="farm_location" 
                                                           value="<?php echo htmlspecialchars($farmer['farm_location']); ?>">
                                                    <small class="text-muted">Where your farm is located</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="status-badge badge-<?php echo strtolower($farmer['status']); ?>">
                                                        <i class="fas fa-circle me-1"></i> 
                                                        Account Status: <?php echo $farmer['status']; ?>
                                                    </span>
                                                    <small class="text-muted ms-3">
                                                        <i class="fas fa-calendar me-1"></i> 
                                                        Member since: <?php echo date('M d, Y', strtotime($farmer['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i> Save Profile Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Change Password Tab -->
                                <div class="tab-pane fade" id="password" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="change_password" value="1">
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-12">
                                                <div class="info-box mb-4">
                                                    <h6 class="mb-2"><i class="fas fa-shield-alt me-2"></i> Password Requirements</h6>
                                                    <p class="mb-0 small text-muted">
                                                        • Must be at least 6 characters long<br>
                                                        • Must include both letters and numbers<br>
                                                        • Cannot be a commonly used password<br>
                                                        • Avoid using personal information
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-12">
                                                <div class="password-input-group mb-3">
                                                    <label for="current_password" class="form-label">Current Password *</label>
                                                    <input type="password" class="form-control" id="current_password" 
                                                           name="current_password" required>
                                                    <span class="password-toggle" onclick="togglePassword('current_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="password-input-group mb-3">
                                                    <label for="new_password" class="form-label">New Password *</label>
                                                    <input type="password" class="form-control" id="new_password" 
                                                           name="new_password" required>
                                                    <span class="password-toggle" onclick="togglePassword('new_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="password-input-group mb-3">
                                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                                    <input type="password" class="form-control" id="confirm_password" 
                                                           name="confirm_password" required>
                                                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Password Strength Indicator -->
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                                                </div>
                                                <small class="text-muted" id="passwordStrengthText">Enter password to check strength</small>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Last changed: 
                                                        <?php 
                                                        // Check if updated_at exists in the array and is valid
                                                        if (isset($farmer['updated_at']) && !empty($farmer['updated_at']) && $farmer['updated_at'] != '0000-00-00 00:00:00') {
                                                            if ($farmer['updated_at'] != $farmer['created_at']) {
                                                                echo date('M d, Y', strtotime($farmer['updated_at']));
                                                            } else {
                                                                echo 'Never changed';
                                                            }
                                                        } else {
                                                            echo 'Never changed';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-key me-2"></i> Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Account Info & Activities -->
                    <div class="col-md-4">
                        <!-- Account Information -->
                        <div class="profile-card mb-4">
                            <h5 class="mb-4">
                                <i class="fas fa-info-circle me-2" style="color: var(--farmer-green);"></i>
                                Account Information
                            </h5>
                            
                            <div class="info-box mb-3">
                                <h6 class="mb-2"><i class="fas fa-id-card me-2"></i> Farmer ID</h6>
                                <p class="mb-0 fw-bold">F<?php echo str_pad($farmer['user_id'], 4, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            
                            <div class="info-box mb-3">
                                <h6 class="mb-2"><i class="fas fa-user-tag me-2"></i> Account Type</h6>
                                <p class="mb-0">
                                    <span class="badge bg-success">Verified Farmer</span>
                                </p>
                                <small class="text-muted">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo ucfirst($farmer['role']); ?> Account
                                </small>
                            </div>
                            
                            <div class="info-box mb-3">
                                <h6 class="mb-2"><i class="fas fa-clock me-2"></i> Last Login</h6>
                                <p class="mb-0">
                                    <?php 
                                    if (!empty($farmer['last_login'])) {
                                        echo date('M d, Y h:i A', strtotime($farmer['last_login']));
                                    } else {
                                        echo 'Not available';
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="info-box">
                                <h6 class="mb-2"><i class="fas fa-chart-line me-2"></i> Performance</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center p-2">
                                            <div class="fw-bold text-primary"><?php echo $approved_products; ?></div>
                                            <small class="text-muted">Active Products</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2">
                                            <div class="fw-bold text-success"><?php echo number_format($total_orders); ?></div>
                                            <small class="text-muted">Orders</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="dashboard.php" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                                </a>
                                <a href="manage_products.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-leaf me-2"></i> Manage Products
                                </a>
                            </div>
                        </div>
                        
                        <!-- Recent Activities -->
                        <div class="profile-card">
                            <h5 class="mb-4">
                                <i class="fas fa-history me-2" style="color: var(--farmer-blue);"></i>
                                Recent Activities
                            </h5>
                            
                            <?php if($recent_activities->num_rows > 0): ?>
                            <div class="activity-list">
                                <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon <?php echo $activity['activity_type'] == 'Product Added' ? 'activity-product' : 'activity-order'; ?>">
                                            <i class="fas <?php echo $activity['activity_type'] == 'Product Added' ? 'fa-leaf' : 'fa-shopping-cart'; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo $activity['activity_type']; ?></div>
                                            <div class="text-muted small"><?php echo $activity['description']; ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding: 30px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h6 class="text-muted mb-3">No recent activities</h6>
                                <p class="text-muted small mb-0">
                                    Your activities will appear here.
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 text-center">
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View All Activities
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Note -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-shield-alt fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-lock me-2"></i> Account Security
                                    </h6>
                                    <p class="mb-0 small">
                                        <strong>Keep your account secure:</strong> 
                                        Never share your password with anyone. 
                                        Use a strong, unique password and change it regularly. 
                                        Log out after each session, especially on shared computers.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-user me-1"></i> 
                                Farmer Profile • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-leaf me-1"></i> 
                                Products: <?php echo $total_products; ?> listed • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-chart-line me-1"></i> 
                                Revenue: Rs. <?php echo number_format($total_sales, 2); ?>
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
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let message = '';
            
            // Check length
            if (password.length >= 6) strength += 25;
            
            // Check for letters
            if (/[A-Za-z]/.test(password)) strength += 25;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 25;
            
            // Check for special characters and mixed case (bonus)
            if (/[^A-Za-z0-9]/.test(password)) strength += 15;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength += 10;
            
            // Cap at 100
            strength = Math.min(strength, 100);
            
            // Update progress bar
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.className = 'progress-bar bg-danger';
                message = 'Weak password';
            } else if (strength < 75) {
                strengthBar.className = 'progress-bar bg-warning';
                message = 'Medium strength password';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                message = 'Strong password';
            }
            
            strengthText.textContent = message;
        });
        
        // Preview profile image before upload
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatar = document.getElementById('profileAvatar');
                    if (avatar) {
                        avatar.src = e.target.result;
                    } else {
                        // Create new image element if it doesn't exist
                        const profileAvatarContainer = document.querySelector('.profile-avatar-container');
                        const oldAvatar = document.querySelector('.profile-avatar');
                        if (oldAvatar) oldAvatar.remove();
                        
                        const newAvatar = document.createElement('img');
                        newAvatar.id = 'profileAvatar';
                        newAvatar.className = 'profile-avatar';
                        newAvatar.src = e.target.result;
                        newAvatar.alt = 'Profile';
                        profileAvatarContainer.prepend(newAvatar);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Initialize tooltips
        $(document).ready(function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Set up profile image upload button
            const profileImageInput = document.getElementById('profile_image');
            if (profileImageInput) {
                // Add click event to avatar to trigger file input
                const avatar = document.querySelector('.profile-avatar');
                if (avatar) {
                    avatar.style.cursor = 'pointer';
                    avatar.addEventListener('click', function() {
                        profileImageInput.click();
                    });
                }
            }
            
            // Add hover effect to cards
            $('.profile-card, .stat-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>