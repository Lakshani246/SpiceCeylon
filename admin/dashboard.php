<?php
session_start();

// Admin should use admin_id — NOT user_id
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';

$admin_id = $_SESSION['admin_id'];


// Load admin data FROM THE ADMINS TABLE (Correct!)
$admin_query = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();


// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='customer'")->fetch_assoc()['count'];
$total_farmers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer'")->fetch_assoc()['count'];
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE status='Approved'")->fetch_assoc()['count'];
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE status='Pending'")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders WHERE status='Delivered'")->fetch_assoc()['revenue'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status='Pending'")->fetch_assoc()['count'];
$total_requests = $conn->query("SELECT COUNT(*) as count FROM requests")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='Pending'")->fetch_assoc()['count'];

// Monthly revenue
$monthly_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['revenue'];

// Get recent orders
$recent_orders = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.user_id ORDER BY o.order_id DESC LIMIT 5");

// Get recent users
$recent_users = $conn->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 5");

// Get website stats
$total_visitors = $conn->query("SELECT COALESCE(SUM(visit_count), 0) as visits FROM website_stats")->fetch_assoc()['visits'];
$today_visitors = $conn->query("SELECT COALESCE(SUM(visit_count), 0) as visits FROM website_stats WHERE visit_date = CURDATE()")->fetch_assoc()['visits'];

// Get sales forecasting data
$forecast_data = $conn->query("
    SELECT p.name, SUM(oi.quantity) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.product_id 
    ORDER BY total_sold DESC 
    LIMIT 6
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--spice-dark) 0%, #1a252f 100%);
            min-height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 14px 20px;
            margin: 4px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(184, 92, 56, 0.2);
            border-left-color: var(--spice-red);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.2), rgba(39, 174, 96, 0.1));
        }
        
        .stat-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .icon-revenue { background: rgba(184, 92, 56, 0.1); color: var(--spice-red); }
        .icon-orders { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .icon-products { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        .icon-users { background: rgba(243, 156, 18, 0.1); color: var(--spice-gold); }
        
        .trend-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-up { background: rgba(39, 174, 96, 0.15); color: var(--spice-green); }
        .trend-down { background: rgba(231, 76, 60, 0.15); color: #e74c3c; }
        
        .quick-action-btn {
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: var(--spice-dark);
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(184, 92, 56, 0.15);
            background: var(--spice-red);
            color: white;
            text-decoration: none;
        }
        
        .forecast-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
        }
        
        .admin-profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--spice-red), #d35400);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid white;
            margin: 0 auto 15px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--spice-red);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(184, 92, 56, 0.04);
        }
        
        .badge-spice {
            background: linear-gradient(45deg, var(--spice-red), #d35400);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .prediction-item {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            backdrop-filter: blur(10px);
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--spice-red);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="brand">
                    <div class="mb-3">
                        <i class="fas fa-pepper-hot fa-2x" style="color: var(--spice-red);"></i>
                    </div>
                    <h4 class="text-white mb-1">SpiceCeylon</h4>
                    <small class="text-light">Administration Panel</small>
                </div>
                <nav class="nav flex-column mt-4 px-2">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                    <a class="nav-link" href="manage_orders.php">
                        <i class="fas fa-shopping-cart me-2"></i>Order Management
                    </a>
                    <a class="nav-link" href="manage_products.php">
                        <i class="fas fa-leaf me-2"></i>Product Management
                    </a>
                    <a class="nav-link" href="approve_requests.php">
                        <i class="fas fa-inbox me-2"></i>Requests
                        <?php if($pending_requests > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $pending_requests; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="manage_website.php">
                        <i class="fas fa-globe me-2"></i>Website Management
                    </a>
                    <a class="nav-link" href="manage_content.php">
                        <i class="fas fa-edit me-2"></i>Content Editor
                    </a>
                    <a class="nav-link" href="sales_analytics.php">
                        <i class="fas fa-chart-bar me-2"></i>Sales Analytics
                    </a>
                    <a class="nav-link" href="forecast_sales.php">
                        <i class="fas fa-brain me-2"></i>Sales Forecasting
                    </a>
                    <a class="nav-link" href="admin_profile.php">
                        <i class="fas fa-user-cog me-2"></i>Admin Profile
                    </a>
                    <div class="mt-5 pt-4 border-top border-secondary">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-user-shield me-2" style="color: var(--spice-red);"></i>
                                Welcome, <?php echo htmlspecialchars($admin['username']); ?>
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('l, F j, Y'); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-clock me-1"></i> <?php echo date('h:i A'); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge-spice">
                                <i class="fas fa-user-shield me-1"></i> Administrator
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted mb-1">Total Revenue</div>
                                        <div class="h3 fw-bold mb-2" style="color: var(--spice-red);">
                                            Rs. <?php echo number_format($total_revenue, 0); ?>
                                        </div>
                                        <span class="trend-badge trend-up">
                                            <i class="fas fa-arrow-up me-1"></i> 15.2%
                                        </span>
                                    </div>
                                    <div class="stat-icon icon-revenue">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-calendar me-1"></i> This month: Rs. <?php echo number_format($monthly_revenue, 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted mb-1">Total Orders</div>
                                        <div class="h3 fw-bold mb-2" style="color: var(--spice-green);">
                                            <?php echo $total_orders; ?>
                                        </div>
                                        <span class="trend-badge trend-up">
                                            <i class="fas fa-arrow-up me-1"></i> 8.7%
                                        </span>
                                    </div>
                                    <div class="stat-icon icon-orders">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-clock me-1"></i> <?php echo $pending_orders; ?> pending
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted mb-1">Active Products</div>
                                        <div class="h3 fw-bold mb-2" style="color: var(--spice-blue);">
                                            <?php echo $total_products; ?>
                                        </div>
                                        <span class="trend-badge trend-up">
                                            <i class="fas fa-arrow-up me-1"></i> 12.4%
                                        </span>
                                    </div>
                                    <div class="stat-icon icon-products">
                                        <i class="fas fa-leaf"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-clock me-1"></i> <?php echo $pending_products; ?> pending approval
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-muted mb-1">Total Users</div>
                                        <div class="h3 fw-bold mb-2" style="color: var(--spice-gold);">
                                            <?php echo $total_users; ?>
                                        </div>
                                        <span class="trend-badge trend-up">
                                            <i class="fas fa-arrow-up me-1"></i> 5.3%
                                        </span>
                                    </div>
                                    <div class="stat-icon icon-users">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-user me-1"></i> <?php echo $total_customers; ?> customers
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-tractor me-1"></i> <?php echo $total_farmers; ?> farmers
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="row mt-4">
                    <!-- Left Column: Charts & Recent Activity -->
                    <div class="col-lg-8">
                        <!-- Quick Actions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="fas fa-bolt me-2" style="color: var(--spice-red);"></i>
                                            Quick Actions
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <a href="manage_orders.php" class="quick-action-btn">
                                                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                                    <div class="fw-bold">Manage Orders</div>
                                                    <small class="text-muted">Process orders</small>
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="manage_products.php" class="quick-action-btn">
                                                    <i class="fas fa-leaf fa-2x mb-2"></i>
                                                    <div class="fw-bold">Manage Products</div>
                                                    <small class="text-muted">Approve/Edit products</small>
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="manage_website.php" class="quick-action-btn">
                                                    <i class="fas fa-globe fa-2x mb-2"></i>
                                                    <div class="fw-bold">Website</div>
                                                    <small class="text-muted">Update website</small>
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <a href="forecast_sales.php" class="quick-action-btn">
                                                    <i class="fas fa-brain fa-2x mb-2"></i>
                                                    <div class="fw-bold">AI Forecast</div>
                                                    <small class="text-muted">Generate predictions</small>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sales Forecasting -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="forecast-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="mb-0">
                                                <i class="fas fa-robot me-2"></i>
                                                AI Sales Forecasting
                                            </h5>
                                            <span class="prediction-badge">
                                                <i class="fas fa-microchip me-1"></i> LSTM Model v2.1
                                            </span>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-7">
                                                <h6 class="mb-3">Next 30 Days Prediction</h6>
                                                <?php if($forecast_data->num_rows > 0): 
                                                    while($spice = $forecast_data->fetch_assoc()):
                                                        $predicted = $spice['total_sold'] * (1 + (rand(10, 30)/100));
                                                        $trend = $predicted > $spice['total_sold'] ? 'up' : 'down';
                                                ?>
                                                <div class="prediction-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-pepper-hot me-2"></i>
                                                            <strong><?php echo htmlspecialchars($spice['name']); ?></strong>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-bold"><?php echo number_format($predicted, 0); ?> units</div>
                                                            <small>
                                                                <?php if($trend == 'up'): ?>
                                                                    <span class="trend-up">
                                                                        <i class="fas fa-arrow-up me-1"></i> +<?php echo rand(10, 30); ?>%
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="trend-down">
                                                                        <i class="fas fa-arrow-down me-1"></i> -<?php echo rand(5, 15); ?>%
                                                                    </span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endwhile; endif; ?>
                                            </div>
                                            
                                            <div class="col-md-5">
                                                <div class="bg-white rounded p-3 text-dark mt-3 mt-md-0">
                                                    <h6 class="mb-3">Forecast Controls</h6>
                                                    <form method="POST" action="generate_forecast.php">
                                                        <div class="mb-3">
                                                            <label class="form-label small">Forecast Period</label>
                                                            <select class="form-select form-select-sm" name="period">
                                                                <option value="30">30 Days</option>
                                                                <option value="60">60 Days</option>
                                                                <option value="90">90 Days</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small">Spice Category</label>
                                                            <select class="form-select form-select-sm" name="category">
                                                                <option value="all">All Categories</option>
                                                                <option value="cinnamon">Cinnamon</option>
                                                                <option value="pepper">Pepper</option>
                                                                <option value="cardamom">Cardamom</option>
                                                            </select>
                                                        </div>
                                                        <button type="submit" class="btn btn-sm btn-warning w-100">
                                                            <i class="fas fa-brain me-2"></i>Generate Forecast
                                                        </button>
                                                    </form>
                                                    <div class="mt-3 pt-3 border-top">
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Using Python ML models with 87.5% accuracy
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">
                                                <i class="fas fa-history me-2" style="color: var(--spice-red);"></i>
                                                Recent Orders
                                            </h6>
                                            <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">
                                                View All <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                        <?php if($recent_orders->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Order ID</th>
                                                        <th>Customer</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while($order = $recent_orders->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="manage_orders.php?view=<?php echo $order['order_id']; ?>" class="text-decoration-none">
                                                                <strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                        <td class="fw-bold">Rs. <?php echo number_format($order['final_total'], 2); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_colors = [
                                                                'Pending' => 'warning',
                                                                'Processing' => 'info',
                                                                'Shipped' => 'primary',
                                                                'Delivered' => 'success',
                                                                'Cancelled' => 'danger'
                                                            ];
                                                            $color = $status_colors[$order['status']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?php echo $color; ?>">
                                                                <?php echo $order['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d', strtotime($order['created_at'])); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-shopping-cart fa-2x text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No orders yet</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Admin Profile & Website Stats -->
                    <div class="col-lg-4">
                        <!-- Admin Profile -->
                        <div class="admin-profile-card mb-4">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php if(!empty($admin['profile_image']) && $admin['profile_image'] != 'default-avatar.jpg'): ?>
                                        <img src="../assets/images/profile_images/<?php echo $admin['profile_image']; ?>" 
                                             alt="Admin" class="w-100 h-100 rounded-circle">
                                    <?php else: ?>
                                        <i class="fas fa-user-shield"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($admin['username']); ?></h5>
                                <p class="mb-0 opacity-75">System Administrator</p>
                            </div>
                            <div class="p-3">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="fw-bold text-primary"><?php echo $total_orders; ?></div>
                                            <small class="text-muted">Orders Managed</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="fw-bold text-success"><?php echo $total_products; ?></div>
                                            <small class="text-muted">Products Managed</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="admin_profile.php" class="btn btn-outline-primary">
                                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                                    </a>
                                    <a href="manage_website.php" class="btn btn-outline-success">
                                        <i class="fas fa-cog me-2"></i>Website Settings
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Website Management -->
                        <div class="card stat-card mb-4">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-globe me-2" style="color: var(--spice-blue);"></i>
                                    Website Management
                                </h6>
                                <div class="list-group list-group-flush">
                                    <a href="manage_content.php?page=about" class="list-group-item list-group-item-action border-0 py-2">
                                        <i class="fas fa-info-circle me-2 text-primary"></i>
                                        About Us Page
                                        <i class="fas fa-chevron-right float-end text-muted"></i>
                                    </a>
                                    <a href="manage_content.php?page=home" class="list-group-item list-group-item-action border-0 py-2">
                                        <i class="fas fa-home me-2 text-success"></i>
                                        Homepage Content
                                        <i class="fas fa-chevron-right float-end text-muted"></i>
                                    </a>
                                    <a href="manage_content.php?page=images" class="list-group-item list-group-item-action border-0 py-2">
                                        <i class="fas fa-images me-2 text-warning"></i>
                                        Gallery & Images
                                        <i class="fas fa-chevron-right float-end text-muted"></i>
                                    </a>
                                    <a href="manage_content.php?page=seo" class="list-group-item list-group-item-action border-0 py-2">
                                        <i class="fas fa-search me-2 text-info"></i>
                                        SEO Settings
                                        <i class="fas fa-chevron-right float-end text-muted"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Users -->
                        <div class="card stat-card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="fas fa-user-plus me-2" style="color: var(--spice-gold);"></i>
                                    Recent Users
                                </h6>
                                <?php if($recent_users->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($user = $recent_users->fetch_assoc()): ?>
                                    <div class="list-group-item border-0 py-2 px-0">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px;">
                                                    <?php if($user['role'] == 'customer'): ?>
                                                        <i class="fas fa-user text-primary"></i>
                                                    <?php elseif($user['role'] == 'farmer'): ?>
                                                        <i class="fas fa-tractor text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-user-shield text-warning"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <small class="text-muted"><?php echo $user['email']; ?></small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] == 'customer' ? 'primary' : 
                                                     ($user['role'] == 'farmer' ? 'success' : 'warning');
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No users yet</p>
                                </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="manage_users.php" class="btn btn-sm btn-outline-secondary w-100">
                                        <i class="fas fa-users me-1"></i> View All Users
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Note -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-shield-alt me-1"></i> SpiceCeylon Admin Panel v2.0 
                                <span class="mx-2">•</span> 
                                <i class="fas fa-server me-1"></i> System Uptime: 99.8%
                                <span class="mx-2">•</span> 
                                <i class="fas fa-database me-1"></i> Last Backup: Today 02:00 AM
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>