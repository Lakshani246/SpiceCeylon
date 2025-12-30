<?php
include "../config/db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    $farm_location = ($role == "farmer") ? $_POST['farm_location'] : NULL;

    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $error = "Email already registered!";
    } else {
        $sql = "INSERT INTO users (name, email, password, phone, address, role, farm_location, is_registered)
                VALUES ('$name', '$email', '$password', '$phone', '$address', '$role', '$farm_location', 1)";

        if ($conn->query($sql)) {
            $success = "Registration successful! Redirecting to login...";
            header("refresh:2; url=login.php");
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpiceCeylon - Register</title>
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

    <div class="auth-container reg-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-pepper-hot"></i>
                    <span>SpiceCeylon</span>
                </div>
                <h2>Create Your Account</h2>
            </div>
            
            <div class="auth-body">
                <?php if($error != ""): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success != ""): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-columns">
                        <!-- Left Column -->
                        <div class="column-left">
                            <div class="input-group">
                                <label for="name"><i class="fas fa-user"></i>Full Name</label>
                                <input type="text" name="name" id="name" placeholder="Enter your full name" required>
                            </div>
                            
                            <div class="input-group">
                                <label for="email"><i class="fas fa-envelope"></i>Email Address</label>
                                <input type="email" name="email" id="email" placeholder="Enter your email" required>
                            </div>
                            
                            <div class="input-group password-toggle">
                                <label for="password"><i class="fas fa-lock"></i>Password</label>
                                <input type="password" name="password" id="password" placeholder="Create a password" required>
                                <span class="toggle-icon" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="column-right">
                            <div class="input-group">
                                <label for="phone"><i class="fas fa-phone"></i>Phone Number</label>
                                <input type="text" name="phone" id="phone" placeholder="Enter phone number" required>
                            </div>
                            
                            <div class="input-group">
                                <label for="address"><i class="fas fa-map-marker-alt"></i>Address</label>
                                <input type="text" name="address" id="address" placeholder="Enter your address" required>
                            </div>
                            
                            <div class="input-group">
                                <label for="role"><i class="fas fa-user-tag"></i>Register As</label>
                                <select name="role" id="role" onchange="toggleFarmLocation()" required>
                                    <option value="">Select Role</option>
                                    <option value="customer">Customer</option>
                                    <option value="farmer">Farmer</option>
                                </select>
                            </div>
                            
                            <div class="input-group" id="farm_location_group">
                                <label for="farm_location"><i class="fas fa-tractor"></i>Farm Location</label>
                                <input type="text" name="farm_location" id="farm_location" placeholder="Enter farm location">
                            </div>
                        </div>
                    </div>
                    
                    <div class="column-divider">Account Details</div>
                    
                    <button type="submit" class="btn-auth btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="auth-link">
                    <p>Already have an account?</p>
                    <a href="login.php" class="btn-alt">
                        <i class="fas fa-sign-in-alt"></i> Login Here
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

        function toggleFarmLocation() {
            const role = document.getElementById('role').value;
            const farmGroup = document.getElementById('farm_location_group');
            if(role === 'farmer') {
                farmGroup.style.display = 'block';
            } else {
                farmGroup.style.display = 'none';
            }
        }

        // Initialize farm location visibility
        document.addEventListener('DOMContentLoaded', function() {
            toggleFarmLocation();
        });

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