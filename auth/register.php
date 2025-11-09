<?php
include "../config/db.php";

$error = "";
$success = "";

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    $farm_location = ($role == "farmer") ? $_POST['farm_location'] : NULL;

    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $error = "Email already registered!";
    } else {
        $sql = "INSERT INTO users (name, email, password, phone, address, role, farm_location, is_registered)
                VALUES ('$name', '$email', '$password', '$phone', '$address', '$role', '$farm_location', 1)";

        if ($conn->query($sql)) {
            $success = "Registration successful! Redirecting to login...";
            header("refresh:2; url=login.php"); // Redirect after 2 seconds
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
<title>SpiceCeylon Registration</title>
<style>
/* General Reset */
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { height:100vh; background: linear-gradient(to bottom right, #fbeec1, #f2a65a); display:flex; justify-content:center; align-items:center; }

/* Registration Box */
.reg-container { width:100%; max-width:500px; padding:20px; }
.reg-box { background:#fff8f0; padding:40px 30px; border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.reg-box h2 { text-align:center; color:#b85c38; margin-bottom:25px; font-size:28px; }

/* Input Fields */
.input-group { margin-bottom:20px; }
.input-group label { display:block; color:#8c5e3c; margin-bottom:5px; font-weight:600; }
.input-group input, .input-group select { width:100%; padding:12px 15px; border:1px solid #e0c097; border-radius:8px; outline:none; font-size:16px; transition:0.3s; }
.input-group input:focus, .input-group select:focus { border-color:#b85c38; box-shadow:0 0 8px rgba(184,92,56,0.4); }

/* Button */
button { width:100%; padding:12px; background-color:#b85c38; color:#fff; border:none; border-radius:8px; font-size:18px; cursor:pointer; transition:0.3s; }
button:hover { background-color:#a14c2e; }

/* Error / Success Messages */
.message { padding:10px; margin-bottom:15px; border-radius:8px; font-size:14px; }
.error { background-color:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
.success { background-color:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }

/* Footer */
.footer-text { text-align:center; margin-top:15px; font-size:12px; color:#8c5e3c; }

/* Farm Location Field Toggle */
#farm_location_group { display:none; }
</style>
<script>
// Show farm location input only if role is farmer
function toggleFarmLocation() {
    const role = document.getElementById('role').value;
    const farmGroup = document.getElementById('farm_location_group');
    if(role === 'farmer') {
        farmGroup.style.display = 'block';
    } else {
        farmGroup.style.display = 'none';
    }
}
</script>
</head>
<body>
<div class="reg-container">
    <div class="reg-box">
        <h2>SpiceCeylon Registration</h2>

        <!-- Display messages -->
        <?php if($error != ""): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success != ""): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label for="name">Full Name</label>
                <input type="text" name="name" id="name" placeholder="Enter your full name" required>
            </div>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter your email" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter a password" required>
            </div>
            <div class="input-group">
                <label for="phone">Phone</label>
                <input type="text" name="phone" id="phone" placeholder="Enter your phone number" required>
            </div>
            <div class="input-group">
                <label for="address">Address</label>
                <input type="text" name="address" id="address" placeholder="Enter your address" required>
            </div>
            <div class="input-group">
                <label for="role">Register As</label>
                <select name="role" id="role" onchange="toggleFarmLocation()" required>
                    <option value="">Select Role</option>
                    <option value="customer">Customer</option>
                    <option value="farmer">Farmer</option>
                </select>
            </div>
            <div class="input-group" id="farm_location_group">
                <label for="farm_location">Farm Location</label>
                <input type="text" name="farm_location" id="farm_location" placeholder="Enter your farm location">
            </div>
            <button type="submit">Register</button>
        </form>

        <div class="footer-text">
            Already registered? <a href="login.php" style="color:#b85c38; text-decoration:none;">Login here</a>
        </div>
    </div>
</div>
</body>
</html>
