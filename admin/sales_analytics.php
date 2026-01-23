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

// Date range parameters
$time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'all_time';
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
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = $today;
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = $today;
        break;
    case 'all_time':
        $start_date = '2020-01-01';
        $end_date = $today;
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = $today;
}

// Time period options
$period_labels = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'week' => 'Last 7 Days',
    'month' => 'Last 30 Days',
    'all_time' => 'All Time',
    'custom' => 'Custom Range'
];

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Functions to get data (same as before)
function get_summary_stats($conn, $start_date, $end_date, $category_filter) {
    $stats = [];
    
    $where_clause = "WHERE DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    if ($category_filter != 'all') {
        $where_clause .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $revenue_query = $conn->query("
        SELECT 
            COALESCE(SUM(o.final_total), 0) as total_revenue,
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT o.customer_id) as total_customers
        FROM orders o
        $where_clause
    ");
    
    if ($revenue_query && $revenue_query->num_rows > 0) {
        $row = $revenue_query->fetch_assoc();
        $stats['total_revenue'] = $row['total_revenue'];
        $stats['total_orders'] = $row['total_orders'];
        $stats['total_customers'] = $row['total_customers'];
        $stats['avg_order_value'] = $row['total_orders'] > 0 ? $row['total_revenue'] / $row['total_orders'] : 0;
    } else {
        $stats = ['total_revenue' => 0, 'total_orders' => 0, 'total_customers' => 0, 'avg_order_value' => 0];
    }
    
    $prev_days = strtotime($end_date) - strtotime($start_date);
    $prev_start = date('Y-m-d', strtotime($start_date . " -$prev_days days"));
    $prev_end = date('Y-m-d', strtotime($end_date . " -$prev_days days"));
    
    $prev_query = $conn->query("
        SELECT COALESCE(SUM(final_total), 0) as prev_revenue
        FROM orders 
        WHERE DATE(created_at) BETWEEN '$prev_start' AND '$prev_end'
    ");
    
    if ($prev_query && $prev_query->num_rows > 0) {
        $prev_row = $prev_query->fetch_assoc();
        $prev_revenue = $prev_row['prev_revenue'];
        
        if ($prev_revenue > 0) {
            $stats['revenue_growth'] = (($stats['total_revenue'] - $prev_revenue) / $prev_revenue) * 100;
        } else {
            $stats['revenue_growth'] = $stats['total_revenue'] > 0 ? 100 : 0;
        }
    } else {
        $stats['revenue_growth'] = 0;
    }
    
    $top_products_query = "
        SELECT 
            p.name,
            p.category,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
    ";
    
    if ($category_filter != 'all') {
        $top_products_query .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $top_products_query .= " GROUP BY p.product_id ORDER BY total_revenue DESC LIMIT 5";
    
    $top_products = $conn->query($top_products_query);
    
    $stats['top_products'] = [];
    if ($top_products) {
        while($row = $top_products->fetch_assoc()) {
            $stats['top_products'][] = $row;
        }
    }
    
    return $stats;
}

function get_daily_sales($conn, $start_date, $end_date, $category_filter) {
    $where_clause = "WHERE DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    if ($category_filter != 'all') {
        $where_clause .= " AND p.category = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    $query = $conn->query("
        SELECT 
            DATE(o.created_at) as date,
            DAYNAME(o.created_at) as day_name,
            COALESCE(SUM(o.final_total), 0) as revenue,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COUNT(DISTINCT o.order_id) as orders,
            COUNT(DISTINCT o.customer_id) as customers
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
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
    
    if (empty($data)) {
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $data[] = [
                'date' => $date,
                'day_name' => date('l', $current),
                'revenue' => 0,
                'quantity' => 0,
                'orders' => 0,
                'customers' => 0
            ];
            $current = strtotime('+1 day', $current);
        }
    }
    
    return $data;
}

function get_monthly_trends($conn) {
    $query = $conn->query("
        SELECT 
            DATE_FORMAT(o.created_at, '%Y-%m') as month,
            DATE_FORMAT(o.created_at, '%b %Y') as month_name,
            COALESCE(SUM(o.final_total), 0) as revenue,
            COUNT(DISTINCT o.order_id) as orders
        FROM orders o
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month
    ");
    
    $data = [];
    if ($query) {
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    $complete_data = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        $found = false;
        foreach ($data as $row) {
            if ($row['month'] == $month) {
                $complete_data[] = $row;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $complete_data[] = [
                'month' => $month,
                'month_name' => $month_name,
                'revenue' => 0,
                'orders' => 0
            ];
        }
    }
    
    return $complete_data;
}

function get_category_performance($conn, $start_date, $end_date) {
    $query = $conn->query("
        SELECT 
            COALESCE(p.category, 'Uncategorized') as category,
            COUNT(DISTINCT o.order_id) as orders,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COALESCE(SUM(oi.total_price), 0) as revenue
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
        GROUP BY COALESCE(p.category, 'Uncategorized')
        ORDER BY revenue DESC
        LIMIT 8
    ");
    
    $data = [];
    if ($query) {
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function get_customer_analytics($conn, $start_date, $end_date) {
    $analytics = [];
    
    $customer_query = $conn->query("
        SELECT 
            customer_id,
            COUNT(order_id) as order_count,
            SUM(final_total) as total_spent
        FROM orders 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
        AND customer_id IS NOT NULL
        GROUP BY customer_id
    ");
    
    $total_customers = 0;
    $repeat_customers = 0;
    $top_customers = [];
    
    if ($customer_query) {
        while($row = $customer_query->fetch_assoc()) {
            $total_customers++;
            if ($row['order_count'] > 1) {
                $repeat_customers++;
            }
            
            $top_customers[] = [
                'customer_id' => $row['customer_id'],
                'total_orders' => $row['order_count'],
                'total_spent' => $row['total_spent']
            ];
        }
    }
    
    usort($top_customers, function($a, $b) {
        return $b['total_spent'] - $a['total_spent'];
    });
    
    $analytics['customers'] = [
        'total_customers' => $total_customers,
        'repeat_customers' => $repeat_customers
    ];
    
    $analytics['top_customers'] = array_slice($top_customers, 0, 5);
    
    return $analytics;
}

// Get performance metrics
$summary_stats = get_summary_stats($conn, $start_date, $end_date, $category_filter);
$daily_sales = get_daily_sales($conn, $start_date, $end_date, $category_filter);
$monthly_trends = get_monthly_trends($conn);
$category_performance = get_category_performance($conn, $start_date, $end_date);
$customer_analytics = get_customer_analytics($conn, $start_date, $end_date);

// Calculate repeat rate
$repeat_rate = 0;
if (isset($customer_analytics['customers']['total_customers']) && 
    $customer_analytics['customers']['total_customers'] > 0) {
    $repeat_rate = ($customer_analytics['customers']['repeat_customers'] / 
                    $customer_analytics['customers']['total_customers']) * 100;
}

// Prepare data for PDF
$pdf_data = [
    'title' => 'SpiceCeylon Sales Analytics Report',
    'period' => date('F d, Y', strtotime($start_date)) . ' - ' . date('F d, Y', strtotime($end_date)),
    'category_filter' => $category_filter,
    'summary_stats' => $summary_stats,
    'repeat_rate' => $repeat_rate,
    'top_products' => $summary_stats['top_products'],
    'category_performance' => $category_performance,
    'top_customers' => $customer_analytics['top_customers'],
    'daily_sales' => $daily_sales
];
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
    <!-- Add jsPDF only (no html2canvas) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
            color: white;
            border-radius: 12px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.revenue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.orders {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.customers {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        }
        
        .stat-card.aov {
            background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%);
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
            height: 300px;
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
        
        /* Data status indicator */
        .data-status {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .data-status.has-data {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
            border-left: 4px solid var(--spice-green);
        }
        
        .data-status.no-data {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
            border-left: 4px solid #f39c12;
        }
        
        /* PDF Loading Indicator (Simplified) */
        .pdf-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .pdf-loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Simple PDF Loading Overlay -->
    <div class="pdf-loading" id="pdfLoading">
        <div class="pdf-loading-content">
            <div class="spinner"></div>
            <h5>Creating PDF...</h5>
            <small>This will take just a moment</small>
        </div>
    </div>
    
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
                            <button class="btn btn-success" onclick="exportToPDF()" id="pdfBtn">
                                <i class="fas fa-file-pdf me-2"></i> Export to PDF
                            </button>
                            <button class="btn btn-outline-primary ms-2" onclick="printPreview()">
                                <i class="fas fa-print me-2"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <!-- Data Status Indicator -->
                    <div class="data-status <?php echo $summary_stats['total_orders'] > 0 ? 'has-data' : 'no-data'; ?> mt-3">
                        <i class="fas fa-<?php echo $summary_stats['total_orders'] > 0 ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php if($summary_stats['total_orders'] > 0): ?>
                            Showing <?php echo $summary_stats['total_orders']; ?> orders with Rs. <?php echo number_format($summary_stats['total_revenue'], 2); ?> revenue
                        <?php else: ?>
                            No order data found for the selected period. Try selecting "All Time" to view all orders.
                        <?php endif; ?>
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
                        </div>
                        
                        <div class="col-md-4">
                            <div class="bg-light rounded p-4 h-100">
                                <h5 class="mb-3">
                                    <i class="fas fa-filter me-2" style="color: var(--spice-red);"></i>
                                    Category Filter
                                </h5>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Category</label>
                                    <select class="form-select" onchange="window.location.href='sales_analytics.php?time_period=<?php echo $time_period; ?>&category='+encodeURIComponent(this.value)">
                                        <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                                        <?php 
                                        $categories->data_seek(0);
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
                                    <a href="sales_analytics.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-redo me-2"></i> Reset Filters
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Metrics -->
                <div class="kpi-grid">
                    <div class="stat-card revenue">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">Rs. <?php echo number_format($summary_stats['total_revenue'], 2); ?></div>
                        <div class="stat-change <?php echo $summary_stats['revenue_growth'] >= 0 ? 'metric-up' : 'metric-down'; ?>">
                            <i class="fas fa-<?php echo $summary_stats['revenue_growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo number_format($summary_stats['revenue_growth'], 1); ?>%
                        </div>
                    </div>
                    
                    <div class="stat-card orders">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value"><?php echo number_format($summary_stats['total_orders'], 0); ?></div>
                        <div class="stat-change metric-neutral">
                            <i class="fas fa-shopping-cart me-1"></i>
                            AOV: Rs. <?php echo number_format($summary_stats['avg_order_value'], 2); ?>
                        </div>
                    </div>
                    
                    <div class="stat-card customers">
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-value"><?php echo number_format($summary_stats['total_customers'], 0); ?></div>
                        <div class="stat-change <?php echo $repeat_rate > 0 ? 'metric-up' : 'metric-neutral'; ?>">
                            <i class="fas fa-users me-1"></i>
                            Repeat: <?php echo number_format($repeat_rate, 1); ?>%
                        </div>
                    </div>
                    
                    <div class="stat-card aov">
                        <div class="stat-label">Avg Order Value</div>
                        <div class="stat-value">Rs. <?php echo number_format($summary_stats['avg_order_value'], 2); ?></div>
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
                                    Revenue Trend
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
                            <?php if(empty($category_performance) || array_sum(array_column($category_performance, 'revenue')) == 0): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No category data available for the selected period.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs for Detailed Analytics -->
                <ul class="nav nav-pills mb-4" id="analyticsTab" role="tablist">
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
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Quantity Sold</th>
                                            <th>Revenue</th>
                                            <th>Avg Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($summary_stats['top_products'] as $product): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                            <td>
                                                <span class="category-chip"><?php echo htmlspecialchars($product['category']); ?></span>
                                            </td>
                                            <td><?php echo number_format($product['total_quantity'], 0); ?></td>
                                            <td>
                                                <strong>Rs. <?php echo number_format($product['total_revenue'], 2); ?></strong>
                                            </td>
                                            <td>
                                                Rs. <?php echo number_format($product['total_revenue'] / max(1, $product['total_quantity']), 2); ?>
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
                                Category Performance
                            </h5>
                            <?php if(!empty($category_performance) && array_sum(array_column($category_performance, 'revenue')) > 0): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Orders</th>
                                            <th>Quantity</th>
                                            <th>Revenue</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_revenue = array_sum(array_column($category_performance, 'revenue'));
                                        foreach($category_performance as $category): 
                                            if($category['revenue'] == 0) continue;
                                            $percentage = $total_revenue > 0 ? ($category['revenue'] / $total_revenue * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                                            <td><?php echo number_format($category['orders'], 0); ?></td>
                                            <td><?php echo number_format($category['quantity'], 0); ?></td>
                                            <td><strong>Rs. <?php echo number_format($category['revenue'], 2); ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small><?php echo number_format($percentage, 1); ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                                    <th>Orders</th>
                                                    <th>Total Spent</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($customer_analytics['top_customers'] as $customer): ?>
                                                <tr>
                                                    <td>#<?php echo $customer['customer_id']; ?></td>
                                                    <td><?php echo $customer['total_orders']; ?></td>
                                                    <td><strong>Rs. <?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No customer data available.
                                    </div>
                                    <?php endif; ?>
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
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Chart initialization (same as before)
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
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: Rs. ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            x: { grid: { color: 'rgba(0,0,0,0.05)' } },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) return 'Rs. ' + (value/1000000).toFixed(1) + 'M';
                        if (value >= 1000) return 'Rs. ' + (value/1000).toFixed(0) + 'K';
                        return 'Rs. ' + value;
                    }
                }
            }
        }
    }
});

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
                labels: { boxWidth: 15, padding: 15 }
            }
        }
    }
});

