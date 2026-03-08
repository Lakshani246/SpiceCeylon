<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$farmer_id = $_SESSION['user_id'];

// Get farmer data
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get overall earnings statistics
// Total lifetime earnings
$total_stats_query = $conn->prepare("
    SELECT 
        COALESCE(SUM(oi.total_price), 0) as total_earnings,
        COUNT(DISTINCT o.order_id) as total_orders,
        COUNT(DISTINCT o.customer_id) as total_customers,
        SUM(oi.quantity) as total_products_sold,
        AVG(oi.total_price) as avg_order_value
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$total_stats_query->bind_param("i", $farmer_id);
$total_stats_query->execute();
$total_stats = $total_stats_query->get_result()->fetch_assoc();

// Get current month earnings
$current_month_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as current_month
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND MONTH(o.created_at) = MONTH(CURDATE())
    AND YEAR(o.created_at) = YEAR(CURDATE())
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$current_month_query->bind_param("i", $farmer_id);
$current_month_query->execute();
$current_month = $current_month_query->get_result()->fetch_assoc();

// Get last month earnings
$last_month_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as last_month
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$last_month_query->bind_param("i", $farmer_id);
$last_month_query->execute();
$last_month = $last_month_query->get_result()->fetch_assoc();

// Get today's earnings
$today_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as today
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND DATE(o.created_at) = CURDATE()
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$today_query->bind_param("i", $farmer_id);
$today_query->execute();
$today = $today_query->get_result()->fetch_assoc();

// Get weekly earnings (last 7 days)
$weekly_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as this_week
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$weekly_query->bind_param("i", $farmer_id);
$weekly_query->execute();
$weekly = $weekly_query->get_result()->fetch_assoc();

// Get monthly earnings for chart (last 6 months)
$monthly_chart_query = $conn->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%b') as month_name,
        DATE_FORMAT(o.created_at, '%Y-%m') as month_year,
        COALESCE(SUM(oi.total_price), 0) as monthly_earnings
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month_year ASC
");
$monthly_chart_query->bind_param("i", $farmer_id);
$monthly_chart_query->execute();
$monthly_data = $monthly_chart_query->get_result();

$monthly_labels = [];
$monthly_values = [];

while ($row = $monthly_data->fetch_assoc()) {
    $monthly_labels[] = $row['month_name'];
    $monthly_values[] = (float)$row['monthly_earnings'];
}

// Calculate growth percentages
$monthly_growth = 0;
if ($last_month['last_month'] > 0) {
    $monthly_growth = (($current_month['current_month'] - $last_month['last_month']) / $last_month['last_month']) * 100;
}

// Get total product count
$product_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Overview - Farmer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --farmer-green: #27ae60;
            --farmer-dark: #2c3e50;
            --farmer-gold: #f39c12;
            --farmer-blue: #3498db;
            --farmer-brown: #8b4513;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2d6a4f 0%, #1b4332 100%);
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
            background: rgba(39, 174, 96, 0.2);
            border-left-color: var(--farmer-green);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.3), rgba(139, 69, 19, 0.2));
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
        }
        
        .earnings-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .earnings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            color: white;
            height: 100%;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
            transition: transform 0.5s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card:hover::after {
            transform: rotate(30deg) translate(10%, 10%);
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .stat-card.month {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .stat-card.week {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .stat-card.today {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .growth-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .growth-positive {
            background: rgba(39, 174, 96, 0.15);
            color: #27ae60;
        }
        
        .growth-negative {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .metric-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .metric-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--farmer-green);
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .summary-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .summary-value {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--farmer-dark);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Farmer Sidebar -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="brand">
                    <h4 class="text-white mb-1">
                        <i class="fas fa-tractor me-2"></i>
                        Farmer Panel
                    </h4>
                    <small class="text-light opacity-75">SpiceCeylon</small>
                </div>
                
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_products.php">
                            <i class="fas fa-leaf me-2"></i>
                            My Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_product.php">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add New Product
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_requests.php">
                            <i class="fas fa-inbox me-2"></i>
                            Customer Requests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_sales.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Sales Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="earnings.php">
                            <i class="fas fa-wallet me-2"></i>
                            Earnings Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-2"></i>
                            My Profile
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="../auth/logout.php" style="background: rgba(231, 76, 60, 0.1);">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
                
                <div class="mt-auto p-3 text-center text-light opacity-75 small">
                    <i class="fas fa-seedling me-1"></i>
                    Farmer ID: F<?php echo str_pad($farmer_id, 4, '0', STR_PAD_LEFT); ?>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-wallet me-2" style="color: var(--farmer-green);"></i>
                                Earnings Overview
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Track your overall earnings and financial performance at a glance.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--farmer-green), #219653); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500;">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>

                <!-- Key Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card total">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($total_stats['total_earnings'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Lifetime Earnings</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-shopping-cart me-1"></i>
                                        <?php echo $total_stats['total_orders'] ?? 0; ?> orders
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-coins"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card month">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($current_month['current_month'] ?? 0, 2); ?></div>
                                    <div class="stat-label">This Month</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('F Y'); ?>
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card week">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($weekly['this_week'] ?? 0, 2); ?></div>
                                    <div class="stat-label">This Week</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar-week me-1"></i>
                                        Last 7 days
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card today">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($today['today'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Today</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-sun me-1"></i>
                                        <?php echo date('l'); ?>
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Growth and Metrics Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="earnings-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-line me-2" style="color: var(--farmer-green);"></i>
                                Growth Metrics
                            </h5>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="summary-label">Monthly Growth</span>
                                    <div>
                                        <span class="growth-badge <?php echo $monthly_growth >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                                            <i class="fas fa-<?php echo $monthly_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                                            <?php echo number_format(abs($monthly_growth), 1); ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Last month: Rs. <?php echo number_format($last_month['last_month'] ?? 0, 2); ?></span>
                                    <span>This month: Rs. <?php echo number_format($current_month['current_month'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="metric-box py-3">
                                        <div class="metric-value"><?php echo $total_stats['total_orders'] ?? 0; ?></div>
                                        <div class="metric-label">Total Orders</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-box py-3">
                                        <div class="metric-value"><?php echo $total_stats['total_customers'] ?? 0; ?></div>
                                        <div class="metric-label">Customers</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="earnings-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-pie me-2" style="color: var(--farmer-blue);"></i>
                                Performance Summary
                            </h5>
                            
                            <div class="summary-item d-flex justify-content-between align-items-center">
                                <span class="summary-label">Products Sold</span>
                                <span class="summary-value"><?php echo $total_stats['total_products_sold'] ?? 0; ?> units</span>
                            </div>
                            
                            <div class="summary-item d-flex justify-content-between align-items-center">
                                <span class="summary-label">Average Order Value</span>
                                <span class="summary-value">Rs. <?php echo number_format($total_stats['avg_order_value'] ?? 0, 2); ?></span>
                            </div>
                            
                            <div class="summary-item d-flex justify-content-between align-items-center">
                                <span class="summary-label">Products in Catalog</span>
                                <span class="summary-value"><?php echo $product_count; ?></span>
                            </div>
                            
                            <div class="summary-item d-flex justify-content-between align-items-center">
                                <span class="summary-label">Avg per Customer</span>
                                <?php 
                                $avg_per_customer = ($total_stats['total_customers'] ?? 0) > 0 
                                    ? ($total_stats['total_earnings'] ?? 0) / $total_stats['total_customers'] 
                                    : 0;
                                ?>
                                <span class="summary-value">Rs. <?php echo number_format($avg_per_customer, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="earnings-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-trophy me-2" style="color: var(--farmer-gold);"></i>
                                Milestones
                            </h5>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Next: Rs. 100,000</span>
                                    <?php $progress = min(100, (($total_stats['total_earnings'] ?? 0) / 100000) * 100); ?>
                                    <span class="small fw-bold"><?php echo number_format($progress, 1); ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Next: 100 Orders</span>
                                    <?php $order_progress = min(100, (($total_stats['total_orders'] ?? 0) / 100) * 100); ?>
                                    <span class="small fw-bold"><?php echo number_format($order_progress, 1); ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $order_progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Next: 500 Products Sold</span>
                                    <?php $product_progress = min(100, (($total_stats['total_products_sold'] ?? 0) / 500) * 100); ?>
                                    <span class="small fw-bold"><?php echo number_format($product_progress, 1); ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $product_progress; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Earnings Chart -->
                <div class="row">
                    <div class="col-12">
                        <div class="earnings-card">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-bar me-2" style="color: var(--farmer-green);"></i>
                                Monthly Earnings Trend (Last 6 Months)
                            </h5>
                            <div class="chart-container">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="earnings-card bg-light">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-2">Best Month</h6>
                                    <?php
                                    $max_month = !empty($monthly_values) ? max($monthly_values) : 0;
                                    $max_index = array_search($max_month, $monthly_values);
                                    $best_month = $max_index !== false ? ($monthly_labels[$max_index] ?? 'N/A') : 'N/A';
                                    ?>
                                    <h4 class="text-success"><?php echo $best_month; ?></h4>
                                    <small class="text-muted">Rs. <?php echo number_format($max_month, 2); ?></small>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-2">Monthly Average</h6>
                                    <?php $avg_monthly = !empty($monthly_values) ? array_sum($monthly_values) / count($monthly_values) : 0; ?>
                                    <h4 class="text-primary">Rs. <?php echo number_format($avg_monthly, 2); ?></h4>
                                    <small class="text-muted">Last 6 months</small>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-2">Total Orders</h6>
                                    <h4 class="text-warning"><?php echo $total_stats['total_orders'] ?? 0; ?></h4>
                                    <small class="text-muted">Lifetime</small>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-muted mb-2">Avg per Order</h6>
                                    <h4 class="text-info">Rs. <?php echo number_format($total_stats['avg_order_value'] ?? 0, 2); ?></h4>
                                    <small class="text-muted">Overall</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-wallet me-1"></i> 
                                Earnings Overview • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-coins me-1"></i> 
                                Total Earnings: Rs. <?php echo number_format($total_stats['total_earnings'] ?? 0, 2); ?> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-shopping-cart me-1"></i> 
                                Orders: <?php echo $total_stats['total_orders'] ?? 0; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart data
        const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
        const monthlyValues = <?php echo json_encode($monthly_values); ?>;

        // Initialize chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Monthly Earnings (Rs.)',
                    data: monthlyValues,
                    backgroundColor: 'rgba(39, 174, 96, 0.7)',
                    borderColor: '#27ae60',
                    borderWidth: 2,
                    borderRadius: 5,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Earnings: Rs. ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e9ecef'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Add hover effect to cards
        $(document).ready(function() {
            $('.stat-card, .earnings-card, .metric-box').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>