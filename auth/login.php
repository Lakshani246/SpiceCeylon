<?php
session_start();
include "../config/db.php";

$error = "";
$email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $remember = isset($_POST['remember']) ? true : false;

    // Admin login
    if ($role === "admin") {
        $query = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $query->bind_param("s", $email);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['username'];
                $_SESSION['role'] = $admin['role'];
                $_SESSION['is_admin'] = true;
                
                // Remember Me functionality
                if ($remember) {
                    setcookie('remember_email', $email, time() + (86400 * 30), "/"); // 30 days
                } else {
                    setcookie('remember_email', '', time() - 3600, "/"); // Delete cookie
                }
                
                header("Location: ../admin/dashboard.php");
                exit();
            } 
        }
        $_SESSION['error'] = "Invalid admin credentials!";
        header("Location: ../index.php");
        exit();
    }

    // Customer/Farmer login
    else {
        // Use prepared statement to prevent SQL injection
        $query = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? AND is_registered = 1");
        $query->bind_param("ss", $email, $role);
        $query->execute();
        $result = $query->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;
                $_SESSION['user_name'] = $user['name'];
                
                // Remember Me functionality
                if ($remember) {
                    setcookie('remember_email', $email, time() + (86400 * 30), "/"); // 30 days
                    // Store user role for auto-select
                    setcookie('remember_role', $role, time() + (86400 * 30), "/");
                } else {
                    setcookie('remember_email', '', time() - 3600, "/");
                    setcookie('remember_role', '', time() - 3600, "/");
                }
                
                // Update last login time if you have the column
                $update = $conn->prepare("UPDATE users SET last_notification_check = NOW() WHERE user_id = ?");
                $update->bind_param("i", $user['user_id']);
                $update->execute();
                
                if ($role == "farmer") {
                    header("Location: ../farmer/dashboard.php");
                } else {
                    header("Location: ../customer/home.php");
                }
                exit;
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "User not found or not registered!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpiceCeylon - Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/auth.css">
<style>
/* Additional styles for remember me and forgot password */
.remember-forgot-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 14px;
}

.remember-me {
    display: flex;
    align-items: center;
}

.remember-me input[type="checkbox"] {
    width: auto;
    margin-right: 8px;
    accent-color: var(--spice-orange);
}

.remember-me label {
    display: inline;
    margin-bottom: 0;
    cursor: pointer;
}

.forgot-password {
    color: var(--spice-orange);
    text-decoration: none;
    transition: color 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.forgot-password:hover {
    color: var(--spice-brown);
    text-decoration: underline;
}

.forgot-password i {
    font-size: 12px;
}

/* Auto-select role based on cookie */
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

    <a href="../index.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Home
    </a>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-pepper-hot"></i>
                    <span>SpiceCeylon</span>
                </div>
                <h2>Welcome Back</h2>
            </div>
            
            <div class="auth-body">
                <?php if($error != ""): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['reset_success'])): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i><?php echo $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-group">
                        <label for="email"><i class="fas fa-envelope"></i>Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter your email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    
                    <div class="input-group password-toggle">
                        <label for="password"><i class="fas fa-lock"></i>Password</label>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <span class="toggle-icon" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    
                    <div class="input-group">
                        <label for="role"><i class="fas fa-user-tag"></i>Login As</label>
                        <select name="role" id="role" required>
                            <option value="">Select Role</option>
                            <option value="customer" <?php echo (isset($_COOKIE['remember_role']) && $_COOKIE['remember_role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                            <option value="farmer" <?php echo (isset($_COOKIE['remember_role']) && $_COOKIE['remember_role'] == 'farmer') ? 'selected' : ''; ?>>Farmer</option>
                            <option value="admin" <?php echo (isset($_COOKIE['remember_role']) && $_COOKIE['remember_role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <!-- Remember Me & Forgot Password Section -->
                    <div class="remember-forgot-container">
                        <div class="remember-me">
                            <input type="checkbox" name="remember" id="remember" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                            <label for="remember">Remember Me</label>
                        </div>
                        <a href="forgot_password.php" class="forgot-password">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-auth btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="auth-link">
                    <p>Don't have an account?</p>
                    <a href="register.php" class="btn-alt">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                </div>
                
                <div class="auth-footer">
                    &copy; <?php echo date('Y'); ?> SpiceCeylon. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Add focus effects
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.borderColor = 'var(--spice-blue)';
                this.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.1)';
            });
            
            element.addEventListener('blur', function() {
                this.style.borderColor = '#e9ecef';
                this.style.boxShadow = 'none';
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>