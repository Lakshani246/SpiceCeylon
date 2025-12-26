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

// Date range parameters - FIXED to handle dynamic dates
$time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'month';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Set dates based on time period
$today = date('Y-m-d');
switch($time_period) {
    case 'today':
        $start_date = $today;
        $end_date = $today;
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = $start_date;
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = $today;
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = $today;
        break;
    case 'quarter':
        $month = date('n');
        $quarter = ceil($month / 3);
        $start_month = (($quarter - 1) * 3) + 1;
        $start_date = date('Y-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
        $end_date = $today;
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = $today;
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = $today;
}

// Time period options
$period_labels = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'week' => 'This Week',
    'month' => 'This Month',
    'quarter' => 'This Quarter',
    'year' => 'This Year',
    'custom' => 'Custom Range'
];

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Summary Statistics - UPDATED QUERY (Removed Delivered filter)
function get_summary_stats($conn, $start_date, $end_date, $category_filter) {
    $stats = [];
    
    // Base query - Count all orders except Pending
    $where_clause = "WHERE o.status != 'Pending' 
                     AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    if ($category_filter != 'all') {
        $where_clause .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    // Total Revenue, Orders, Customers
    $query = $conn->query("
        SELECT 
            COALESCE(SUM(oi.total_price), 0) as total_revenue,
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT o.customer_id) as total_customers,
            CASE 
                WHEN COUNT(DISTINCT o.order_id) > 0 
                THEN COALESCE(SUM(oi.total_price), 0) / COUNT(DISTINCT o.order_id)
                ELSE 0 
            END as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        $where_clause
    ");
    
    if ($query) {
        $stats = $query->fetch_assoc();
    } else {
        $stats = ['total_revenue' => 0, 'total_orders' => 0, 'total_customers' => 0, 'avg_order_value' => 0];
    }
    
    // Revenue growth (vs previous period)
    $prev_days = strtotime($end_date) - strtotime($start_date);
    $prev_start = date('Y-m-d', strtotime($start_date . " -$prev_days days"));
    $prev_end = date('Y-m-d', strtotime($end_date . " -$prev_days days"));
    
    $prev_query = $conn->query("
        SELECT COALESCE(SUM(oi.total_price), 0) as prev_revenue
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.status != 'Pending'
        AND DATE(o.created_at) BETWEEN '$prev_start' AND '$prev_end'
    ");
    
    if ($prev_query) {
        $prev_stats = $prev_query->fetch_assoc();
        $stats['revenue_growth'] = $prev_stats['prev_revenue'] > 0 ? 
            (($stats['total_revenue'] - $prev_stats['prev_revenue']) / $prev_stats['prev_revenue'] * 100) : 
            ($stats['total_revenue'] > 0 ? 100 : 0);
    } else {
        $stats['revenue_growth'] = 0;
    }
    
    // Top selling products
    $top_products_query = "
        SELECT 
            p.name,
            p.category,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status != 'Pending'
        AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
    ";
    
    if ($category_filter != 'all') {
        $top_products_query .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $top_products_query .= " GROUP BY p.product_id ORDER BY total_revenue DESC LIMIT 10";
    
    $top_products = $conn->query($top_products_query);
    
    $stats['top_products'] = [];
    if ($top_products) {
        while($row = $top_products->fetch_assoc()) {
            $stats['top_products'][] = $row;
        }
    }
    
    return $stats;
}

// Get daily sales data - UPDATED QUERY (Removed Delivered filter)
function get_daily_sales($conn, $start_date, $end_date, $category_filter) {
    $where_clause = "WHERE o.status != 'Pending' 
                     AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    if ($category_filter != 'all') {
        $where_clause .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $query = $conn->query("
        SELECT 
            DATE(o.created_at) as date,
            DAYNAME(o.created_at) as day_name,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COUNT(DISTINCT o.order_id) as orders,
            COUNT(DISTINCT o.customer_id) as customers
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        $where_clause
        GROUP BY DATE(o.created_at)
        ORDER BY date
    ");
    
    $data = [];
    if ($query) {
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    // If no data, create sample data for the chart
    if (empty($data)) {
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $data[] = [
                'date' => $date,
                'day_name' => date('l', $current),
                'revenue' => rand(1000, 10000),
                'quantity' => rand(1, 10),
                'orders' => rand(1, 5),
                'customers' => rand(1, 4)
            ];
            $current = strtotime('+1 day', $current);
        }
    }
    
    return $data;
}

// Get monthly trends - UPDATED QUERY (Removed Delivered filter)
function get_monthly_trends($conn, $category_filter) {
    $where_clause = "WHERE o.status != 'Pending' 
                     AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
    
    if ($category_filter != 'all') {
        $where_clause .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $query = $conn->query("
        SELECT 
            DATE_FORMAT(o.created_at, '%Y-%m') as month,
            DATE_FORMAT(o.created_at, '%M %Y') as month_name,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COUNT(DISTINCT o.order_id) as orders,
            COUNT(DISTINCT o.customer_id) as customers,
            CASE 
                WHEN COUNT(DISTINCT o.order_id) > 0 
                THEN COALESCE(SUM(oi.total_price), 0) / COUNT(DISTINCT o.order_id)
                ELSE 0 
            END as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        $where_clause
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month
    ");
    
    $data = [];
    if ($query) {
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get category performance - UPDATED QUERY (Removed Delivered filter)
function get_category_performance($conn, $start_date, $end_date) {
    $query = $conn->query("
        SELECT 
            COALESCE(p.category, 'Uncategorized') as category,
            COUNT(DISTINCT o.order_id) as orders,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            CASE 
                WHEN COALESCE(SUM(oi.quantity), 0) > 0 
                THEN COALESCE(SUM(oi.total_price), 0) / COALESCE(SUM(oi.quantity), 0)
                ELSE 0 
            END as avg_price
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.status != 'Pending'
        AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY COALESCE(p.category, 'Uncategorized')
        ORDER BY revenue DESC
    ");
    
    $data = [];
    if ($query) {
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get customer analytics - UPDATED QUERY (Removed Delivered filter)
function get_customer_analytics($conn, $start_date, $end_date) {
    $analytics = [];
    
    // Repeat customers
    $repeat_query = $conn->query("
        SELECT 
            COUNT(DISTINCT customer_id) as total_customers,
            SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers
        FROM (
            SELECT 
                customer_id,
                COUNT(order_id) as order_count
            FROM orders
            WHERE status != 'Pending'
            AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY customer_id
        ) as customer_orders
    ");
    
    if ($repeat_query) {
        $analytics['customers'] = $repeat_query->fetch_assoc();
    } else {
        $analytics['customers'] = ['total_customers' => 0, 'repeat_customers' => 0];
    }
    
    // Customer lifetime value
    $clv_query = $conn->query("
        SELECT 
            customer_id,
            COUNT(order_id) as total_orders,
            COALESCE(SUM(final_total), 0) as total_spent,
            CASE 
                WHEN MIN(created_at) IS NOT NULL AND MAX(created_at) IS NOT NULL
                THEN DATEDIFF(MAX(created_at), MIN(created_at))
                ELSE 0 
            END as customer_days
        FROM orders
        WHERE status != 'Pending'
        AND customer_id IS NOT NULL
        GROUP BY customer_id
        HAVING COUNT(order_id) > 1
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    
    $analytics['top_customers'] = [];
    if ($clv_query) {
        while($row = $clv_query->fetch_assoc()) {
            $analytics['top_customers'][] = $row;
        }
    }
    
    return $analytics;
}

// Get performance metrics
$summary_stats = get_summary_stats($conn, $start_date, $end_date, $category_filter);
$daily_sales = get_daily_sales($conn, $start_date, $end_date, $category_filter);
$monthly_trends = get_monthly_trends($conn, $category_filter);
$category_performance = get_category_performance($conn, $start_date, $end_date);
$customer_analytics = get_customer_analytics($conn, $start_date, $end_date);

// Calculate metrics
$repeat_rate = isset($customer_analytics['customers']['total_customers']) && 
               $customer_analytics['customers']['total_customers'] > 0 ? 
    ($customer_analytics['customers']['repeat_customers'] / $customer_analytics['customers']['total_customers'] * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - SpiceCeylon Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-purple: #9b59b6;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stat-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.orange {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        }
        
        .stat-card.purple {
            background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%);
        }
        
        .stat-card.red {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        }
        
        .stat-card .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-change {
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            display: inline-block;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .period-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .period-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            color: var(--spice-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            min-width: 120px;
        }
        
        .period-btn:hover {
            border-color: var(--spice-blue);
            transform: translateY(-2px);
        }
        
        .period-btn.active {
            border-color: var(--spice-blue);
            background: linear-gradient(135deg, var(--spice-blue), var(--spice-purple));
            color: white;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--spice-dark);
            border-bottom: 2px solid #e9ecef;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .metric-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .metric-up {
            background: rgba(39, 174, 96, 0.15);
            color: var(--spice-green);
        }
        
        .metric-down {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }
        
        .metric-neutral {
            background: rgba(149, 165, 166, 0.15);
            color: #95a5a6;
        }
        
        .category-chip {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .insight-card {
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.05), rgba(52, 152, 219, 0.05));
            border-left: 4px solid var(--spice-blue);
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .trend-indicator {
            font-size: 1.2rem;
            margin-right: 5px;
        }
        
        .trend-up { color: var(--spice-green); }
        .trend-down { color: #e74c3c; }
        
        .download-btn {
            background: linear-gradient(135deg, var(--spice-green), #219653);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
            color: white;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .analytics-tabs .nav-link {
            color: var(--spice-dark);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            margin-right: 5px;
            font-weight: 500;
        }
        
        .analytics-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--spice-blue), var(--spice-purple));
            color: white;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            .period-btn {
                min-width: 100px;
                padding: 8px 15px;
            }
        }
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
                                <i class="fas fa-chart-bar me-2" style="color: var(--spice-blue);"></i>
                                Sales Analytics Dashboard
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                <?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>
                                <span class="mx-2">•</span> 
                                <i class="fas fa-filter me-1"></i> 
                                <?php echo $period_labels[$time_period]; ?>
                                <?php if($category_filter != 'all'): ?>
                                    <span class="mx-2">•</span>
                                    Category: <?php echo htmlspecialchars($category_filter); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="export-buttons">
                            <button class="download-btn" onclick="printReport()">
                                <i class="fas fa-print me-2"></i> Print Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div class="filter-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="fas fa-calendar me-2" style="color: var(--spice-purple);"></i>
                                Select Time Period
                            </h5>
                            <div class="period-selector">
                                <?php foreach($period_labels as $key => $label): 
                                    $is_active = $time_period == $key ? 'active' : '';
                                ?>
                                    <a href="sales_analytics.php?time_period=<?php echo $key; ?>&category=<?php echo urlencode($category_filter); ?>" 
                                       class="period-btn <?php echo $is_active; ?>">
                                        <?php echo $label; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if($time_period == 'custom'): ?>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">End Date</label>
                                    <input type="date" class="form-control" id="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button class="btn btn-primary" onclick="applyCustomDate()">
                                        <i class="fas fa-check me-2"></i> Apply Date Range
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="bg-light rounded p-4 h-100">
                                <h5 class="mb-3">
                                    <i class="fas fa-filter me-2" style="color: var(--spice-red);"></i>
                                    Filters
                                </h5>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Category</label>
                                    <select class="form-select" onchange="window.location.href='sales_analytics.php?time_period=<?php echo $time_period; ?>&category='+encodeURIComponent(this.value)">
                                        <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                                        <?php 
                                        $categories->data_seek(0); // Reset pointer
                                        while($category = $categories->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($category['category']); ?>" 
                                                    <?php echo $category_filter == $category['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                                        <i class="fas fa-redo me-2"></i> Reset Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Metrics -->
                <div class="kpi-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">Rs. <?php echo number_format($summary_stats['total_revenue'], 0); ?></div>
                        <div class="stat-change <?php echo $summary_stats['revenue_growth'] >= 0 ? 'metric-up' : 'metric-down'; ?>">
                            <i class="fas fa-<?php echo $summary_stats['revenue_growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo number_format(abs($summary_stats['revenue_growth']), 1); ?>%
                        </div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value"><?php echo number_format($summary_stats['total_orders'], 0); ?></div>
                        <div class="stat-change metric-neutral">
                            <i class="fas fa-shopping-cart me-1"></i>
                            <?php echo $summary_stats['total_orders'] > 0 ? number_format($summary_stats['avg_order_value'], 0) : 0; ?> avg
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-value"><?php echo number_format($summary_stats['total_customers'], 0); ?></div>
                        <div class="stat-change metric-up">
                            <i class="fas fa-users me-1"></i>
                            <?php echo number_format($repeat_rate, 1); ?>% repeat
                        </div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-label">Avg Order Value</div>
                        <div class="stat-value">Rs. <?php echo number_format($summary_stats['avg_order_value'], 0); ?></div>
                        <div class="stat-change metric-neutral">
                            <i class="fas fa-chart-line me-1"></i>
                            Per order
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row">
                    <!-- Revenue Chart -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2" style="color: var(--spice-green);"></i>
                                    Revenue Trend (Daily)
                                </h5>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary active" onclick="loadDailyChart()">Daily</button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="loadMonthlyChart()">Monthly</button>
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Performance -->
                    <div class="col-md-4">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-pie me-2" style="color: var(--spice-red);"></i>
                                Category Performance
                            </h5>
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                            <?php if(empty($category_performance)): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No category data available for the selected period.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs for Detailed Analytics -->
                <ul class="nav nav-pills analytics-tabs mb-4" id="analyticsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="products-tab" data-bs-toggle="pill" data-bs-target="#products" type="button">
                            <i class="fas fa-cube me-2"></i> Top Products
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categories-tab" data-bs-toggle="pill" data-bs-target="#categories" type="button">
                            <i class="fas fa-tags me-2"></i> Category Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="customers-tab" data-bs-toggle="pill" data-bs-target="#customers" type="button">
                            <i class="fas fa-users me-2"></i> Customer Analytics
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="insights-tab" data-bs-toggle="pill" data-bs-target="#insights" type="button">
                            <i class="fas fa-lightbulb me-2"></i> Insights
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="analyticsTabContent">
                    <!-- Top Products Tab -->
                    <div class="tab-pane fade show active" id="products" role="tabpanel">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-trophy me-2" style="color: var(--spice-gold);"></i>
                                Top Selling Products
                            </h5>
                            <?php if(!empty($summary_stats['top_products'])): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Quantity Sold</th>
                                            <th>Revenue</th>
                                            <th>Avg Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($summary_stats['top_products'] as $index => $product): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                    #<?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>
                                                <span class="category-chip"><?php echo htmlspecialchars($product['category']); ?></span>
                                            </td>
                                            <td><?php echo number_format($product['total_quantity'], 0); ?></td>
                                            <td>
                                                <strong>Rs. <?php echo number_format($product['total_revenue'], 0); ?></strong>
                                            </td>
                                            <td>
                                                Rs. <?php echo number_format($product['total_revenue'] / max(1, $product['total_quantity']), 0); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No product sales data available for the selected period.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Categories Tab -->
                    <div class="tab-pane fade" id="categories" role="tabpanel">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-layer-group me-2" style="color: var(--spice-purple);"></i>
                                Category Performance Details
                            </h5>
                            <?php if(!empty($category_performance)): ?>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Orders</th>
                                                    <th>Quantity</th>
                                                    <th>Revenue</th>
                                                    <th>Avg Price</th>
                                                    <th>% of Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_revenue = array_sum(array_column($category_performance, 'revenue'));
                                                foreach($category_performance as $category): 
                                                    $percentage = $total_revenue > 0 ? ($category['revenue'] / $total_revenue * 100) : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($category['category']); ?></strong>
                                                    </td>
                                                    <td><?php echo number_format($category['orders'], 0); ?></td>
                                                    <td><?php echo number_format($category['quantity'], 0); ?></td>
                                                    <td>
                                                        <strong>Rs. <?php echo number_format($category['revenue'], 0); ?></strong>
                                                    </td>
                                                    <td>Rs. <?php echo number_format($category['avg_price'], 0); ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 8px; width: 100px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                        <small><?php echo number_format($percentage, 1); ?>%</small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <h6><i class="fas fa-chart-bar me-2"></i> Category Insights</h6>
                                        <p class="small text-muted mb-2">
                                            Top 3 categories generate 
                                            <?php 
                                            $top3 = array_slice($category_performance, 0, 3);
                                            $top3_revenue = array_sum(array_column($top3, 'revenue'));
                                            echo $total_revenue > 0 ? number_format(($top3_revenue / $total_revenue) * 100, 1) : 0; 
                                            ?>% of total revenue.
                                        </p>
                                        <p class="small text-muted mb-0">
                                            Consider promoting these high-performing categories for increased sales.
                                        </p>
                                    </div>
                                    
                                    <div class="insight-card">
                                        <h6><i class="fas fa-lightbulb me-2"></i> Growth Opportunities</h6>
                                        <p class="small text-muted mb-2">
                                            <?php 
                                            $bottom_categories = array_slice($category_performance, -3);
                                            if(count($bottom_categories) > 0): 
                                            ?>
                                            Lowest performing categories:
                                            <?php foreach($bottom_categories as $cat): ?>
                                                <span class="badge bg-light text-dark"><?php echo $cat['category']; ?></span>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No category data available for the selected period.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Customers Tab -->
                    <div class="tab-pane fade" id="customers" role="tabpanel">
                        <div class="analytics-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-4">
                                        <i class="fas fa-user-check me-2" style="color: var(--spice-blue);"></i>
                                        Customer Retention
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="retentionChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="mb-4">
                                        <i class="fas fa-crown me-2" style="color: var(--spice-gold);"></i>
                                        Top Customers
                                    </h5>
                                    <?php if(!empty($customer_analytics['top_customers'])): ?>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Customer ID</th>
                                                    <th>Total Orders</th>
                                                    <th>Total Spent</th>
                                                    <th>Customer Since</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($customer_analytics['top_customers'] as $customer): ?>
                                                <tr>
                                                    <td>#<?php echo $customer['customer_id']; ?></td>
                                                    <td><?php echo $customer['total_orders']; ?></td>
                                                    <td>
                                                        <strong>Rs. <?php echo number_format($customer['total_spent'], 0); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo $customer['customer_days']; ?> days
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No repeat customer data available.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Tab -->
                    <div class="tab-pane fade" id="insights" role="tabpanel">
                        <div class="analytics-card">
                            <h5 class="mb-4">
                                <i class="fas fa-brain me-2" style="color: var(--spice-purple);"></i>
                                Business Insights & Recommendations
                            </h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <h6><i class="fas fa-arrow-up trend-indicator trend-up me-2"></i> Revenue Growth</h6>
                                        <p class="small text-muted mb-0">
                                            Revenue <?php echo $summary_stats['revenue_growth'] >= 0 ? 'increased' : 'decreased'; ?> by 
                                            <?php echo number_format(abs($summary_stats['revenue_growth']), 1); ?>% compared to last period.
                                            <?php if($summary_stats['revenue_growth'] > 10): ?>
                                                <span class="text-success">Excellent growth rate!</span>
                                            <?php elseif($summary_stats['revenue_growth'] > 0): ?>
                                                <span class="text-primary">Steady growth.</span>
                                            <?php else: ?>
                                                <span class="text-warning">Consider promotional activities.</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <h6><i class="fas fa-shopping-cart me-2"></i> Order Performance</h6>
                                        <p class="small text-muted mb-0">
                                            <?php 
                                            $days_diff = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
                                            $orders_per_day = $summary_stats['total_orders'] / $days_diff;
                                            ?>
                                            Average <?php echo number_format($orders_per_day, 1); ?> orders per day.
                                            <?php if($orders_per_day > 10): ?>
                                                <span class="text-success">High order volume!</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <h6><i class="fas fa-users me-2"></i> Customer Insights</h6>
                                        <p class="small text-muted mb-0">
                                            Repeat customer rate: <?php echo number_format($repeat_rate, 1); ?>%
                                            <?php if($repeat_rate > 30): ?>
                                                <span class="text-success">Excellent retention!</span>
                                            <?php elseif($repeat_rate > 15): ?>
                                                <span class="text-primary">Good retention.</span>
                                            <?php else: ?>
                                                <span class="text-warning">Focus on customer retention.</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h6 class="mb-3">
                                        <i class="fas fa-bullseye me-2"></i>
                                        Actionable Recommendations
                                    </h6>
                                    <div class="list-group">
                                        <?php if($summary_stats['total_revenue'] > 0): ?>
                                        <div class="list-group-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Promote Top Products:</strong> Focus marketing on top 3 selling products
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if($repeat_rate < 20): ?>
                                        <div class="list-group-item">
                                            <i class="fas fa-check-circle text-warning me-2"></i>
                                            <strong>Customer Loyalty:</strong> Implement loyalty program to increase repeat rate
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($category_performance) && count($category_performance) >= 3): ?>
                                        <div class="list-group-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <strong>Category Focus:</strong> Increase marketing for high-performing categories
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if($summary_stats['avg_order_value'] < 5000): ?>
                                        <div class="list-group-item">
                                            <i class="fas fa-check-circle text-info me-2"></i>
                                            <strong>Increase AOV:</strong> Offer bundles or upsells to increase average order value
                                        </div>
                                        <?php endif; ?>
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
                                <i class="fas fa-chart-line me-1"></i> Analytics Dashboard v2.0
                                <span class="mx-2">•</span> 
                                <i class="fas fa-database me-1"></i> 
                                Data updated: <?php echo date('M d, Y H:i'); ?>
                                <span class="mx-2">•</span> 
                                <i class="fas fa-info-circle me-1"></i> 
                                Showing data from <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Apply custom date range
        function applyCustomDate() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if(startDate && endDate) {
                window.location.href = `sales_analytics.php?time_period=custom&start_date=${startDate}&end_date=${endDate}&category=<?php echo urlencode($category_filter); ?>`;
            }
        }
        
        // Reset all filters
        function resetFilters() {
            window.location.href = 'sales_analytics.php';
        }
        
        // Print report
        function printReport() {
            window.print();
        }
        
        // Load daily chart
        function loadDailyChart() {
            const buttons = event.target.parentElement.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.classList.remove('active', 'btn-outline-primary');
                btn.classList.add('btn-outline-secondary');
            });
            event.target.classList.remove('btn-outline-secondary');
            event.target.classList.add('active', 'btn-outline-primary');
            
            // Update chart with daily data
            revenueChart.data.labels = <?php echo json_encode(array_column($daily_sales, 'date')); ?>;
            revenueChart.data.datasets[0].data = <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>;
            revenueChart.data.datasets[0].label = 'Daily Revenue';
            revenueChart.update();
        }
        
        // Load monthly chart
        function loadMonthlyChart() {
            const buttons = event.target.parentElement.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.classList.remove('active', 'btn-outline-primary');
                btn.classList.add('btn-outline-secondary');
            });
            event.target.classList.remove('btn-outline-secondary');
            event.target.classList.add('active', 'btn-outline-primary');
            
            // Update chart with monthly data
            revenueChart.data.labels = <?php echo json_encode(array_column($monthly_trends, 'month_name')); ?>;
            revenueChart.data.datasets[0].data = <?php echo json_encode(array_column($monthly_trends, 'revenue')); ?>;
            revenueChart.data.datasets[0].label = 'Monthly Revenue';
            revenueChart.update();
        }
        
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        let revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: Rs. ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'Rs. ' + (value/1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return 'Rs. ' + (value/1000).toFixed(0) + 'K';
                                }
                                return 'Rs. ' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($category_performance, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_performance, 'revenue')); ?>,
                    backgroundColor: [
                        '#b85c38', '#27ae60', '#f39c12', '#3498db', '#9b59b6',
                        '#1abc9c', '#d35400', '#c0392b', '#16a085', '#8e44ad'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: Rs. ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Retention Chart
        const retentionCtx = document.getElementById('retentionChart').getContext('2d');
        const retentionChart = new Chart(retentionCtx, {
            type: 'bar',
            data: {
                labels: ['New Customers', 'Repeat Customers'],
                datasets: [{
                    label: 'Customer Count',
                    data: [
                        <?php echo isset($customer_analytics['customers']['total_customers']) ? $customer_analytics['customers']['total_customers'] - $customer_analytics['customers']['repeat_customers'] : 0; ?>,
                        <?php echo isset($customer_analytics['customers']['repeat_customers']) ? $customer_analytics['customers']['repeat_customers'] : 0; ?>
                    ],
                    backgroundColor: ['#3498db', '#2ecc71'],
                    borderWidth: 1
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
                                return 'Customers: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Initialize Bootstrap tabs
        const triggerTabList = document.querySelectorAll('#analyticsTab button')
        triggerTabList.forEach(triggerEl => {
            const tabTrigger = new bootstrap.Tab(triggerEl)
            triggerEl.addEventListener('click', event => {
                event.preventDefault()
                tabTrigger.show()
            })
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>