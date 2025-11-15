<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

// Optional: Fetch user info
include "../config/db.php";
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE user_id='$user_id'");
$user = $user_query->fetch_assoc();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpiceCeylon Customer</title>
<link rel="stylesheet" href="home.css">
</head>
<body>
<header>
    <h1>SpiceCeylon</h1>
    <nav>
        <a href="home.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="browse.php">Browse</a>
        <a href="cart.php">Cart</a>
        <a href="orders.php">Orders</a>
        <a href="request_product.php">Request</a>
        <a href="profile.php">Profile</a>
    </nav>
    <div class="user-info">
        Welcome, <?php echo htmlspecialchars($user['name']); ?> 
        <a href="../auth/logout.php" class="logout-btn">Logout</a>
    </div>
</header>
<main>
