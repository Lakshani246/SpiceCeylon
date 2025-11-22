<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch user info and cart count
include '../config/db.php';

$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE user_id='$user_id'");
$user = $user_query->fetch_assoc();

// Get cart count
$cart_count = 0;
$cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id='$user_id'");
if ($cart_query) {
    $cart_data = $cart_query->fetch_assoc();
    $cart_count = $cart_data['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpiceCeylon - Customer Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="../assets/css/home.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: #b85c38; padding: 15px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand fw-bold" href="home.php" style="font-size: 28px; margin: 0;">
                <i class="fas fa-pepper-hot me-2"></i>
                SpiceCeylon
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="home.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="dashboard.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="cart.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-shopping-cart me-1"></i>Cart
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-light text-dark ms-1"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="orders.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-clipboard-list me-1"></i>Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="request_product.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-plus-circle me-1"></i>Request
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="about.php" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-info-circle me-1"></i>About Us
                        </a>
                    </li>
                </ul>

                <!-- User Section -->
                <ul class="navbar-nav ms-auto">
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-bold" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false" style="color: #fff; margin: 0 12px; font-weight: 600;">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <span class="dropdown-item-text text-muted small">
                                    <i class="fas fa-envelope me-2"></i>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="orders.php">
                                    <i class="fas fa-clipboard-list me-2"></i>My Orders
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="request_product.php">
                                    <i class="fas fa-plus-circle me-2"></i>Request Product
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger fw-bold" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Optional: Alert for messages -->
    <?php if(isset($_SESSION['message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Custom Styles for Hover Effects -->
    <style>
    .nav-link {
        transition: 0.3s !important;
    }
    
    .nav-link:hover {
        color: #ffe1c4 !important;
    }
    
    .dropdown-item.text-danger {
        color: #b85c38 !important;
    }
    
    .dropdown-item.text-danger:hover {
        background: #ffe1c4 !important;
        color: #b85c38 !important;
    }
    
    .badge {
        background: #fff !important;
        color: #b85c38 !important;
        font-weight: bold;
    }
    </style>