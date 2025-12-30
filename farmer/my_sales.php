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

// Get date filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Validate dates
if ($start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Get total sales statistics for this farmer
$total_sales_query = $conn->prepare("
    SELECT 
        COALESCE(SUM(oi.total_price), 0) as total_sales,
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(oi.quantity) as total_quantity,
        AVG(oi.total_price) as avg_order_value
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$total_sales_query->bind_param("i", $farmer_id);
$total_sales_query->execute();
$total_stats = $total_sales_query->get_result()->fetch_assoc();

// Get filtered sales statistics
$filtered_sales_query = $conn->prepare("
    SELECT 
        COALESCE(SUM(oi.total_price), 0) as filtered_sales,
        COUNT(DISTINCT o.order_id) as filtered_orders,
        SUM(oi.quantity) as filtered_quantity,
        AVG(oi.total_price) as filtered_avg_value
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND DATE(o.created_at) BETWEEN ? AND ?
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$filtered_sales_query->bind_param("iss", $farmer_id, $start_date, $end_date);
$filtered_sales_query->execute();
$filtered_stats = $filtered_sales_query->get_result()->fetch_assoc();

// Get daily sales for chart (last 30 days)
$daily_sales_query = $conn->prepare("
    SELECT 
        DATE(o.created_at) as sale_date,
        COALESCE(SUM(oi.total_price), 0) as daily_sales,
        COUNT(DISTINCT o.order_id) as daily_orders,
        SUM(oi.quantity) as daily_quantity
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
    AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date
");
$daily_sales_query->bind_param("i", $farmer_id);
$daily_sales_query->execute();
$daily_sales_data = $daily_sales_query->get_result();

$daily_sales_labels = [];
$daily_sales_values = [];
$daily_orders_values = [];

while ($row = $daily_sales_data->fetch_assoc()) {
    $daily_sales_labels[] = date('M j', strtotime($row['sale_date']));
    $daily_sales_values[] = (float)$row['daily_sales'];
    $daily_orders_values[] = (int)$row['daily_orders'];
}

// If no daily data, create default empty arrays
if (empty($daily_sales_labels)) {
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_sales_labels[] = date('M j', strtotime($date));
        $daily_sales_values[] = 0;
        $daily_orders_values[] = 0;
    }
}

// Get monthly sales for chart (last 12 months)
$monthly_sales_query = $conn->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as sale_month,
        DATE_FORMAT(o.created_at, '%b %Y') as month_name,
        COALESCE(SUM(oi.total_price), 0) as monthly_sales,
        COUNT(DISTINCT o.order_id) as monthly_orders
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
    AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY sale_month
");
$monthly_sales_query->bind_param("i", $farmer_id);
$monthly_sales_query->execute();
$monthly_sales_data = $monthly_sales_query->get_result();

$monthly_labels = [];
$monthly_sales = [];
$monthly_orders = [];

while ($row = $monthly_sales_data->fetch_assoc()) {
    $monthly_labels[] = $row['month_name'];
    $monthly_sales[] = (float)$row['monthly_sales'];
    $monthly_orders[] = (int)$row['monthly_orders'];
}

// If no monthly data, create default for last 6 months
if (empty($monthly_labels)) {
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $monthly_labels[] = date('M Y', strtotime($date));
        $monthly_sales[] = 0;
        $monthly_orders[] = 0;
    }
}

// Get top selling products
$top_products_query = $conn->prepare("
    SELECT 
        p.product_id,
        p.name,
        p.category,
        p.image,
        p.stock,
        COALESCE(SUM(oi.quantity), 0) as total_sold,
        COALESCE(SUM(oi.total_price), 0) as total_revenue,
        COALESCE(AVG(oi.price), 0) as avg_price
    FROM products p 
    LEFT JOIN order_items oi ON p.product_id = oi.product_id 
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND (o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped') OR o.status IS NULL)
    GROUP BY p.product_id
    ORDER BY total_revenue DESC, total_sold DESC
    LIMIT 10
");
$top_products_query->bind_param("i", $farmer_id);
$top_products_query->execute();
$top_products = $top_products_query->get_result();

// Get sales by category
$category_sales_query = $conn->prepare("
    SELECT 
        p.category,
        COALESCE(SUM(oi.total_price), 0) as category_sales,
        COALESCE(SUM(oi.quantity), 0) as category_quantity,
        COUNT(DISTINCT o.order_id) as category_orders
    FROM products p 
    LEFT JOIN order_items oi ON p.product_id = oi.product_id 
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND (o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped') OR o.status IS NULL)
    GROUP BY p.category
    ORDER BY category_sales DESC
");
$category_sales_query->bind_param("i", $farmer_id);
$category_sales_query->execute();
$category_sales = $category_sales_query->get_result();

// Get recent sales transactions
$recent_sales_query = $conn->prepare("
    SELECT 
        o.order_id,
        o.created_at,
        u.name as customer_name,
        p.name as product_name,
        oi.quantity,
        oi.price,
        oi.total_price,
        o.status,
        o.payment_method
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    JOIN users u ON o.customer_id = u.user_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
    ORDER BY o.created_at DESC
    LIMIT 20
");
$recent_sales_query->bind_param("i", $farmer_id);
$recent_sales_query->execute();
$recent_sales = $recent_sales_query->get_result();

// Get best selling days of week
$weekly_pattern_query = $conn->prepare("
    SELECT 
        DAYNAME(o.created_at) as day_name,
        DAYOFWEEK(o.created_at) as day_num,
        COALESCE(SUM(oi.total_price), 0) as day_sales,
        COUNT(DISTINCT o.order_id) as day_orders
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
    GROUP BY DAYOFWEEK(o.created_at)
    ORDER BY day_num
");
$weekly_pattern_query->bind_param("i", $farmer_id);
$weekly_pattern_query->execute();
$weekly_pattern = $weekly_pattern_query->get_result();

$weekly_labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$weekly_sales = array_fill(0, 7, 0);

while ($row = $weekly_pattern->fetch_assoc()) {
    $day_index = ($row['day_num'] - 1) % 7;
    $weekly_sales[$day_index] = (float)$row['day_sales'];
}

// Get customer count
$customer_count_query = $conn->prepare("
    SELECT COUNT(DISTINCT o.customer_id) as total_customers
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$customer_count_query->bind_param("i", $farmer_id);
$customer_count_query->execute();
$customer_count_result = $customer_count_query->get_result();
$customer_count = $customer_count_result->fetch_assoc();

// Get today's sales
$today_sales_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as today_sales
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND DATE(o.created_at) = CURDATE()
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$today_sales_query->bind_param("i", $farmer_id);
$today_sales_query->execute();
$today_sales_result = $today_sales_query->get_result();
$today_sales = $today_sales_result->fetch_assoc();

// Get this month's sales
$month_sales_query = $conn->prepare("
    SELECT COALESCE(SUM(oi.total_price), 0) as month_sales
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND MONTH(o.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$month_sales_query->bind_param("i", $farmer_id);
$month_sales_query->execute();
$month_sales_result = $month_sales_query->get_result();
$month_sales = $month_sales_result->fetch_assoc();

// Get growth percentage (this month vs last month)
$growth_query = $conn->prepare("
    SELECT 
        SUM(CASE 
            WHEN MONTH(o.created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
            THEN oi.total_price ELSE 0 
        END) as current_month,
        SUM(CASE 
            WHEN MONTH(o.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(o.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            THEN oi.total_price ELSE 0 
        END) as last_month
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$growth_query->bind_param("i", $farmer_id);
$growth_query->execute();
$growth_result = $growth_query->get_result();
$growth_data = $growth_result->fetch_assoc();

$current_month_sales = $growth_data['current_month'] ?: 0;
$last_month_sales = $growth_data['last_month'] ?: 0;

if ($last_month_sales > 0) {
    $growth_percentage = (($current_month_sales - $last_month_sales) / $last_month_sales) * 100;
} else {
    $growth_percentage = $current_month_sales > 0 ? 100 : 0;
}

// Get product count
$product_count_query = $conn->prepare("
    SELECT COUNT(*) as total_products FROM products WHERE farmer_id = ? AND status = 'Approved'
");
$product_count_query->bind_param("i", $farmer_id);
$product_count_query->execute();
$product_count_result = $product_count_query->get_result();
$product_count = $product_count_result->fetch_assoc();

// Get average product rating
$avg_rating_query = $conn->prepare("
    SELECT COALESCE(AVG(r.rating), 0) as avg_rating
    FROM reviews r
    JOIN products p ON r.product_id = p.product_id
    WHERE p.farmer_id = ?
");
$avg_rating_query->bind_param("i", $farmer_id);
$avg_rating_query->execute();
$avg_rating_result = $avg_rating_query->get_result();
$avg_rating = $avg_rating_result->fetch_assoc();

// Get all time sales summary
$all_time_sales_query = $conn->prepare("
    SELECT 
        COALESCE(SUM(oi.total_price), 0) as all_time_sales,
        COUNT(DISTINCT o.order_id) as all_time_orders
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id
    WHERE p.farmer_id = ? 
    AND o.status IN ('Completed', 'Delivered', 'Confirmed', 'Shipped')
");
$all_time_sales_query->bind_param("i", $farmer_id);
$all_time_sales_query->execute();
$all_time_sales_result = $all_time_sales_query->get_result();
$all_time_sales = $all_time_sales_result->fetch_assoc();

// Debug data (remove in production)
// echo "<pre>";
// echo "Farmer ID: $farmer_id<br>";
// echo "Total Stats: "; print_r($total_stats); echo "<br>";
// echo "Filtered Stats: "; print_r($filtered_stats); echo "<br>";
// echo "Today Sales: "; print_r($today_sales); echo "<br>";
// echo "Month Sales: "; print_r($month_sales); echo "<br>";
// echo "</pre>";

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - Farmer Dashboard</title>
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
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .analytics-card:hover {
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
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
        
        .growth-positive {
            color: #27ae60;
            font-weight: bold;
        }
        
        .growth-negative {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(39, 174, 96, 0.04);
        }
        
        .product-performance {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--farmer-green);
        }
        
        .category-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            background: rgba(39, 174, 96, 0.15);
            color: var(--farmer-green);
        }
        
        .payment-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .badge-cod {
            background: rgba(243, 156, 18, 0.15);
            color: var(--farmer-gold);
        }
        
        .badge-card {
            background: rgba(52, 152, 219, 0.15);
            color: var(--farmer-blue);
        }
        
        .badge-online {
            background: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
        }
        
        .trend-icon {
            font-size: 1.2rem;
            margin-left: 5px;
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
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
        
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .insight-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--farmer-blue);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .debug-info {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 12px;
            display: none; /* Hide in production */
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
                        <a class="nav-link active" href="my_sales.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Sales Analytics
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
                <!-- Debug Info (Remove in production) -->
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Farmer ID: <?php echo $farmer_id; ?><br>
                    Total Sales: Rs. <?php echo number_format($total_stats['total_sales'] ?? 0, 2); ?><br>
                    Filtered Sales: Rs. <?php echo number_format($filtered_stats['filtered_sales'] ?? 0, 2); ?>
                </div>

                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-chart-line me-2" style="color: var(--farmer-green);"></i>
                                Sales Analytics Dashboard
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Track your sales performance, analyze trends, and optimize your farm business.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--farmer-green), #219653); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(39, 174, 96, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>

                <!-- Date Filter -->
                <div class="filter-card">
                    <h5 class="mb-3">
                        <i class="fas fa-filter me-2" style="color: var(--farmer-blue);"></i>
                        Filter Sales Data
                    </h5>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-chart-bar me-1"></i> Apply Filter
                                </button>
                                <a href="my_sales.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i> Reset
                                </a>
                                <button type="button" class="btn btn-outline-success" onclick="exportData()">
                                    <i class="fas fa-download me-1"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="badge bg-success p-2">
                                Filtered Period: 
                                <?php echo date('M d', strtotime($start_date)); ?> - 
                                <?php echo date('M d, Y', strtotime($end_date)); ?>
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #219653);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($filtered_stats['filtered_sales'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Filtered Sales Revenue</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-chart-line me-1"></i> 
                                        <?php echo $filtered_stats['filtered_orders'] ?? 0; ?> orders
                                        <br>
                                        <i class="fas fa-box me-1"></i> 
                                        <?php echo $filtered_stats['filtered_quantity'] ?? 0; ?> units sold
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-coins"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($filtered_stats['filtered_avg_value'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Average Order Value</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-users me-1"></i> 
                                        <?php echo $customer_count['total_customers'] ?? 0; ?> total customers
                                        <br>
                                        <i class="fas fa-leaf me-1"></i> 
                                        <?php echo $product_count['total_products'] ?? 0; ?> products
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">Rs. <?php echo number_format($today_sales['today_sales'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Today's Sales</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-calendar-check me-1"></i> 
                                        This month: Rs. <?php echo number_format($month_sales['month_sales'] ?? 0, 2); ?>
                                        <br>
                                        <i class="fas fa-trophy me-1"></i> 
                                        Total: Rs. <?php echo number_format($total_stats['total_sales'] ?? 0, 2); ?>
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-sun"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value">
                                        <?php echo number_format($growth_percentage ?? 0, 1); ?>%
                                        <span class="trend-icon">
                                            <?php if (($growth_percentage ?? 0) >= 0): ?>
                                                <i class="fas fa-arrow-up"></i>
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="stat-label">Monthly Growth</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-star me-1"></i> 
                                        Avg Rating: <?php echo number_format($avg_rating['avg_rating'] ?? 0, 1); ?>/5
                                        <br>
                                        <i class="fas fa-chart-bar me-1"></i> 
                                        Current vs Last Month
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Daily Sales Chart -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-area me-2" style="color: var(--farmer-green);"></i>
                                    Daily Sales Trend (Last 30 Days)
                                </h5>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary active" onclick="showDailyChart()">
                                        <i class="fas fa-rupee-sign me-1"></i> Revenue
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="showOrdersChart()">
                                        <i class="fas fa-shopping-cart me-1"></i> Orders
                                    </button>
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="dailySalesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Sales Chart -->
                    <div class="col-md-4">
                        <div class="analytics-card h-100">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-bar me-2" style="color: var(--farmer-blue);"></i>
                                Monthly Performance
                            </h5>
                            <div class="chart-container">
                                <canvas id="monthlySalesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Products & Sales by Category -->
                <div class="row mb-4">
                    <!-- Top Selling Products -->
                    <div class="col-md-6">
                        <div class="analytics-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2" style="color: var(--farmer-gold);"></i>
                                    Top Selling Products
                                </h5>
                                <span class="badge bg-primary">
                                    <?php echo $top_products->num_rows; ?> Products
                                </span>
                            </div>
                            
                            <?php if($top_products->num_rows > 0): 
                                $rank = 1;
                                $top_products->data_seek(0);
                                while($product = $top_products->fetch_assoc()): 
                                    $percentage = ($total_stats['total_sales'] ?? 0) > 0 ? 
                                        ($product['total_revenue'] / $total_stats['total_sales']) * 100 : 0;
                            ?>
                            <div class="product-performance">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-primary me-2">#<?php echo $rank++; ?></span>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <span class="category-badge ms-2"><?php echo $product['category']; ?></span>
                                    </div>
                                    <div class="fw-bold text-success">
                                        Rs. <?php echo number_format($product['total_revenue'], 2); ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-box me-1"></i>
                                            Sold: <?php echo $product['total_sold']; ?> units
                                        </small>
                                        <small class="text-muted ms-3">
                                            <i class="fas fa-tag me-1"></i>
                                            Avg: Rs. <?php echo number_format($product['avg_price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <i class="fas fa-chart-pie me-1"></i>
                                            <?php echo number_format($percentage, 1); ?>% of total
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-cubes me-1"></i>
                                            Stock: <?php echo $product['stock']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h5 class="text-muted mb-3">No sales data</h5>
                                <p class="text-muted mb-4">
                                    Your products haven't been sold yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sales by Category & Weekly Pattern -->
                    <div class="col-md-6">
                        <div class="row h-100">
                            <div class="col-12 mb-4">
                                <div class="analytics-card h-100">
                                    <h5 class="mb-3">
                                        <i class="fas fa-chart-pie me-2" style="color: var(--farmer-brown);"></i>
                                        Sales by Category
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="categoryChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="analytics-card h-100">
                                    <h5 class="mb-3">
                                        <i class="fas fa-calendar-week me-2" style="color: var(--farmer-blue);"></i>
                                        Weekly Sales Pattern
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="weeklyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales Transactions -->
                <div class="row">
                    <div class="col-12">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2" style="color: var(--farmer-green);"></i>
                                    Recent Sales Transactions
                                </h5>
                                <span class="badge bg-info">
                                    Showing <?php echo $recent_sales->num_rows; ?> transactions
                                </span>
                            </div>
                            
                            <?php if($recent_sales->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date & Time</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                            <th>Payment</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $recent_sales->data_seek(0);
                                        while($sale = $recent_sales->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <a href="#" class="text-decoration-none fw-bold">
                                                    #<?php echo str_pad($sale['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d, Y', strtotime($sale['created_at'])); ?>
                                                    <br>
                                                    <?php echo date('h:i A', strtotime($sale['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($sale['product_name']); ?></small>
                                            </td>
                                            <td><?php echo $sale['quantity']; ?></td>
                                            <td>Rs. <?php echo number_format($sale['price'], 2); ?></td>
                                            <td class="fw-bold text-success">
                                                Rs. <?php echo number_format($sale['total_price'], 2); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $payment_class = '';
                                                switch($sale['payment_method']) {
                                                    case 'cash_on_delivery':
                                                        $payment_class = 'badge-cod';
                                                        $payment_text = 'COD';
                                                        break;
                                                    case 'credit_card':
                                                        $payment_class = 'badge-card';
                                                        $payment_text = 'Card';
                                                        break;
                                                    case 'paypal':
                                                        $payment_class = 'badge-online';
                                                        $payment_text = 'Online';
                                                        break;
                                                    default:
                                                        $payment_class = 'badge-secondary';
                                                        $payment_text = $sale['payment_method'];
                                                }
                                                ?>
                                                <span class="payment-badge <?php echo $payment_class; ?>">
                                                    <?php echo $payment_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch($sale['status']) {
                                                    case 'Completed':
                                                    case 'Delivered':
                                                        $status_class = 'text-success';
                                                        break;
                                                    case 'Confirmed':
                                                    case 'Shipped':
                                                        $status_class = 'text-primary';
                                                        break;
                                                    default:
                                                        $status_class = 'text-warning';
                                                }
                                                ?>
                                                <span class="badge bg-success">
                                                    <?php echo $sale['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h5 class="text-muted mb-3">No transactions found</h5>
                                <p class="text-muted mb-4">
                                    There are no sales transactions for your products yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Business Insights -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="info-box">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="mb-3">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Business Insights
                                    </h5>
                                    <p class="mb-2">
                                        Based on your sales data from 
                                        <?php echo date('M d', strtotime($start_date)); ?> to 
                                        <?php echo date('M d, Y', strtotime($end_date)); ?>:
                                    </p>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="insight-card">
                                                <h6>
                                                    <i class="fas fa-chart-line me-2 text-primary"></i>
                                                    Sales Performance
                                                </h6>
                                                <p class="mb-1 small">
                                                    <?php if (($growth_percentage ?? 0) > 0): ?>
                                                        <span class="growth-positive">
                                                            <i class="fas fa-arrow-up me-1"></i>
                                                            Sales are growing by <?php echo number_format($growth_percentage, 1); ?>% this month
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="growth-negative">
                                                            <i class="fas fa-arrow-down me-1"></i>
                                                            Sales decreased by <?php echo number_format(abs($growth_percentage), 1); ?>% this month
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="insight-card">
                                                <h6>
                                                    <i class="fas fa-users me-2 text-success"></i>
                                                    Customer Base
                                                </h6>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    You have <?php echo $customer_count['total_customers'] ?? 0; ?> customers
                                                </p>
                                                <p class="mb-0 small">
                                                    <i class="fas fa-shopping-bag me-1"></i>
                                                    Average order value: Rs. <?php echo number_format($filtered_stats['filtered_avg_value'] ?? 0, 2); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-center justify-content-center">
                                    <div class="text-center">
                                        <div class="display-4 mb-2"><?php echo number_format($filtered_stats['filtered_orders'] ?? 0); ?></div>
                                        <div class="mb-3">Completed Orders</div>
                                        <?php 
                                        $progress = 0;
                                        if (($filtered_stats['filtered_quantity'] ?? 0) > 0) {
                                            $progress = min(100, (($filtered_stats['filtered_quantity'] ?? 0) / 1000) * 100);
                                        }
                                        ?>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%" 
                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="mt-2 d-block">
                                            <?php echo $filtered_stats['filtered_quantity'] ?? 0; ?> units sold
                                        </small>
                                    </div>
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
                                <i class="fas fa-chart-line me-1"></i> 
                                Sales Analytics Dashboard • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-calendar me-1"></i> 
                                Period: <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-coins me-1"></i> 
                                Total Revenue: Rs. <?php echo number_format($total_stats['total_sales'] ?? 0, 2); ?>
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
        // Chart.js configurations
        let dailySalesChart, monthlySalesChart, categoryChart, weeklyChart;
        let chartMode = 'revenue'; // 'revenue' or 'orders'
        
        // Initialize charts
        function initCharts() {
            // Daily Sales Chart
            const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
            dailySalesChart = new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($daily_sales_labels); ?>,
                    datasets: [{
                        label: 'Daily Revenue (Rs.)',
                        data: <?php echo json_encode($daily_sales_values); ?>,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Daily Orders',
                        data: <?php echo json_encode($daily_orders_values); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        hidden: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (chartMode === 'revenue') {
                                        return 'Rs. ' + value.toLocaleString();
                                    } else {
                                        return value;
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (chartMode === 'revenue') {
                                        return 'Revenue: Rs. ' + context.parsed.y.toLocaleString();
                                    } else {
                                        return 'Orders: ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            
            // Monthly Sales Chart
            const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
            monthlySalesChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthly_labels); ?>,
                    datasets: [{
                        label: 'Monthly Revenue (Rs.)',
                        data: <?php echo json_encode($monthly_sales); ?>,
                        backgroundColor: 'rgba(39, 174, 96, 0.7)',
                        borderColor: '#27ae60',
                        borderWidth: 1
                    }, {
                        label: 'Monthly Orders',
                        data: <?php echo json_encode($monthly_orders); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: '#3498db',
                        borderWidth: 1,
                        hidden: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rs. ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
            
            // Prepare category data
            <?php
            $category_labels = [];
            $category_data = [];
            $category_colors = [
                '#27ae60', '#3498db', '#f39c12', '#e74c3c', '#9b59b6',
                '#1abc9c', '#d35400', '#c0392b', '#16a085', '#8e44ad'
            ];
            
            if ($category_sales->num_rows > 0) {
                $category_sales->data_seek(0);
                $color_index = 0;
                while ($cat = $category_sales->fetch_assoc()) {
                    $category_labels[] = $cat['category'];
                    $category_data[] = (float)$cat['category_sales'];
                    $color_index = ($color_index + 1) % count($category_colors);
                }
            }
            ?>
            
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($category_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($category_data); ?>,
                        backgroundColor: <?php echo json_encode($category_colors); ?>.slice(0, <?php echo count($category_data); ?>),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': Rs. ' + context.parsed.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Weekly Chart
            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            weeklyChart = new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($weekly_labels); ?>,
                    datasets: [{
                        label: 'Sales by Day (Rs.)',
                        data: <?php echo json_encode($weekly_sales); ?>,
                        backgroundColor: 'rgba(155, 89, 182, 0.7)',
                        borderColor: '#9b59b6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rs. ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Toggle between revenue and orders in daily chart
        function showDailyChart() {
            chartMode = 'revenue';
            dailySalesChart.data.datasets[0].hidden = false;
            dailySalesChart.data.datasets[1].hidden = true;
            dailySalesChart.options.scales.y.ticks.callback = function(value) {
                return 'Rs. ' + value.toLocaleString();
            };
            dailySalesChart.update();
            
            // Update button states
            $('.btn-group .btn').removeClass('active');
            $('.btn-group .btn:first').addClass('active');
        }
        
        function showOrdersChart() {
            chartMode = 'orders';
            dailySalesChart.data.datasets[0].hidden = true;
            dailySalesChart.data.datasets[1].hidden = false;
            dailySalesChart.options.scales.y.ticks.callback = function(value) {
                return value;
            };
            dailySalesChart.update();
            
            // Update button states
            $('.btn-group .btn').removeClass('active');
            $('.btn-group .btn:last').addClass('active');
        }
        
        // Export data function
        function exportData() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            // Create a simple CSV download
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Sales Report for " + startDate + " to " + endDate + "\n\n";
            csvContent += "Metric,Value\n";
            csvContent += "Filtered Sales Revenue,Rs. " + <?php echo $filtered_stats['filtered_sales'] ?? 0; ?> + "\n";
            csvContent += "Filtered Orders," + <?php echo $filtered_stats['filtered_orders'] ?? 0; ?> + "\n";
            csvContent += "Total Units Sold," + <?php echo $filtered_stats['filtered_quantity'] ?? 0; ?> + "\n";
            csvContent += "Average Order Value,Rs. " + <?php echo $filtered_stats['filtered_avg_value'] ?? 0; ?> + "\n";
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "sales_report_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Initialize on page load
        $(document).ready(function() {
            initCharts();
            
            // Set max date for date inputs
            const today = new Date().toISOString().split('T')[0];
            $('#start_date').attr('max', today);
            $('#end_date').attr('max', today);
            
            // Add hover effect to cards
            $('.stat-card, .analytics-card').hover(
                function() {
                    $(this).css('transform', 'translateY(-5px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
            
            // Validate date range
            $('#start_date, #end_date').on('change', function() {
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());
                
                if (startDate > endDate) {
                    alert('Start date cannot be after end date!');
                    $('#end_date').val($('#start_date').val());
                }
            });
            
            // Show debug info on Ctrl+D
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'd') {
                    $('.debug-info').toggle();
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>