const retentionCtx = document.getElementById('retentionChart').getContext('2d');
const retentionChart = new Chart(retentionCtx, {
    type: 'doughnut',
    data: {
        labels: ['New Customers', 'Repeat Customers'],
        datasets: [{
            data: [
                <?php echo max(0, $customer_analytics['customers']['total_customers'] - $customer_analytics['customers']['repeat_customers']); ?>,
                <?php echo $customer_analytics['customers']['repeat_customers']; ?>
            ],
            backgroundColor: ['#3498db', '#2ecc71'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'right' } }
    }
});

// FAST PDF Export Function
function exportToPDF() {
    // Show loading indicator
    const pdfBtn = document.getElementById('pdfBtn');
    const originalHTML = pdfBtn.innerHTML;
    pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating PDF...';
    pdfBtn.disabled = true;
    
    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        
        // ========== HEADER ==========
        pdf.setFontSize(24);
        pdf.setTextColor(184, 92, 56); // Spice red
        pdf.text('SPICE CEYLON', pageWidth/2, 20, { align: 'center' });
        
        pdf.setFontSize(16);
        pdf.setTextColor(44, 62, 80); // Spice dark
        pdf.text('Sales Analytics Report', pageWidth/2, 30, { align: 'center' });
        
        // Report details
        pdf.setFontSize(10);
        pdf.setTextColor(100);
        pdf.text('Report Period:', 20, 45);
        pdf.setTextColor(50);
        pdf.text(`<?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>`, 60, 45);
        
        pdf.setTextColor(100);
        pdf.text('Generated:', 20, 50);
        pdf.setTextColor(50);
        pdf.text(new Date().toLocaleString(), 60, 50);
        
        if('<?php echo $category_filter; ?>' !== 'all') {
            pdf.setTextColor(100);
            pdf.text('Category:', 20, 55);
            pdf.setTextColor(50);
            pdf.text('<?php echo htmlspecialchars($category_filter); ?>', 60, 55);
        }
        
        // Separator line
        pdf.setDrawColor(184, 92, 56);
        pdf.line(20, 60, pageWidth - 20, 60);
        
        let yPos = 70;
        
        // ========== KPI METRICS ==========
        pdf.setFontSize(14);
        pdf.setTextColor(44, 62, 80);
        pdf.text('Key Performance Indicators', 20, yPos);
        yPos += 10;
        
        // Create KPI boxes
        const kpiWidth = (pageWidth - 50) / 4;
        
        // Revenue
        pdf.setFillColor(102, 126, 234);
        pdf.roundedRect(20, yPos, kpiWidth, 25, 3, 3, 'F');
        pdf.setTextColor(255);
        pdf.setFontSize(10);
        pdf.text('TOTAL REVENUE', 25, yPos + 8);
        pdf.setFontSize(12);
        const revenueShort = <?php echo $summary_stats['total_revenue']; ?> >= 1000000 ? 
            'Rs. ' + (<?php echo $summary_stats['total_revenue']; ?> / 1000000).toFixed(1) + 'M' : 
            'Rs. ' + (<?php echo $summary_stats['total_revenue']; ?> / 1000).toFixed(0) + 'K';
        pdf.text(revenueShort, 25, yPos + 18);
        
        // Orders
        pdf.setFillColor(17, 153, 142);
        pdf.roundedRect(25 + kpiWidth, yPos, kpiWidth, 25, 3, 3, 'F');
        pdf.setTextColor(255);
        pdf.setFontSize(10);
        pdf.text('TOTAL ORDERS', 30 + kpiWidth, yPos + 8);
        pdf.setFontSize(12);
        pdf.text('<?php echo number_format($summary_stats["total_orders"], 0); ?>', 30 + kpiWidth, yPos + 18);
        
        // Customers
        pdf.setFillColor(247, 151, 30);
        pdf.roundedRect(30 + kpiWidth*2, yPos, kpiWidth, 25, 3, 3, 'F');
        pdf.setTextColor(255);
        pdf.setFontSize(10);
        pdf.text('TOTAL CUSTOMERS', 35 + kpiWidth*2, yPos + 8);
        pdf.setFontSize(12);
        pdf.text('<?php echo number_format($summary_stats["total_customers"], 0); ?>', 35 + kpiWidth*2, yPos + 18);
        
        // AOV
        pdf.setFillColor(142, 45, 226);
        pdf.roundedRect(35 + kpiWidth*3, yPos, kpiWidth, 25, 3, 3, 'F');
        pdf.setTextColor(255);
        pdf.setFontSize(10);
        pdf.text('AVG ORDER VALUE', 40 + kpiWidth*3, yPos + 8);
        pdf.setFontSize(12);
        pdf.text('Rs. <?php echo number_format($summary_stats["avg_order_value"], 0); ?>', 40 + kpiWidth*3, yPos + 18);
        
        yPos += 40;
        
        // ========== TOP PRODUCTS ==========
        pdf.setFontSize(14);
        pdf.setTextColor(44, 62, 80);
        pdf.text('Top Selling Products', 20, yPos);
        yPos += 10;
        
        // Table header
        pdf.setFillColor(240, 240, 240);
        pdf.rect(20, yPos, pageWidth - 40, 10, 'F');
        pdf.setTextColor(50);
        pdf.setFontSize(10);
        pdf.text('Product', 22, yPos + 7);
        pdf.text('Category', 80, yPos + 7);
        pdf.text('Qty', 120, yPos + 7);
        pdf.text('Revenue', 140, yPos + 7);
        pdf.text('Avg Price', 170, yPos + 7);
        
        yPos += 12;
        
        // Product rows
        <?php if(!empty($summary_stats['top_products'])): ?>
            <?php foreach($summary_stats['top_products'] as $index => $product): ?>
                pdf.setTextColor(30);
                pdf.text('<?php echo substr(htmlspecialchars($product['name']), 0, 30); ?>', 22, yPos);
                pdf.setTextColor(100);
                pdf.text('<?php echo htmlspecialchars($product['category']); ?>', 80, yPos);
                pdf.text('<?php echo number_format($product['total_quantity'], 0); ?>', 120, yPos);
                pdf.text('Rs. <?php echo number_format($product['total_revenue'], 0); ?>', 140, yPos);
                pdf.text('Rs. <?php echo number_format($product['total_revenue'] / max(1, $product['total_quantity']), 0); ?>', 170, yPos);
                yPos += 7;
            <?php endforeach; ?>
        <?php else: ?>
            pdf.text('No product data available', 22, yPos);
            yPos += 7;
        <?php endif; ?>
        
        yPos += 10;
        
        // ========== CATEGORY PERFORMANCE ==========
        pdf.addPage();
        yPos = 20;
        
        pdf.setFontSize(14);
        pdf.setTextColor(44, 62, 80);
        pdf.text('Category Performance', 20, yPos);
        yPos += 10;
        
        // Table header
        pdf.setFillColor(240, 240, 240);
        pdf.rect(20, yPos, pageWidth - 40, 10, 'F');
        pdf.setTextColor(50);
        pdf.setFontSize(10);
        pdf.text('Category', 22, yPos + 7);
        pdf.text('Orders', 80, yPos + 7);
        pdf.text('Quantity', 100, yPos + 7);
        pdf.text('Revenue', 130, yPos + 7);
        pdf.text('% of Total', 170, yPos + 7);
        
        yPos += 12;
        
        // Category rows
        <?php 
        $total_cat_revenue = array_sum(array_column($category_performance, 'revenue'));
        if(!empty($category_performance) && $total_cat_revenue > 0): ?>
            <?php foreach($category_performance as $category): 
                if($category['revenue'] == 0) continue;
                $percentage = $total_cat_revenue > 0 ? ($category['revenue'] / $total_cat_revenue * 100) : 0;
            ?>
                pdf.setTextColor(30);
                pdf.text('<?php echo substr(htmlspecialchars($category['category']), 0, 25); ?>', 22, yPos);
                pdf.setTextColor(100);
                pdf.text('<?php echo number_format($category['orders'], 0); ?>', 80, yPos);
                pdf.text('<?php echo number_format($category['quantity'], 0); ?>', 100, yPos);
                pdf.text('Rs. <?php echo number_format($category['revenue'], 0); ?>', 130, yPos);
                pdf.text('<?php echo number_format($percentage, 1); ?>%', 170, yPos);
                yPos += 7;
            <?php endforeach; ?>
        <?php else: ?>
            pdf.text('No category data available', 22, yPos);
            yPos += 7;
        <?php endif; ?>
        
        yPos += 10;
        
        // ========== CUSTOMER ANALYTICS ==========
        pdf.setFontSize(14);
        pdf.setTextColor(44, 62, 80);
        pdf.text('Customer Analytics', 20, yPos);
        yPos += 10;
        
        // Customer summary
        pdf.setFontSize(10);
        pdf.setTextColor(100);
        pdf.text('Total Customers:', 20, yPos);
        pdf.setTextColor(30);
        pdf.text('<?php echo number_format($customer_analytics['customers']['total_customers'], 0); ?>', 50, yPos);
        
        pdf.setTextColor(100);
        pdf.text('Repeat Customers:', 80, yPos);
        pdf.setTextColor(30);
        pdf.text('<?php echo number_format($customer_analytics['customers']['repeat_customers'], 0); ?> (<?php echo number_format($repeat_rate, 1); ?>%)', 110, yPos);
        
        yPos += 10;
        
        // Top customers table
        pdf.setFillColor(240, 240, 240);
        pdf.rect(20, yPos, pageWidth - 40, 10, 'F');
        pdf.setTextColor(50);
        pdf.text('Customer ID', 22, yPos + 7);
        pdf.text('Orders', 80, yPos + 7);
        pdf.text('Total Spent', 120, yPos + 7);
        
        yPos += 12;
        
        <?php if(!empty($customer_analytics['top_customers'])): ?>
            <?php foreach($customer_analytics['top_customers'] as $customer): ?>
                pdf.setTextColor(30);
                pdf.text('#<?php echo $customer['customer_id']; ?>', 22, yPos);
                pdf.setTextColor(100);
                pdf.text('<?php echo $customer['total_orders']; ?>', 80, yPos);
                pdf.text('Rs. <?php echo number_format($customer['total_spent'], 0); ?>', 120, yPos);
                yPos += 7;
            <?php endforeach; ?>
        <?php else: ?>
            pdf.text('No customer data available', 22, yPos);
            yPos += 7;
        <?php endif; ?>
        
        // ========== FOOTER ==========
        pdf.addPage();
        pdf.setFontSize(10);
        pdf.setTextColor(150);
        pdf.text('Report generated by SpiceCeylon Admin System', pageWidth/2, 20, { align: 'center' });
        pdf.text('© ' + new Date().getFullYear() + ' SpiceCeylon. All rights reserved.', pageWidth/2, 30, { align: 'center' });
        
        // Summary stats
        pdf.setFontSize(12);
        pdf.setTextColor(44, 62, 80);
        pdf.text('Report Summary', pageWidth/2, 50, { align: 'center' });
        
        pdf.setFontSize(10);
        pdf.setTextColor(100);
        pdf.text('Total Revenue Growth:', 50, 70);
        pdf.setTextColor(50);
        pdf.text('<?php echo $summary_stats['revenue_growth'] >= 0 ? "+" : ""; ?><?php echo number_format($summary_stats['revenue_growth'], 1); ?>%', 90, 70);
        
        pdf.setTextColor(100);
        pdf.text('Average Order Value:', 50, 80);
        pdf.setTextColor(50);
        pdf.text('Rs. <?php echo number_format($summary_stats['avg_order_value'], 2); ?>', 90, 80);
        
        pdf.setTextColor(100);
        pdf.text('Customer Repeat Rate:', 50, 90);
        pdf.setTextColor(50);
        pdf.text('<?php echo number_format($repeat_rate, 1); ?>%', 90, 90);
        
        // Save PDF
        const filename = `SpiceCeylon_Report_${new Date().toISOString().slice(0,10)}.pdf`;
        pdf.save(filename);
        
    } catch (error) {
        console.error('PDF Error:', error);
        alert('Error generating PDF. Please try again.');
    } finally {
        // Restore button
        pdfBtn.innerHTML = originalHTML;
        pdfBtn.disabled = false;
    }
}

// Chart functions
function loadDailyChart() {
    const buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(btn => {
        btn.classList.remove('active', 'btn-outline-primary');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('active', 'btn-outline-primary');
    
    revenueChart.data.labels = <?php echo json_encode(array_column($daily_sales, 'date')); ?>;
    revenueChart.data.datasets[0].data = <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>;
    revenueChart.data.datasets[0].label = 'Daily Revenue';
    revenueChart.update();
}

function loadMonthlyChart() {
    const buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(btn => {
        btn.classList.remove('active', 'btn-outline-primary');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('active', 'btn-outline-primary');
    
    revenueChart.data.labels = <?php echo json_encode(array_column($monthly_trends, 'month_name')); ?>;
    revenueChart.data.datasets[0].data = <?php echo json_encode(array_column($monthly_trends, 'revenue')); ?>;
    revenueChart.data.datasets[0].label = 'Monthly Revenue';
    revenueChart.update();
}

// Print function
function printPreview() {
    window.print();
}

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