<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Get user data with prepared statement
$user_query = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle profile image with proper fallback
$profile_image = 'default-profile.jpg';
if (isset($user['profile_image']) && !empty($user['profile_image'])) {
    $profile_image_path = '../assets/images/profile_images/' . $user['profile_image'];
    if (file_exists($profile_image_path)) {
        $profile_image = $user['profile_image'];
    }
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    
    // Validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address!";
    } else {
        // Handle image upload
        $new_profile_image = $profile_image;
        
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                // Create folder if it doesn't exist
                $upload_dir = '../assets/images/profile_images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists and not default
                    if ($profile_image != 'default-profile.jpg' && file_exists($upload_dir . $profile_image)) {
                        unlink($upload_dir . $profile_image);
                    }
                    $new_profile_image = $new_filename;
                }
            }
        }
        
        // Update user data with prepared statement
        $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_image = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssssi", $name, $email, $phone, $address, $new_profile_image, $user_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $profile_image = $new_profile_image; // Update for current session
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
        $update_stmt->close();
    }
}

// Get cart count for navbar
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-light: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--spice-light);
            color: #333;
            padding-bottom: 50px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--spice-red) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--spice-dark) !important;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--spice-red) !important;
        }
        
        /* Profile Container */
        .profile-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Header */
        .profile-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .profile-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .profile-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .profile-card-header {
            background: rgba(184, 92, 56, 0.1);
            padding: 25px;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        .profile-card-body {
            padding: 30px;
        }
        
        /* Profile Image Section */
        .profile-image-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-image-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-image {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--spice-red);
            padding: 4px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .profile-image-placeholder {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.1), rgba(39, 174, 96, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid var(--spice-red);
            color: var(--spice-red);
            font-size: 3rem;
        }
        
        .image-upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--spice-red);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-btn:hover {
            background: #a04a2c;
            transform: scale(1.1);
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--spice-red);
            box-shadow: 0 0 0 3px rgba(184, 92, 56, 0.1);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
            border-left: 4px solid var(--spice-green);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        /* Buttons */
        .btn-save {
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-cancel {
            background: white;
            color: var(--spice-dark);
            border: 2px solid var(--spice-dark);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: var(--spice-dark);
            color: white;
        }
        
        /* File Upload */
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 20px;
            background: rgba(184, 92, 56, 0.05);
            border: 2px dashed rgba(184, 92, 56, 0.3);
            border-radius: 8px;
            text-align: center;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-label:hover {
            background: rgba(184, 92, 56, 0.1);
            border-color: var(--spice-red);
        }
        
        .file-upload-label i {
            margin-right: 8px;
            color: var(--spice-red);
        }
        
        /* Stats Section */
        .stats-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            border: 1px solid #e9ecef;
            margin-top: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--spice-red);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Footer */
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }
        
        @media (max-width: 768px) {
            .profile-image {
                width: 150px;
                height: 150px;
            }
            
            .profile-image-placeholder {
                width: 150px;
                height: 150px;
            }
            
            .stats-section .row > div {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-pepper-hot me-2"></i>SpiceCeylon
            </a>
            <div class="collapse navbar-collapse">
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
                    <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="wishlist.php"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                            <li><a class="dropdown-item" href="request.php"><i class="fas fa-plus-circle me-2"></i> Request</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Container -->
    <div class="profile-container">
        <!-- Header -->
        <div class="profile-header">
            <h1><i class="fas fa-user-cog me-2"></i>Profile Settings</h1>
            <p>Manage your account information and preferences</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column - Edit Form -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile Information</h4>
                    </div>
                    
                    <div class="profile-card-body">
                        <!-- Profile Image Section -->
                        <div class="profile-image-section">
                            <div class="profile-image-container">
                                <?php
                                $image_path = '../assets/images/profile_images/' . $profile_image;
                                if (file_exists($image_path) && $profile_image != 'default-profile.jpg'):
                                ?>
                                    <img src="<?php echo $image_path; ?>" 
                                         alt="Profile Picture" 
                                         class="profile-image"
                                         onerror="this.style.display='none'; document.getElementById('profile-placeholder').style.display='flex';">
                                    <div id="profile-placeholder" class="profile-image-placeholder d-none">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php else: ?>
                                    <div id="profile-placeholder" class="profile-image-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <label class="image-upload-btn" for="profile_image">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </div>
                            <p class="text-muted">Click the camera icon or choose file below to update your profile picture</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <!-- File Upload Input -->
                            <div class="file-upload-wrapper mb-4">
                                <input type="file" id="profile_image" name="profile_image" class="file-upload-input" 
                                       accept="image/*" onchange="previewImage(event)">
                                <label class="file-upload-label" for="profile_image">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    Choose New Profile Picture (JPG, PNG, GIF - Max 2MB)
                                </label>
                                <div class="form-text text-center">Current file: <?php echo $profile_image; ?></div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                                           placeholder="Enter your full name" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           placeholder="Enter your email" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" 
                                           placeholder="Enter your phone number">
                                    <div class="form-text">Used for delivery notifications</div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="1" 
                                              placeholder="Enter your delivery address"><?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?></textarea>
                                    <div class="form-text">Primary delivery address</div>
                                </div>
                            </div>

                            <!-- Member Since -->
                            <div class="mb-4 p-3 rounded" style="background: rgba(184, 92, 56, 0.05);">
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Member Since</small>
                                        <p class="mb-0 fw-bold">
                                            <?php 
                                            if (isset($user['created_at'])) {
                                                echo date('F j, Y', strtotime($user['created_at']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Account Type</small>
                                        <p class="mb-0 fw-bold">
                                            <span class="badge bg-primary">Customer</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-3 justify-content-end mt-4">
                                <a href="dashboard.php" class="btn btn-cancel">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - Stats -->
            <div class="col-lg-4">
                <!-- Account Stats -->
                <div class="stats-section">
                    <h5 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Account Overview</h5>
                    
                    <?php
                    // Get order stats
                    $order_stats = $conn->prepare("SELECT 
                        COUNT(*) as total_orders,
                        COALESCE(SUM(final_total), 0) as total_spent
                        FROM orders 
                        WHERE customer_id = ? AND status != 'Cancelled'");
                    $order_stats->bind_param("i", $user_id);
                    $order_stats->execute();
                    $order_result = $order_stats->get_result();
                    $order_data = $order_result->fetch_assoc();
                    
                    // Get cart count
                    $cart_stats = $conn->prepare("SELECT COUNT(*) as cart_items FROM cart WHERE customer_id = ?");
                    $cart_stats->bind_param("i", $user_id);
                    $cart_stats->execute();
                    $cart_result = $cart_stats->get_result();
                    $cart_data = $cart_result->fetch_assoc();
                    
                    // Get wishlist count
                    $wishlist_stats = $conn->prepare("SELECT COUNT(*) as wishlist_items FROM wishlist WHERE customer_id = ?");
                    $wishlist_stats->bind_param("i", $user_id);
                    $wishlist_stats->execute();
                    $wishlist_result = $wishlist_stats->get_result();
                    $wishlist_data = $wishlist_result->fetch_assoc();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-4">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $order_data['total_orders']; ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-6 mb-4">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cart_data['cart_items']; ?></div>
                                <div class="stat-label">Cart Items</div>
                            </div>
                        </div>
                        <div class="col-6 mb-4">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $wishlist_data['wishlist_items']; ?></div>
                                <div class="stat-label">Wishlist</div>
                            </div>
                        </div>
                        <div class="col-6 mb-4">
                            <div class="stat-item">
                                <div class="stat-value">Rs. <?php echo number_format($order_data['total_spent'], 0); ?></div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="dashboard.php" class="btn btn-sm" style="background: var(--spice-red); color: white; padding: 8px 20px;">
                            <i class="fas fa-tachometer-alt me-2"></i>View Full Dashboard
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="stats-section mt-3">
                    <h5 class="mb-3"><i class="fas fa-link me-2"></i>Quick Links</h5>
                    <div class="list-group">
                        <a href="orders.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-shopping-bag me-2"></i>My Orders
                        </a>
                        <a href="cart.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                        </a>
                        <a href="wishlist.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-heart me-2"></i>My Wishlist
                        </a>
                        <a href="request.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-plus-circle me-2"></i>Request Product
                        </a>
                        <a href="../auth/logout.php" class="list-group-item list-group-item-action border-0 text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Account Security</h4>
                    <p><i class="fas fa-shield-alt me-2"></i>Your data is secure</p>
                    <p><i class="fas fa-lock me-2"></i>Encrypted connections</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Need Help?</h4>
                    <p><i class="fas fa-question-circle me-2"></i> <a href="#" class="text-light text-decoration-none">FAQ</a></p>
                    <p><i class="fas fa-envelope me-2"></i> <a href="#" class="text-light text-decoration-none">Contact Support</a></p>
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
        // Image preview function
        function previewImage(event) {
            const input = event.target;
            const preview = document.querySelector('.profile-image');
            const placeholder = document.getElementById('profile-placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        if (placeholder) placeholder.style.display = 'none';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
                
                // Update file name display
                const fileName = input.files[0].name;
                document.querySelector('.file-upload-wrapper .form-text').textContent = 'Selected: ' + fileName;
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        // File upload label click simulation
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            document.querySelector('.file-upload-label').innerHTML = 
                `<i class="fas fa-file-image"></i> ${fileName}`;
        });
    </script>
</body>
</html>

<?php
// Close connections
if (isset($user_stmt)) $user_stmt->close();
if (isset($cart_stmt)) $cart_stmt->close();
if (isset($order_stats)) $order_stats->close();
if (isset($cart_stats)) $cart_stats->close();
if (isset($wishlist_stats)) $wishlist_stats->close();
$conn->close();
?>