<?php
require_once '../config/db.php';
require_once '../config/auth_check.php';

// Redirect if not customer
if ($_SESSION['role'] !== 'customer') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$customer_id = $_SESSION['user_id'];

// Get customer data
$customer_query = "SELECT * FROM users WHERE user_id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();

// Get recent orders
$orders_query = "SELECT order_id, final_total, status, created_at FROM orders WHERE customer_id = ? ORDER BY order_id DESC LIMIT 5";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Get cart count
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];

// Get statistics
$total_orders_query = "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?";
$total_orders_stmt = $conn->prepare($total_orders_query);
$total_orders_stmt->bind_param("i", $customer_id);
$total_orders_stmt->execute();
$total_orders_result = $total_orders_stmt->get_result();
$total_orders = $total_orders_result->fetch_assoc()['total'];

$pending_orders_query = "SELECT COUNT(*) as pending FROM orders WHERE customer_id = ? AND status = 'Pending'";
$pending_orders_stmt = $conn->prepare($pending_orders_query);
$pending_orders_stmt->bind_param("i", $customer_id);
$pending_orders_stmt->execute();
$pending_orders_result = $pending_orders_stmt->get_result();
$pending_orders = $pending_orders_result->fetch_assoc()['pending'];

$total_spent_query = "SELECT COALESCE(SUM(final_total), 0) as total_spent FROM orders WHERE customer_id = ? AND status != 'Cancelled'";
$total_spent_stmt = $conn->prepare($total_spent_query);
$total_spent_stmt->bind_param("i", $customer_id);
$total_spent_stmt->execute();
$total_spent_result = $total_spent_stmt->get_result();
$total_spent = $total_spent_result->fetch_assoc()['total_spent'];

