<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$admin_id = $_SESSION['admin_id'];
$role = $_SESSION['role'];

// Get admin data - Using your actual table structure
$admin_query = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();

// Handle profile update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Check if email already exists (excluding current admin)
        $check_email = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? AND admin_id != ?");
        $check_email->bind_param("si", $email, $admin_id);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "danger";
        } else {
            // Update query using your actual column names
            $update_query = $conn->prepare("
                UPDATE admins 
                SET username = ?, email = ? 
                WHERE admin_id = ?
            ");
            $update_query->bind_param("ssi", $username, $email, $admin_id);
            
            if ($update_query->execute()) {
                $message = "Profile updated successfully!";
                $message_type = "success";
                
                // Update session
                $_SESSION['admin_name'] = $username;
                
                // Refresh admin data
                $admin['username'] = $username;
                $admin['email'] = $email;
                
            } else {
                $message = "Error updating profile: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password - using 'password' column
        if (!password_verify($current_password, $admin['password'])) {
            $message = "Current password is incorrect!";
            $message_type = "danger";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match!";
            $message_type = "danger";
        } elseif (strlen($new_password) < 6) {
            $message = "Password must be at least 6 characters!";
            $message_type = "danger";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $update_pass->bind_param("si", $hashed_password, $admin_id);
            
            if ($update_pass->execute()) {
                $message = "Password changed successfully!";
                $message_type = "success";
            } else {
                $message = "Error changing password: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
    
    if (isset($_POST['update_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['avatar']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $file_size = $_FILES['avatar']['size'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if ($file_size <= $max_size) {
                    // Create avatars directory if it doesn't exist
                    $avatar_dir = "../uploads/avatars/";
                    if (!file_exists($avatar_dir)) {
                        mkdir($avatar_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $filename = "avatar_" . $admin_id . "_" . time() . "." . $file_extension;
                    $upload_path = $avatar_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                        // Check if avatar column exists in database
                        $check_avatar_column = $conn->query("SHOW COLUMNS FROM admins LIKE 'avatar'");
                        
                        if ($check_avatar_column->num_rows > 0) {
                            // Delete old avatar if exists
                            if (!empty($admin['avatar']) && file_exists($avatar_dir . $admin['avatar'])) {
                                unlink($avatar_dir . $admin['avatar']);
                            }
                            
                            // Update avatar in database
                            $update_avatar = $conn->prepare("UPDATE admins SET avatar = ? WHERE admin_id = ?");
                            $update_avatar->bind_param("si", $filename, $admin_id);
                            
                            if ($update_avatar->execute()) {
                                $admin['avatar'] = $filename;
                                $message = "Profile picture updated successfully!";
                                $message_type = "success";
                            }
                        } else {
                            // If no avatar column, store in session
                            $_SESSION['admin_avatar'] = $filename;
                            $message = "Profile picture updated (stored in session)!";
                            $message_type = "success";
                        }
                    } else {
                        $message = "Error uploading file!";
                        $message_type = "danger";
                    }
                } else {
                    $message = "File size too large (max 2MB)!";
                    $message_type = "danger";
                }
            } else {
                $message = "Invalid file type. Allowed: JPG, PNG, GIF, WebP";
                $message_type = "danger";
            }
        }
    }
}

// Format date for display
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return 'Never';
    }
    return date('F d, Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - SpiceCeylon Admin</title>
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
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .avatar-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
            position: relative;
        }
        
        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--spice-blue);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .avatar-upload:hover {
            background: var(--spice-dark);
            transform: scale(1.1);
        }
        
        .profile-info-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .profile-info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--spice-dark);
            min-width: 120px;
        }
        
        .info-value {
            color: #666;
        }
        
        .form-control:focus {
            border-color: var(--spice-blue);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--spice-blue), #2980b9);
            border: none;
            padding: 10px 25px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, var(--spice-blue));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--spice-green), #219653);
            border: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--spice-gold), #e67e22);
            border: none;
            color: white;
        }
        
        .role-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-super-admin {
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            color: white;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .badge-moderator {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .security-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid var(--spice-green);
        }
        
        .password-strength {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: #27ae60; width: 100%; }
        
        @media (max-width: 768px) {
            .avatar-container {
                width: 120px;
                height: 120px;
            }
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
                <!-- Display Message -->
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-user-circle me-2" style="color: var(--spice-purple);"></i>
                                Admin Profile
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-id-card me-1"></i> Manage your account settings and preferences
                            </p>
                        </div>
                        <div>
                            <span class="role-badge badge-<?php echo str_replace('_', '-', $role); ?>">
                                <i class="fas fa-<?php echo $role == 'super_admin' ? 'crown' : 'user-shield'; ?> me-1"></i>
                                <?php echo ucwords(str_replace('_', ' ', $role)); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column: Profile Info -->
                    <div class="col-md-4">
                        <!-- Profile Card -->
                        <div class="profile-card text-center">
                            <div class="avatar-container">
                                <?php 
                                // Check for avatar in database or session
                                $avatar_file = '';
                                if (!empty($admin['avatar'])) {
                                    $avatar_file = "../uploads/avatars/" . htmlspecialchars($admin['avatar']);
                                } elseif (isset($_SESSION['admin_avatar'])) {
                                    $avatar_file = "../uploads/avatars/" . htmlspecialchars($_SESSION['admin_avatar']);
                                }
                                
                                if ($avatar_file && file_exists($avatar_file)): ?>
                                    <img src="<?php echo $avatar_file; ?>" 
                                         alt="<?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?>">
                                <?php else: ?>
                                    <div style="background: linear-gradient(135deg, var(--spice-blue), var(--spice-purple)); 
                                                width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 3rem; color: white;">
                                            <?php echo strtoupper(substr($admin['username'] ?? 'A', 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display: none;" 
                                           onchange="document.getElementById('avatarForm').submit()">
                                    <input type="hidden" name="update_avatar" value="1">
                                    <div class="avatar-upload" onclick="document.getElementById('avatarInput').click()">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </form>
                            </div>
                            
                            <h4 class="mb-2"><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></h4>
                            <p class="text-muted mb-3">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($admin['email'] ?? 'No email'); ?>
                            </p>
                            
                            <div class="d-flex justify-content-center gap-2 mb-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-edit me-1"></i> Edit Profile
                                </button>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="fas fa-key me-1"></i> Change Password
                                </button>
                            </div>
                            
                            <div class="text-start">
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Admin ID:</span>
                                    <span class="info-value">#<?php echo $admin['admin_id']; ?></span>
                                </div>
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Username:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($admin['username'] ?? 'Not set'); ?></span>
                                </div>
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($admin['email'] ?? 'Not set'); ?></span>
                                </div>
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Role:</span>
                                    <span class="info-value">
                                        <span class="badge bg-<?php echo $admin['role'] == 'super_admin' ? 'warning' : 'info'; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $admin['role'])); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Member Since:</span>
                                    <span class="info-value"><?php echo formatDate($admin['created_at']); ?></span>
                                </div>
                                <div class="profile-info-item d-flex">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value">
                                        <span class="badge bg-<?php echo $admin['status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Security Settings -->
                    <div class="col-md-8">
                        <div class="profile-card">
                            <h5 class="mb-4">
                                <i class="fas fa-shield-alt me-2" style="color: var(--spice-green);"></i>
                                Account Security Settings
                            </h5>
                            
                            <div class="security-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-key me-2"></i> Password Management
                                        </h6>
                                        <p class="mb-0 small text-muted">Change your account password</p>
                                    </div>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                        Change Password
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="password-strength-bar strength-medium"></div>
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Use a strong password with at least 6 characters including numbers and symbols.
                                </p>
                            </div>
                            
                            <div class="security-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-user-shield me-2"></i> Two-Factor Authentication
                                        </h6>
                                        <p class="mb-0 small text-muted">Add an extra layer of security to your account</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="twoFactorSwitch">
                                        <label class="form-check-label" for="twoFactorSwitch">Enable 2FA</label>
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    When enabled, you'll need to enter a verification code from your phone when signing in.
                                </p>
                            </div>
                            
                            <div class="security-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-envelope me-2"></i> Email Notifications
                                        </h6>
                                        <p class="mb-0 small text-muted">Receive security alerts via email</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="emailAlertsSwitch" checked>
                                        <label class="form-check-label" for="emailAlertsSwitch">Enabled</label>
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You'll receive email notifications for important security events and account activities.
                                </p>
                            </div>
                            
                            <div class="security-item">
                                <h6 class="mb-3">
                                    <i class="fas fa-clock me-2"></i> Session Timeout
                                </h6>
                                <p class="small text-muted mb-2">Auto-logout after inactivity</p>
                                <select class="form-select form-select-sm" style="max-width: 200px;">
                                    <option value="15">15 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="120">2 hours</option>
                                    <option value="0">Never (not recommended)</option>
                                </select>
                                <p class="small text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    For security, your session will automatically expire after the selected time of inactivity.
                                </p>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="mb-3">
                                    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                    Account Actions
                                </h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                        <i class="fas fa-user-slash me-2"></i> Deactivate Account
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Deactivating will temporarily disable your account. Contact super admin to reactivate.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i> Edit Profile
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_profile">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="passwordForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-key me-2"></i> Change Password
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password *</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" required 
                                   onkeyup="checkPasswordStrength()">
                            <div class="password-strength mt-2">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small class="text-muted">Password must be at least 6 characters long</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="change_password">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Deactivate Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-circle me-2"></i> Warning
                        </h6>
                        <p class="mb-0">
                            Deactivating your account will:
                        </p>
                        <ul class="mb-0 mt-2">
                            <li>Log you out immediately</li>
                            <li>Prevent you from logging in again</li>
                            <li>Keep your data in the system</li>
                            <li>Require super admin to reactivate</li>
                        </ul>
                    </div>
                    <p>Are you sure you want to deactivate your account?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deactivateAccount()">
                        <i class="fas fa-user-slash me-2"></i> Deactivate Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        // Deactivate account
        function deactivateAccount() {
            if (confirm('Are you absolutely sure? This action cannot be undone easily!')) {
                // Simple implementation - you can enhance this with AJAX
                alert('Account deactivation feature would be implemented here. For now, please contact super admin.');
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>