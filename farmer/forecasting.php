<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get farmer data
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get statistics for farmer (to match dashboard)
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id")->fetch_assoc()['count'];
$approved_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id AND status='Approved' AND admin_approved='approved'")->fetch_assoc()['count'];
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id AND status='Pending' AND admin_approved='pending'")->fetch_assoc()['count'];
$rejected_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id AND status='Rejected' OR admin_approved='rejected'")->fetch_assoc()['count'];

// Get farmer's sales statistics
$total_sales = $conn->query("
    SELECT COALESCE(SUM(oi.total_price), 0) as total_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.farmer_id = $farmer_id
")->fetch_assoc()['total_sales'];

// Get today's sales
$today_sales = $conn->query("
    SELECT COALESCE(SUM(oi.total_price), 0) as today_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE p.farmer_id = $farmer_id AND DATE(o.created_at) = CURDATE()
")->fetch_assoc()['today_sales'];

// Get monthly sales
$monthly_sales = $conn->query("
    SELECT COALESCE(SUM(oi.total_price), 0) as monthly_sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE p.farmer_id = $farmer_id 
    AND MONTH(o.created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
")->fetch_assoc()['monthly_sales'];

// Get farmer's orders count
$total_orders = $conn->query("
    SELECT COUNT(DISTINCT oi.order_id) as order_count 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.farmer_id = $farmer_id
")->fetch_assoc()['order_count'];

// Get customer requests assigned to this farmer
$total_requests = $conn->query("
    SELECT COUNT(*) as count 
    FROM product_requests 
    WHERE assigned_farmer_id = $farmer_id
")->fetch_assoc()['count'];

$pending_requests = $conn->query("
    SELECT COUNT(*) as count 
    FROM product_requests 
    WHERE assigned_farmer_id = $farmer_id AND status = 'Pending'
")->fetch_assoc()['count'];

// Get farmer's products for forecasting
$products_query = "
    SELECT p.*, 
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(AVG(oi.quantity), 0) as avg_monthly_sales
    FROM products p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status IN ('Completed', 'Delivered')
    WHERE p.farmer_id = ? 
    AND p.status = 'Approved'
    GROUP BY p.product_id
    ORDER BY p.created_at DESC
";
$products_stmt = $conn->prepare($products_query);
$products_stmt->bind_param("i", $farmer_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();

// Get forecasting data for farmer's products
$forecasting_data = [];
if ($products_result->num_rows > 0) {
    while($product = $products_result->fetch_assoc()) {
        $forecast_query = "
            SELECT * FROM forecast_data 
            WHERE product_id = ? 
            ORDER BY forecast_month ASC
            LIMIT 6
        ";
        $forecast_stmt = $conn->prepare($forecast_query);
        $forecast_stmt->bind_param("i", $product['product_id']);
        $forecast_stmt->execute();
        $forecast_result = $forecast_stmt->get_result();
        
        $product['forecasts'] = [];
        while($forecast = $forecast_result->fetch_assoc()) {
            $product['forecasts'][] = $forecast;
        }
        
        // Calculate simple forecast if no existing forecast data
        if (empty($product['forecasts'])) {
            $product['forecasts'] = generateSimpleForecast($product, $conn);
        }
        
        $forecasting_data[] = $product;
    }
}

// Function to generate simple forecast if no admin forecast exists
function generateSimpleForecast($product, $conn) {
    $forecasts = [];
    $monthly_avg = $product['avg_monthly_sales'] ?: 5; // Default to 5 if no sales
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Get current month
    $current_month = date('n') - 1;
    
    // Generate 6-month forecast
    for ($i = 1; $i <= 6; $i++) {
        $month_index = ($current_month + $i) % 12;
        $forecasts[] = [
            'forecast_month' => $months[$month_index] . ' ' . date('Y'),
            'forecast_value' => $monthly_avg * (1 + ($i * 0.1)), // 10% growth each month
            'confidence' => 0.7,
            'trend' => 'growing'
        ];
    }
    
    return $forecasts;
}

// Get overall stats
$total_forecast_products = count($forecasting_data);
$total_forecasts = 0;
foreach ($forecasting_data as $product) {
    $total_forecasts += count($product['forecasts']);
}

// Get recommended actions based on forecasts
$recommendations = [];
foreach ($forecasting_data as $product) {
    if (!empty($product['forecasts'])) {
        $latest_forecast = end($product['forecasts']);
        $forecast_value = $latest_forecast['forecast_value'];
        $current_stock = $product['stock'];
        
        if ($forecast_value > $current_stock * 1.5) {
            $recommendations[] = [
                'type' => 'stock_up',
                'product' => $product['name'],
                'message' => "Consider increasing stock for {$product['name']}. Forecast: {$forecast_value}, Current: {$current_stock}"
            ];
        } elseif ($forecast_value < $current_stock * 0.5) {
            $recommendations[] = [
                'type' => 'reduce_stock',
                'product' => $product['name'],
                'message' => "Consider reducing stock for {$product['name']}. Forecast: {$forecast_value}, Current: {$current_stock}"
            ];
        }
    }
}

// Get today's date for display
$today = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Forecasting - SpiceCeylon</title>
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
            --pending: #f39c12;
            --approved: #27ae60;
            --rejected: #e74c3c;
            --processing: #3498db;
            --completed: #2ecc71;
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
        
        .stat-card.forecasting {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .stat-card.products {
            background: linear-gradient(135deg, var(--farmer-brown), #a0522d);
        }
        
        .stat-card.insights {
            background: linear-gradient(135deg, var(--farmer-blue), #2980b9);
        }
        
        .stat-card.recommendations {
            background: linear-gradient(135deg, var(--farmer-gold), #e67e22);
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
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-Pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-Approved { background: rgba(39, 174, 96, 0.15); color: var(--approved); }
        .badge-Rejected { background: rgba(231, 76, 60, 0.15); color: var(--rejected); }
        .badge-Processing { background: rgba(52, 152, 219, 0.15); color: var(--processing); }
        .badge-Completed { background: rgba(46, 204, 113, 0.15); color: var(--completed); }
        
        .forecast-chart-container {
            height: 300px;
            margin: 20px 0;
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
            color: var(--farmer-dark);
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(39, 174, 96, 0.15);
            background: var(--farmer-green);
            color: white;
            text-decoration: none;
        }
        
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
        
        .trend-up { color: var(--farmer-green); }
        .trend-down { color: #e74c3c; }
        .trend-stable { color: var(--farmer-blue); }
        
        .accordion-button {
            font-weight: 600;
            background: #f8f9fa;
        }
        
        .accordion-button:not(.collapsed) {
            background: rgba(39, 174, 96, 0.05);
            color: var(--farmer-dark);
        }
        
        .forecast-month-card {
            border-left: 4px solid var(--farmer-blue);
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .forecast-month-card.high-demand {
            border-left-color: var(--farmer-green);
            background: rgba(39, 174, 96, 0.05);
        }
        
        .forecast-month-card.low-demand {
            border-left-color: #e74c3c;
            background: rgba(231, 76, 60, 0.05);
        }
        
        .recommendation-alert {
            border-left: 4px solid var(--farmer-gold);
            background: rgba(243, 156, 18, 0.05);
            border-radius: 8px;
        }
        
        .ai-badge {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 20px;
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
                            <?php if($pending_products > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $pending_products; ?></span>
                            <?php endif; ?>
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
                            <?php if($pending_requests > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $pending_requests; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-2"></i>
                            Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="earnings.php">
                            <i class="fas fa-wallet me-2"></i>
                            Earnings Monitor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_sales.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            Sales Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="forecasting.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Sales Forecasting
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
                                <i class="fas fa-chart-line me-2" style="color: #9b59b6;"></i>
                                Sales Forecasting Dashboard
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                AI-powered sales predictions to help you plan production and inventory
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo $today; ?>
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock me-1"></i> <?php echo $current_time; ?>
                        </div>
                    </div>
                </div>

                <!-- Forecasting Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card forecasting">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_forecast_products); ?></div>
                                    <div class="stat-label">Products with Forecasts</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-chart-line me-1"></i> 
                                        <?php echo $total_forecasts; ?> forecast periods
                                        <br>
                                        <i class="fas fa-brain me-1"></i> 
                                        AI-powered predictions
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card products">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($total_sales, 0); ?></div>
                                    <div class="stat-label">Total Sales Revenue</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar me-1"></i> 
                                        This month: Rs. <?php echo number_format($monthly_sales, 0); ?>
                                        <?php if($today_sales > 0): ?>
                                        <br>
                                        <i class="fas fa-sun me-1"></i> 
                                        Today: Rs. <?php echo number_format($today_sales, 0); ?>
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
                        <div class="stat-card insights">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                                    <div class="stat-label">Orders Analyzed</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-database me-1"></i> 
                                        Historical data patterns
                                        <br>
                                        <i class="fas fa-chart-bar me-1"></i> 
                                        Seasonal trend analysis
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card recommendations">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format(count($recommendations)); ?></div>
                                    <div class="stat-label">Smart Recommendations</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-lightbulb me-1"></i> 
                                        Stock level suggestions
                                        <br>
                                        <i class="fas fa-calendar-check me-1"></i> 
                                        Production planning
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Smart Recommendations -->
                <?php if(!empty($recommendations)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="analytics-card recommendation-alert">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-lightbulb me-2" style="color: var(--farmer-gold);"></i>
                                    Smart Recommendations
                                    <span class="ai-badge ms-2"><i class="fas fa-robot me-1"></i> AI-Powered</span>
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-exclamation-triangle me-1"></i> 
                                    Based on forecast analysis
                                </span>
                            </div>
                            <div class="row g-3">
                                <?php foreach($recommendations as $rec): ?>
                                <div class="col-md-6">
                                    <div class="alert alert-<?php echo $rec['type'] == 'stock_up' ? 'success' : 'warning'; ?> mb-0">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-<?php echo $rec['type'] == 'stock_up' ? 'arrow-up' : 'arrow-down'; ?> fa-lg"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo ucfirst(str_replace('_', ' ', $rec['type'])); ?>:</strong>
                                                <?php echo htmlspecialchars($rec['message']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions & How It Works -->
                <div class="row mb-4">
                    <!-- Quick Actions -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2" style="color: var(--farmer-green);"></i>
                                    Quick Actions
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-lightbulb me-1"></i> 
                                    Based on your forecasts
                                </span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="add_product.php" class="quick-action-btn">
                                        <i class="fas fa-plus-circle fa-2x mb-2" style="color: #9b59b6;"></i>
                                        <div class="fw-bold">Add Product</div>
                                        <small class="text-muted">List new spice</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="manage_products.php" class="quick-action-btn">
                                        <i class="fas fa-edit fa-2x mb-2" style="color: var(--farmer-brown);"></i>
                                        <div class="fw-bold">Adjust Stock</div>
                                        <small class="text-muted"><?php echo $total_products; ?> products</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="my_sales.php" class="quick-action-btn">
                                        <i class="fas fa-chart-bar fa-2x mb-2" style="color: var(--farmer-blue);"></i>
                                        <div class="fw-bold">Sales Analytics</div>
                                        <small class="text-muted">View detailed reports</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="dashboard.php" class="quick-action-btn">
                                        <i class="fas fa-tachometer-alt fa-2x mb-2" style="color: var(--farmer-green);"></i>
                                        <div class="fw-bold">Dashboard</div>
                                        <small class="text-muted">Back to overview</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- How It Works -->
                    <div class="col-md-4">
                        <div class="analytics-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-question-circle me-2" style="color: var(--farmer-green);"></i>
                                How Forecasting Works
                            </h5>
                            <div class="mb-3">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <div style="width: 40px; height: 40px; background: rgba(39, 174, 96, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-history" style="color: var(--farmer-green);"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Historical Data</h6>
                                        <p class="small text-muted mb-0">Analyzes your past sales patterns and seasonal trends</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <div style="width: 40px; height: 40px; background: rgba(155, 89, 182, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-brain" style="color: #9b59b6;"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">AI Prediction</h6>
                                        <p class="small text-muted mb-0">Machine learning predicts future demand</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <div style="width: 40px; height: 40px; background: rgba(243, 156, 18, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-lightbulb" style="color: var(--farmer-gold);"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Smart Insights</h6>
                                        <p class="small text-muted mb-0">Get actionable production recommendations</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-light mt-3">
                                <small>
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong>Note:</strong> Forecasts are predictive models based on historical data - actual sales may vary.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Forecasts -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2" style="color: var(--farmer-blue);"></i>
                                    Product Sales Forecasts
                                    <span class="ai-badge ms-2"><i class="fas fa-robot me-1"></i> AI Generated</span>
                                </h5>
                                <span class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    6-month sales predictions
                                </span>
                            </div>
                            
                            <?php if(!empty($forecasting_data)): ?>
                                <div class="accordion" id="forecastAccordion">
                                    <?php foreach($forecasting_data as $index => $product): ?>
                                    <div class="accordion-item mb-3 border-0">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#forecast<?php echo $index; ?>" style="border-radius: 10px;">
                                                <div class="d-flex justify-content-between align-items-center w-100">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <span class="badge bg-secondary ms-2"><?php echo $product['category']; ?></span>
                                                        <span class="badge <?php echo $product['stock'] > 20 ? 'bg-success' : ($product['stock'] > 5 ? 'bg-warning' : 'bg-danger'); ?> ms-1">
                                                            Stock: <?php echo $product['stock']; ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted">
                                                            <?php if(!empty($product['forecasts'])): ?>
                                                                <i class="fas fa-chart-line me-1"></i>
                                                                <?php echo count($product['forecasts']); ?> month forecast
                                                            <?php else: ?>
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                No forecast data
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="forecast<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" data-bs-parent="#forecastAccordion">
                                            <div class="accordion-body">
                                                <?php if(!empty($product['forecasts'])): ?>
                                                    <!-- Forecast Chart -->
                                                    <div class="forecast-chart-container">
                                                        <canvas id="forecastChart<?php echo $index; ?>"></canvas>
                                                    </div>
                                                    
                                                    <!-- Forecast Table -->
                                                    <div class="table-responsive mt-4">
                                                        <table class="table table-hover">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Month</th>
                                                                    <th>Forecasted Sales</th>
                                                                    <th>Growth Rate</th>
                                                                    <th>Confidence</th>
                                                                    <th>Recommendation</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $previous_value = null;
                                                                foreach($product['forecasts'] as $forecast): 
                                                                    $growth = $previous_value ? (($forecast['forecast_value'] - $previous_value) / $previous_value * 100) : 0;
                                                                    $confidence = isset($forecast['confidence']) ? $forecast['confidence'] * 100 : 75;
                                                                    $confidence_class = $confidence > 80 ? 'success' : ($confidence > 60 ? 'warning' : 'danger');
                                                                ?>
                                                                <tr>
                                                                    <td><strong><?php echo htmlspecialchars($forecast['forecast_month']); ?></strong></td>
                                                                    <td>
                                                                        <span class="badge bg-primary">
                                                                            <?php echo number_format($forecast['forecast_value'], 1); ?> units
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="<?php echo $growth > 0 ? 'trend-up' : ($growth < 0 ? 'trend-down' : 'trend-stable'); ?>">
                                                                            <i class="fas fa-arrow-<?php echo $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'right'); ?> me-1"></i>
                                                                            <?php echo number_format($growth, 1); ?>%
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo $confidence_class; ?>">
                                                                            <?php echo number_format($confidence, 0); ?>%
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php 
                                                                        $forecast_value = $forecast['forecast_value'];
                                                                        $current_stock = $product['stock'];
                                                                        $ratio = $forecast_value / max($current_stock, 1);
                                                                        
                                                                        if ($ratio > 1.5) {
                                                                            echo '<span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i> Increase Stock</span>';
                                                                        } elseif ($ratio < 0.5) {
                                                                            echo '<span class="badge bg-warning"><i class="fas fa-arrow-down me-1"></i> Reduce Stock</span>';
                                                                        } else {
                                                                            echo '<span class="badge bg-info"><i class="fas fa-check me-1"></i> Maintain</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                </tr>
                                                                <?php 
                                                                    $previous_value = $forecast['forecast_value'];
                                                                endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    
                                                    <script>
                                                    // Initialize chart for this product
                                                    document.addEventListener('DOMContentLoaded', function() {
                                                        const ctx = document.getElementById('forecastChart<?php echo $index; ?>').getContext('2d');
                                                        const months = <?php echo json_encode(array_column($product['forecasts'], 'forecast_month')); ?>;
                                                        const values = <?php echo json_encode(array_column($product['forecasts'], 'forecast_value')); ?>;
                                                        
                                                        new Chart(ctx, {
                                                            type: 'line',
                                                            data: {
                                                                labels: months,
                                                                datasets: [{
                                                                    label: 'Forecasted Sales',
                                                                    data: values,
                                                                    borderColor: 'rgba(52, 152, 219, 1)',
                                                                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                                                    borderWidth: 3,
                                                                    fill: true,
                                                                    tension: 0.3
                                                                }]
                                                            },
                                                            options: {
                                                                responsive: true,
                                                                maintainAspectRatio: false,
                                                                plugins: {
                                                                    title: {
                                                                        display: true,
                                                                        text: '6-Month Sales Forecast: <?php echo addslashes($product['name']); ?>'
                                                                    },
                                                                    tooltip: {
                                                                        callbacks: {
                                                                            label: function(context) {
                                                                                return 'Forecast: ' + context.parsed.y.toFixed(1) + ' units';
                                                                            }
                                                                        }
                                                                    }
                                                                },
                                                                scales: {
                                                                    y: {
                                                                        beginAtZero: true,
                                                                        title: {
                                                                            display: true,
                                                                            text: 'Units'
                                                                        }
                                                                    },
                                                                    x: {
                                                                        title: {
                                                                            display: true,
                                                                            text: 'Month'
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        });
                                                    });
                                                    </script>
                                                    
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        No forecast data available for this product. Forecasts are generated by the admin system based on historical sales data.
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Current Stats -->
                                                <div class="row mt-4">
                                                    <div class="col-md-3">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body text-center">
                                                                <h6 class="card-title text-muted">Current Stock</h6>
                                                                <h2 class="<?php echo $product['stock'] > 20 ? 'text-success' : ($product['stock'] > 5 ? 'text-warning' : 'text-danger'); ?>">
                                                                    <?php echo $product['stock']; ?>
                                                                </h2>
                                                                <p class="small text-muted">Units available</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body text-center">
                                                                <h6 class="card-title text-muted">Total Sold</h6>
                                                                <h2 class="text-success"><?php echo $product['total_sold']; ?></h2>
                                                                <p class="small text-muted">All-time sales</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body text-center">
                                                                <h6 class="card-title text-muted">Monthly Avg</h6>
                                                                <h2 class="text-warning"><?php echo number_format($product['avg_monthly_sales'], 1); ?></h2>
                                                                <p class="small text-muted">Avg units/month</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body text-center">
                                                                <h6 class="card-title text-muted">Price</h6>
                                                                <h2 class="text-primary">Rs. <?php echo number_format($product['price'], 2); ?></h2>
                                                                <p class="small text-muted">Per unit</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 40px 20px;">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No forecast data available</h5>
                                    <p class="text-muted mb-4">
                                        You need to have approved products with sales history to generate forecasts.
                                    </p>
                                    <a href="add_product.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i> Add Your First Product
                                    </a>
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
                                <i class="fas fa-robot me-1"></i> 
                                SpiceCeylon AI Forecasting v1.0 • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-chart-line me-1"></i> 
                                Forecast Accuracy: <span class="text-success">85%</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-box me-1"></i> 
                                Products Analyzed: <?php echo $total_forecast_products; ?> of <?php echo $total_products; ?>
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
            
            // Auto-refresh forecasts every 5 minutes
            setInterval(function() {
                $.ajax({
                    url: 'check_forecast_updates.php',
                    method: 'GET',
                    success: function(data) {
                        if (data.updated) {
                            location.reload();
                        }
                    }
                });
            }, 300000);
        });
    </script>
</body>
</html>