// Get wishlist count
$wishlist_query = "SELECT COUNT(*) as wishlist_count FROM wishlist WHERE customer_id = ?";
$wishlist_stmt = $conn->prepare($wishlist_query);
$wishlist_stmt->bind_param("i", $customer_id);
$wishlist_stmt->execute();
$wishlist_result = $wishlist_stmt->get_result();
$wishlist_count = $wishlist_result->fetch_assoc()['wishlist_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-light: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--spice-light);
            color: #333;
        }
        
        .dashboard-container {
            min-height: calc(100vh - 120px);
        }
        
        /* Sidebar Styling */
        .sidebar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 20px 0;
            height: fit-content;
        }
        
        .sidebar .nav-link {
            color: var(--spice-dark);
            padding: 12px 25px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: var(--spice-red);
            color: white !important;
            box-shadow: 0 4px 12px rgba(184, 92, 56, 0.3);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Card Styling */
        .dashboard-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .dashboard-card .card-header {
            background: white;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            padding: 20px;
            font-weight: 600;
            color: var(--spice-dark);
            font-size: 1.1rem;
        }
        
        .dashboard-card .card-body {
            padding: 25px;
        }
        
        /* Stat Cards */
        .stat-card {
            text-align: center;
            padding: 25px 15px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }
        
        .stat-orders .stat-icon { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        .stat-cart .stat-icon { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .stat-pending .stat-icon { background: rgba(243, 156, 18, 0.1); color: var(--spice-gold); }
        .stat-spent .stat-icon { background: rgba(184, 92, 56, 0.1); color: var(--spice-red); }
        .stat-wishlist .stat-icon { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--spice-dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.1), rgba(39, 174, 96, 0.1));
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(184, 92, 56, 0.2);
        }
        
        .welcome-banner h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-banner p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Table Styling */
        .orders-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .orders-table th {
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-dark);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        .orders-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .orders-table tr:hover {
            background: rgba(184, 92, 56, 0.05);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .status-pending { background: rgba(149, 165, 166, 0.2); color: #636e72; }
        .status-confirmed { background: rgba(243, 156, 18, 0.2); color: #d35400; }
        .status-processing { background: rgba(52, 152, 219, 0.2); color: var(--spice-blue); }
        .status-shipped { background: rgba(155, 89, 182, 0.2); color: #8e44ad; }
        .status-delivered { background: rgba(39, 174, 96, 0.2); color: var(--spice-green); }
        .status-cancelled { background: rgba(231, 76, 60, 0.2); color: #c0392b; }
        
        /* Profile Section */
        .profile-img-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--spice-red);
            padding: 3px;
        }
        
        .profile-detail {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .profile-detail:hover {
            background: rgba(184, 92, 56, 0.05);
        }
        
        .profile-detail i {
            width: 24px;
            color: var(--spice-red);
            margin-right: 12px;
        }
        
        .profile-detail span {
            flex: 1;
        }
        
        /* Buttons */
        .btn-spice {
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-spice:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-spice-outline {
            color: var(--spice-red);
            border: 2px solid var(--spice-red);
            background: transparent;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-spice-outline:hover {
            background: var(--spice-red);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            
            .welcome-banner {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .orders-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation (Reuse your home page navigation) -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-pepper-hot me-2"></i>SpiceCeylon
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i> Cart 
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="wishlist.php"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container-fluid mt-4 dashboard-container">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <div class="text-center mb-4">
                        <div class="profile-img-container">
                            <img class="profile-img" 
                                src="<?php 
                                    if (!empty($customer['profile_image']) && file_exists('../assets/images/profile_images/' . $customer['profile_image'])) {
                                        echo '../assets/images/profile_images/' . $customer['profile_image'];
                                    } else {
                                        echo '../assets/images/default-profile.jpg';
                                    }
                                ?>" 
                                alt="Profile"
                                onerror="this.src='../assets/images/default-profile.jpg'">
                        </div>
                        <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($customer['name']); ?></h5>
                        <p class="text-muted small">Customer</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">
                                <i class="fas fa-store"></i>
                                Shop Spices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                My Cart
                                <?php if($cart_count > 0): ?>
                                    <span class="badge bg-primary ms-1"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-clipboard-list"></i>
                                My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="wishlist.php">
                                <i class="fas fa-heart"></i>
                                My Wishlist
                                <?php if($wishlist_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $wishlist_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- Add these to the existing sidebar links -->
<li class="nav-item">
    <a class="nav-link" href="messages.php">
        <i class="fas fa-envelope"></i>
        My Messages
        <?php
        // Get unread message count
        $unread_query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND receiver_role = 'customer' AND is_read = FALSE";
        $unread_stmt = $conn->prepare($unread_query);
        $unread_stmt->bind_param("i", $customer_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_count = $unread_result->fetch_assoc()['count'];
        ?>
        <?php if($unread_count > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="notifications.php">
        <i class="fas fa-bell"></i>
        Notifications
        <?php
        // Get unread notification count
        $unread_notif_query = "SELECT COUNT(DISTINCT n.notification_id) as count 
                              FROM notifications n
                              LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                              WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                              AND (uns.is_read IS NULL OR uns.is_read = FALSE)";
        $unread_notif_stmt = $conn->prepare($unread_notif_query);
        $unread_notif_stmt->bind_param("i", $customer_id);
        $unread_notif_stmt->execute();
        $unread_notif_result = $unread_notif_stmt->get_result();
        $unread_notif_count = $unread_notif_result->fetch_assoc()['count'];
        ?>
        <?php if($unread_notif_count > 0): ?>
            <span class="badge bg-warning ms-1"><?php echo $unread_notif_count; ?></span>
        <?php endif; ?>
    </a>
</li>
                        <li class="nav-item">
                            <a class="nav-link" href="request.php">
                                <i class="fas fa-plus-circle"></i>
                                Request Product
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user-cog"></i>
                                Profile Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h1 class="display-6">Welcome back, <?php echo htmlspecialchars($customer['name']); ?>! <i class="fas fa-pepper-hot text-danger"></i></h1>
                            <p class="lead">Track your spice orders, manage your cart, and explore new flavors.</p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <a href="home.php" class="btn btn-spice">
                                <i class="fas fa-shopping-bag me-1"></i>
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card stat-orders">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_orders; ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card stat-cart">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value"><?php echo $cart_count; ?></div>
                            <div class="stat-label">Cart Items</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card stat-pending">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value"><?php echo $pending_orders; ?></div>
                            <div class="stat-label">Pending Orders</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card stat-spent">
                            <div class="stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-value">Rs. <?php echo number_format($total_spent, 0); ?></div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card stat-wishlist">
                            <div class="stat-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="stat-value"><?php echo $wishlist_count; ?></div>
                            <div class="stat-label">Wishlist Items</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-card" style="background: linear-gradient(135deg, rgba(184, 92, 56, 0.1), rgba(39, 174, 96, 0.1));">
                            <div class="stat-icon" style="background: rgba(184, 92, 56, 0.2); color: var(--spice-red);">
                                <i class="fas fa-pepper-hot"></i>
                            </div>
                            <div class="stat-value">45+</div>
                            <div class="stat-label">Spice Varieties</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Recent Orders
                    </div>
                    <div class="card-body">
                        <?php if($orders_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($order = $orders_result->fetch_assoc()): 
                                            $status_class = 'status-' . strtolower($order['status']);
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><span class="fw-bold text-success">Rs. <?php echo number_format($order['final_total'], 2); ?></span></td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (isset($order['created_at'])) {
                                                        echo date('M j, Y', strtotime($order['created_at']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-spice-outline btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-4">
                                <a href="orders.php" class="btn btn-spice">
                                    <i class="fas fa-list me-1"></i>View All Orders
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="fas fa-shopping-bag fa-4x text-muted"></i>
                                </div>
                                <h4 class="text-muted mb-3">No orders yet</h4>
                                <p class="text-muted mb-4">Start your spice journey with authentic Sri Lankan flavors</p>
                                <a href="home.php" class="btn btn-spice">
                                    <i class="fas fa-store me-1"></i>Start Shopping
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="home.php" class="text-decoration-none">
                                    <div class="text-center p-3 rounded border hover-shadow">
                                        <i class="fas fa-store fa-2x text-primary mb-2"></i>
                                        <h6>Shop Spices</h6>
                                        <small class="text-muted">Browse collection</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="cart.php" class="text-decoration-none">
                                    <div class="text-center p-3 rounded border hover-shadow">
                                        <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                                        <h6>View Cart</h6>
                                        <small class="text-muted"><?php echo $cart_count; ?> items</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="request.php" class="text-decoration-none">
                                    <div class="text-center p-3 rounded border hover-shadow">
                                        <i class="fas fa-plus-circle fa-2x text-warning mb-2"></i>
                                        <h6>Request Spice</h6>
                                        <small class="text-muted">Can't find it?</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="profile.php" class="text-decoration-none">
                                    <div class="text-center p-3 rounded border hover-shadow">
                                        <i class="fas fa-user-cog fa-2x text-info mb-2"></i>
                                        <h6>Profile</h6>
                                        <small class="text-muted">Update details</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer (Similar to home page) -->
    <footer style="background: var(--spice-dark); color: white; padding: 50px 0 20px; margin-top: 80px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen since 2020.</p>
                    <div class="mt-3">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Quick Links</h4>
                    <div class="footer-links">
                        <a href="home.php" class="text-light text-decoration-none d-block mb-2">Home</a>
                        <a href="dashboard.php" class="text-light text-decoration-none d-block mb-2">Dashboard</a>
                        <a href="cart.php" class="text-light text-decoration-none d-block mb-2">Shopping Cart</a>
                        <a href="orders.php" class="text-light text-decoration-none d-block mb-2">My Orders</a>
                        <a href="about.php" class="text-light text-decoration-none d-block mb-2">About Us</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Need Help?</h4>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Colombo, Sri Lanka</p>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> support@spiceceylon.com</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Sat: 8:00 AM - 6:00 PM</p>
                </div>
            </div>
            <hr class="mt-4 mb-3">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SpiceCeylon. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh cart count every 30 seconds
        setInterval(function() {
            fetch('../actions/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    // Update cart badge in navigation
                    const cartBadge = document.querySelector('.nav-link[href="cart.php"] .badge');
                    if (data.count > 0) {
                        if (cartBadge) {
                            cartBadge.textContent = data.count;
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-danger';
                            badge.textContent = data.count;
                            document.querySelector('.nav-link[href="cart.php"]').appendChild(badge);
                        }
                    } else if (cartBadge) {
                        cartBadge.remove();
                    }
                    
                    // Update cart count in stats
                    const cartStat = document.querySelector('.stat-cart .stat-value');
                    if (cartStat) {
                        cartStat.textContent = data.count;
                    }
                });
        }, 30000);
        
        // Add hover effects
        document.querySelectorAll('.hover-shadow').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                this.style.transform = 'translateY(-3px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

<?php
// Close connections
$customer_stmt->close();
$orders_stmt->close();
$cart_stmt->close();
$total_orders_stmt->close();
$pending_orders_stmt->close();
$total_spent_stmt->close();
$wishlist_stmt->close();
$conn->close();
?>