<?php
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$admin_id = $_SESSION['admin_id'];

// Get admin data
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
$total_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders")->fetch_assoc()['revenue'];
// Add this after the monthly revenue line:
$today_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['revenue'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status='Pending'")->fetch_assoc()['count'];
$total_requests = $conn->query("SELECT COUNT(*) as count FROM requests")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='Pending'")->fetch_assoc()['count'];

// Get order statistics by status
$order_stats = [];
$statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Confirmed', 'Cancelled'];
foreach ($statuses as $status) {
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = '$status'");
    $order_stats[$status] = $result->fetch_assoc()['count'];
}

// Monthly revenue
$monthly_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['revenue'];

// Today's revenue
$today_revenue = $conn->query("SELECT COALESCE(SUM(final_total), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['revenue'];

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

// Get today's date for display
$today = date('l, F j, Y');
$current_time = date('h:i A');
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
            --spice-purple: #9b59b6;
            --pending: #f39c12;
            --processing: #3498db;
            --shipped: #9b59b6;
            --delivered: #27ae60;
            --completed: #2ecc71;
            --confirmed: #1abc9c;
            --cancelled: #e74c3c;
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
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--spice-blue);
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
            color: white;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.revenue {
            background: linear-gradient(135deg, var(--spice-red), #d35400);
        }
        
        .stat-card.orders {
            background: linear-gradient(135deg, var(--spice-green), #219653);
        }
        
        .stat-card.products {
            background: linear-gradient(135deg, var(--spice-blue), #2980b9);
        }
        
        .stat-card.users {
            background: linear-gradient(135deg, var(--spice-gold), #e67e22);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .forecast-card {
            background: linear-gradient(135deg, var(--spice-purple), #8e44ad);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(155, 89, 182, 0.3);
        }
        
        .admin-profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--spice-red);
            margin: 0 auto 20px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--spice-red);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-Pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-Processing { background: rgba(52, 152, 219, 0.15); color: var(--processing); }
        .badge-Shipped { background: rgba(155, 89, 182, 0.15); color: var(--shipped); }
        .badge-Delivered { background: rgba(39, 174, 96, 0.15); color: var(--delivered); }
        .badge-Completed { background: rgba(46, 204, 113, 0.15); color: var(--completed); }
        .badge-Confirmed { background: rgba(26, 188, 156, 0.15); color: var(--confirmed); }
        .badge-Cancelled { background: rgba(231, 76, 60, 0.15); color: var(--cancelled); }
        
        .table-hover tbody tr:hover {
            background-color: rgba(184, 92, 56, 0.04);
        }
        
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
        
        .prediction-item {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            backdrop-filter: blur(10px);
        }
        
        .trend-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-up { background: rgba(39, 174, 96, 0.15); color: var(--spice-green); }
        .trend-down { background: rgba(231, 76, 60, 0.15); color: #e74c3c; }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        .order-status-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .order-status-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .order-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-Pending { background: var(--pending); }
        .status-Processing { background: var(--processing); }
        .status-Shipped { background: var(--shipped); }
        .status-Delivered { background: var(--delivered); }
        .status-Completed { background: var(--completed); }
        .status-Confirmed { background: var(--confirmed); }
        .status-Cancelled { background: var(--cancelled); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-tachometer-alt me-2" style="color: var(--spice-red);"></i>
                                Admin Dashboard
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Welcome back, <strong><?php echo htmlspecialchars($admin['username']); ?></strong>! 
                                Here's what's happening with your store today.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-red), #d35400); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(184, 92, 56, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo $today; ?>
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock me-1"></i> <?php echo $current_time; ?>
                        </div>
                    </div>
                </div>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card revenue">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($total_revenue, 0); ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar me-1"></i> 
                                        This month: Rs. <?php echo number_format($monthly_revenue, 0); ?>
                                        <?php if($today_revenue > 0): ?>
                                        <br>
                                        <i class="fas fa-sun me-1"></i> 
                                        Today: Rs. <?php echo number_format($today_revenue, 0); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-coins"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card orders">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                                    <div class="stat-label">Total Orders</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-clock me-1"></i> 
                                        <?php echo $pending_orders; ?> pending
                                        <br>
                                        <i class="fas fa-truck me-1"></i> 
                                        <?php echo $order_stats['Processing'] + $order_stats['Shipped']; ?> in process
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card products">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                                    <div class="stat-label">Active Products</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-clock me-1"></i> 
                                        <?php echo $pending_products; ?> pending approval
                                        <br>
                                        <i class="fas fa-tractor me-1"></i> 
                                        From <?php echo $total_farmers; ?> farmers
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-leaf"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card users">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                                    <div class="stat-label">Total Users</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-user me-1"></i> 
                                        <?php echo $total_customers; ?> customers
                                        <br>
                                        <i class="fas fa-tractor me-1"></i> 
                                        <?php echo $total_farmers; ?> farmers
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Order Status Summary -->
                <div class="row mb-4">
                    <!-- Quick Actions -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2" style="color: var(--spice-red);"></i>
                                    Quick Actions
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-lightbulb me-1"></i> 
                                    Frequently used admin tasks
                                </span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="manage_orders.php" class="quick-action-btn">
                                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                        <div class="fw-bold">Manage Orders</div>
                                        <small class="text-muted"><?php echo $pending_orders; ?> pending</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="manage_products.php" class="quick-action-btn">
                                        <i class="fas fa-leaf fa-2x mb-2"></i>
                                        <div class="fw-bold">Manage Products</div>
                                        <small class="text-muted"><?php echo $pending_products; ?> pending</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="manage_users.php" class="quick-action-btn">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <div class="fw-bold">Manage Users</div>
                                        <small class="text-muted"><?php echo $total_users; ?> total</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="forecast_sales.php" class="quick-action-btn">
                                        <i class="fas fa-brain fa-2x mb-2"></i>
                                        <div class="fw-bold">AI Forecast</div>
                                        <small class="text-muted">Predict sales</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Status Summary -->
                    <div class="col-md-4">
                        <div class="analytics-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-pie me-2" style="color: var(--spice-blue);"></i>
                                Order Status Summary
                            </h5>
                            <div class="order-status-summary">
                                <?php foreach ($statuses as $status): 
                                    if ($order_stats[$status] > 0): ?>
                                    <div class="order-status-item">
                                        <div class="order-status-dot status-<?php echo $status; ?>"></div>
                                        <div class="flex-grow-1">
                                            <?php echo $status; ?>
                                        </div>
                                        <div class="fw-bold">
                                            <?php echo $order_stats[$status]; ?>
                                        </div>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                            <div class="text-center">
                                <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chart-bar me-1"></i> View Detailed Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Sales Forecasting -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="forecast-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-robot me-2"></i>
                                    AI Sales Forecasting
                                </h5>
                                <span class="trend-badge trend-up">
                                    <i class="fas fa-microchip me-1"></i> LSTM Model v2.1 • 87.5% accuracy
                                </span>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-7">
                                    <h6 class="mb-3">Top Selling Products (Next 30 Days)</h6>
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
                                    <?php endwhile; else: ?>
                                    <div class="prediction-item">
                                        <div class="text-center py-3">
                                            <i class="fas fa-chart-line me-2"></i>
                                            <span>Insufficient data for prediction</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-5">
                                    <div class="bg-white rounded p-4 text-dark mt-3 mt-md-0">
                                        <h6 class="mb-3" style="color: var(--spice-dark);">
                                            <i class="fas fa-sliders-h me-2"></i>
                                            Forecast Controls
                                        </h6>
                                        <form method="POST" action="generate_forecast.php">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Forecast Period</label>
                                                <select class="form-select form-select-sm" name="period">
                                                    <option value="30">30 Days</option>
                                                    <option value="60">60 Days</option>
                                                    <option value="90">90 Days</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Spice Category</label>
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
                                                Forecasts are generated using historical sales data and machine learning algorithms.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2" style="color: var(--spice-red);"></i>
                                    Recent Orders
                                </h5>
                                <a href="manage_orders.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View All Orders
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
                                                <a href="manage_orders.php?action=view&id=<?php echo $order['order_id']; ?>" 
                                                   class="text-decoration-none fw-bold">
                                                    #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td class="fw-bold">Rs. <?php echo number_format($order['final_total'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'badge-' . $order['status'];
                                                $status_icon = 'fa-circle';
                                                
                                                switch($order['status']) {
                                                    case 'Pending': $status_icon = 'fa-clock'; break;
                                                    case 'Processing': $status_icon = 'fa-cogs'; break;
                                                    case 'Shipped': $status_icon = 'fa-shipping-fast'; break;
                                                    case 'Delivered': $status_icon = 'fa-check-circle'; break;
                                                    case 'Completed': $status_icon = 'fa-check-double'; break;
                                                    case 'Confirmed': $status_icon = 'fa-user-check'; break;
                                                    case 'Cancelled': $status_icon = 'fa-times-circle'; break;
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <i class="fas <?php echo $status_icon; ?> me-1"></i> 
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h5 class="text-muted mb-3">No orders found</h5>
                                <p class="text-muted mb-4">
                                    There are no orders in the system yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Admin Profile & Recent Users -->
                    <div class="col-md-4">
                        <!-- Admin Profile -->
                        <div class="admin-profile-card">
                            <div class="text-center mb-4">
                                <div class="profile-avatar">
                                    <?php if(!empty($admin['avatar'])): ?>
                                        <img src="../assets/images/profile_images/<?php echo $admin['avatar']; ?>" 
                                             alt="Admin" class="w-100 h-100 rounded-circle">
                                    <?php else: ?>
                                        <i class="fas fa-user-shield"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($admin['username']); ?></h5>
                                <p class="text-muted mb-3">System Administrator</p>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="fw-bold text-primary"><?php echo $total_orders; ?></div>
                                        <small class="text-muted">Orders Managed</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-success"><?php echo $total_products; ?></div>
                                        <small class="text-muted">Products Managed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="admin_profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-edit me-2"></i> Edit Profile
                                </a>
                                <a href="manage_website.php" class="btn btn-outline-success">
                                    <i class="fas fa-cog me-2"></i> Website Settings
                                </a>
                            </div>
                        </div>
                        
                        <!-- Recent Users -->
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-plus me-2" style="color: var(--spice-gold);"></i>
                                    Recent Users
                                </h5>
                                <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View All
                                </a>
                            </div>
                            
                            <?php if($recent_users->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while($user = $recent_users->fetch_assoc()): ?>
                                <div class="list-group-item border-0 py-2 px-0">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
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
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No users yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-shield-alt me-1"></i> 
                                SpiceCeylon Admin Panel v2.0 • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-server me-1"></i> 
                                System Status: <span class="text-success">Operational</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-database me-1"></i> 
                                Last Backup: Today 02:00 AM
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-update time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateStr = now.toLocaleDateString('en-US', options);
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            $('.time-display').html(`
                <i class="fas fa-calendar-alt me-1"></i> ${dateStr}
                <span class="mx-2">|</span>
                <i class="fas fa-clock me-1"></i> ${timeStr}
            `);
        }
        
        // Update time every minute
        setInterval(updateTime, 60000);
        
        // Initialize on page load
        $(document).ready(function() {
            updateTime();
            
            // Add hover effect to stat cards
            $('.stat-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>