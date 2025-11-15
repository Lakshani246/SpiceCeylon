<?php
// Suppress notices/warnings
error_reporting(E_ERROR | E_PARSE);
session_start();
include "../config/db.php";

$error = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    if ($role == "admin") {
        $result = $conn->query("SELECT * FROM admins WHERE email='$email'");
    } else {
        $result = $conn->query("SELECT * FROM users WHERE email='$email' AND role='$role'");
    }

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $role;

            // Redirect based on role
            if ($role == 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($role == 'farmer') {
                header("Location: ../farmer/dashboard.php");
            } else {
                header("Location: ../customer/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpiceCeylon Login</title>
<style>
/* General Reset */
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { height:100vh; background: linear-gradient(to bottom right, #fbeec1, #f2a65a); display:flex; justify-content:center; align-items:center; }

/* Login Box */
.login-container { width:100%; max-width:400px; padding:20px; }
.login-box { background:#fff8f0; padding:40px 30px; border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,0.15); text-align:center; }
.login-box h2 { color:#b85c38; margin-bottom:25px; font-size:28px; }

/* Input Fields */
.input-group { margin-bottom:20px; text-align:left; }
.input-group label { display:block; color:#8c5e3c; margin-bottom:5px; font-weight:600; }
.input-group input, .input-group select { width:100%; padding:12px 15px; border:1px solid #e0c097; border-radius:8px; outline:none; font-size:16px; transition:0.3s; }
.input-group input:focus, .input-group select:focus { border-color:#b85c38; box-shadow:0 0 8px rgba(184,92,56,0.4); }

/* Button */
button { width:100%; padding:12px; background-color:#b85c38; color:#fff; border:none; border-radius:8px; font-size:18px; cursor:pointer; transition:0.3s; }
button:hover { background-color:#a14c2e; }

/* Error Message */
.error { background-color:#f8d7da; color:#842029; padding:10px; margin-bottom:15px; border-radius:8px; border:1px solid #f5c2c7; font-size:14px; }

/* Footer */
.footer-text { margin-top:15px; font-size:12px; color:#8c5e3c; }

/* Register Links */
.register-links { margin-top:20px; }
.register-links a { color:#b85c38; text-decoration:none; margin:0 10px; transition:0.3s; font-weight:600; }
.register-links a:hover { color:#a14c2e; text-decoration:underline; }

/* Top Right Back Button */
.back-top-right {
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: bold;
    z-index: 999;
    transition: 0.3s ease;
}

.back-top-right:hover {
    background: rgba(0, 0, 0, 0.85);
}


</style>
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <h2>SpiceCeylon Login</h2>

        <!-- Display error if any -->
        <?php if($error != ""): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter your email" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
            </div>
            <div class="input-group">
                <label for="role">Login As</label>
                <select name="role" id="role" required>
                    <option value="">Select Role</option>
                    <option value="customer">Customer</option>
                    <option value="farmer">Farmer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit">Login</button>
        </form>

        <!-- Register Links -->
        <div class="register-links">
            <span>Not registered yet?</span>
            <a href="../auth/register.php">Register as Farmer/Customer</a> |
        </div>
        <a href="../index.php" class="back-top-right">🏠 Home</a>

        <p class="footer-text">© 2025 SpiceCeylon. All rights reserved.</p>
    </div>
</div>
</body>
</html>
