<?php
session_start();
include "../config/db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Validate
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Check if email exists in users table
        $userQuery = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND role = ?");
        $userQuery->bind_param("ss", $email, $role);
        $userQuery->execute();
        $userResult = $userQuery->get_result();
        
        // Check if email exists in admins table (only if role is admin)
        $adminExists = false;
        if ($role == 'admin') {
            $adminQuery = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
            $adminQuery->bind_param("s", $email);
            $adminQuery->execute();
            $adminResult = $adminQuery->get_result();
            $adminExists = ($adminResult->num_rows > 0);
        }
        
        if ($userResult->num_rows > 0 || $adminExists) {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($role == 'admin') {
                $update = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
                $update->bind_param("ss", $hashed_password, $email);
            } else {
                $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = ?");
                $update->bind_param("sss", $hashed_password, $email, $role);
            }
            
            if ($update->execute()) {
                $_SESSION['reset_success'] = "Password reset successful! You can now login with your new password.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again.";
            }
        } else {
            $error = "Email not found for selected role!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpiceCeylon - Reset Password</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/auth.css">
<style>
    .auth-link {
        text-align: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }
    
    .auth-link p {
        color: #666;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .btn-alt {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: transparent;
        color: #b85c38;
        border: 2px solid #b85c38;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        width: auto;
        min-width: 160px;
    }
    
    .btn-alt:hover {
        background: #b85c38;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(184, 92, 56, 0.2);
    }
    
    .btn-alt i {
        font-size: 14px;
    }
</style>
</head>
<body>
    <!-- Background Spice Icons -->
    <div class="bg-spice">
        <div class="spice-1"><i class="fas fa-pepper-hot"></i></div>
        <div class="spice-2"><i class="fas fa-seedling"></i></div>
        <div class="spice-3"><i class="fas fa-leaf"></i></div>
        <div class="spice-4"><i class="fas fa-mortar-pestle"></i></div>
    </div>

    <a href="login.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Login
    </a>

    <div class="auth-container">
        <div class="auth-box" style="max-width: 450px;">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-pepper-hot"></i>
                    <span>SpiceCeylon</span>
                </div>
                <h2>Reset Password</h2>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">Enter your details to reset password</p>
            </div>
            
            <div class="auth-body">
                <?php if($error != ""): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-group">
                        <label for="email"><i class="fas fa-envelope"></i>Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required>
                    </div>

                    <div class="input-group">
                        <label for="role"><i class="fas fa-user-tag"></i>Account Type</label>
                        <select name="role" id="role" required>
                            <option value="">Select Account Type</option>
                            <option value="customer">Customer</option>
                            <option value="farmer">Farmer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="input-group password-toggle">
                        <label for="password"><i class="fas fa-lock"></i>New Password</label>
                        <input type="password" name="password" id="password" placeholder="Enter new password" minlength="6" required>
                        <span class="toggle-icon" onclick="togglePassword('password', 'toggleIcon1')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </span>
                    </div>

                    <div class="input-group password-toggle">
                        <label for="confirm_password"><i class="fas fa-lock"></i>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" minlength="6" required>
                        <span class="toggle-icon" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn-auth btn-login">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
                <!-- Added Remember password link -->
                <div class="auth-link">
                    <p>Remember your password?</p>
                    <a href="login.php" class="btn-alt">
                        <i class="fas fa-sign-in-alt"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>