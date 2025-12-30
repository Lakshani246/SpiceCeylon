<?php
session_start();
include "../config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

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
        $result = $conn->query("SELECT * FROM users WHERE email='$email' AND role='$role' AND is_registered=1");
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;
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
            $error = "User not found!";
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

                <form method="POST" action="">
                    <div class="input-group">
                        <label for="email"><i class="fas fa-envelope"></i>Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required>
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
                            <option value="customer">Customer</option>
                            <option value="farmer">Farmer</option>
                            <option value="admin">Admin</option>
                        </select>
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
    </script>
</body>
</html>