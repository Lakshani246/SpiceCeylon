<?php
session_start();
include "../config/db.php";

$error = "";
$success = "";
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Verify token
if (empty($token)) {
    header("Location: forgot-password.php");
    exit();
}

// Check if token is valid and not expired
$query = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expiry > NOW()");
$query->bind_param("s", $token);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid or expired reset link! Please try again.";
    header("Location: forgot-password.php");
    exit();
}

$resetData = $result->fetch_assoc();

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password in appropriate table
        if ($resetData['user_type'] == 'admin') {
            $update = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
        } else {
            $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        }
        
        $update->bind_param("ss", $hashed_password, $resetData['email']);
        
        if ($update->execute()) {
            // Mark token as used
            $markUsed = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?");
            $markUsed->bind_param("i", $resetData['reset_id']);
            $markUsed->execute();
            
            $_SESSION['reset_success'] = "Password reset successful! You can now login with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
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
                <p style="color: #666; font-size: 14px; margin-top: 5px;">Enter your new password</p>
            </div>
            
            <div class="auth-body">
                <?php if($error != ""): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
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