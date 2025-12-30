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

// Get statistics for this farmer
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

// Get farmer's orders count
$total_orders = $conn->query("
    SELECT COUNT(DISTINCT oi.order_id) as order_count 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE p.farmer_id = $farmer_id
")->fetch_assoc()['order_count'];

// Get farmer's products stock info
$low_stock_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM products 
    WHERE farmer_id = $farmer_id AND stock < 10 AND stock > 0
")->fetch_assoc()['count'];

$out_of_stock_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM products 
    WHERE farmer_id = $farmer_id AND stock = 0
")->fetch_assoc()['count'];

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

// Get recent orders for this farmer
$recent_orders = $conn->query("
    SELECT DISTINCT o.*, u.name as customer_name 
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN users u ON o.customer_id = u.user_id 
    WHERE p.farmer_id = $farmer_id 
    ORDER BY o.order_id DESC 
    LIMIT 5
");

// Get recent customer requests
$recent_requests = $conn->query("
    SELECT pr.*, u.name as customer_name 
    FROM product_requests pr 
    JOIN users u ON pr.customer_id = u.user_id 
    WHERE pr.assigned_farmer_id = $farmer_id 
    ORDER BY pr.request_id DESC 
    LIMIT 5
");

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

// Get top selling products
$top_products = $conn->query("
    SELECT p.name, SUM(oi.quantity) as total_sold, p.stock
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE p.farmer_id = $farmer_id 
    GROUP BY p.product_id 
    ORDER BY total_sold DESC 
    LIMIT 5
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
    <title>Farmer Dashboard - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .stat-card.sales {
            background: linear-gradient(135deg, var(--farmer-green), #219653);
        }
        
        .stat-card.products {
            background: linear-gradient(135deg, var(--farmer-brown), #a0522d);
        }
        
        .stat-card.orders {
            background: linear-gradient(135deg, var(--farmer-blue), #2980b9);
        }
        
        .stat-card.requests {
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
        
        .farmer-profile-card {
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
            border: 3px solid var(--farmer-green);
            margin: 0 auto 20px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--farmer-green);
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
        
        .table-hover tbody tr:hover {
            background-color: rgba(39, 174, 96, 0.04);
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
        
        .stock-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .stock-good { background: #27ae60; }
        .stock-low { background: #f39c12; }
        .stock-out { background: #e74c3c; }
        
        .top-product-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid var(--farmer-green);
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
        
        .product-status-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .product-status-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .product-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-approved { background: var(--approved); }
        .status-pending { background: var(--pending); }
        .status-rejected { background: var(--rejected); }
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="my_sales.php">
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
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-tachometer-alt me-2" style="color: var(--farmer-green);"></i>
                                Farmer Dashboard
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Welcome back, <strong><?php echo htmlspecialchars($farmer['name']); ?></strong>! 
                                Manage your farm products and customer requests here.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--farmer-green), #219653); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(39, 174, 96, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo $today; ?>
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock me-1"></i> <?php echo $current_time; ?>
                        </div>
                    </div>
                </div>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card sales">
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
                        <div class="stat-card products">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                                    <div class="stat-label">Total Products</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-check-circle me-1"></i> 
                                        <?php echo $approved_products; ?> approved
                                        <br>
                                        <i class="fas fa-clock me-1"></i> 
                                        <?php echo $pending_products; ?> pending
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-leaf"></i>
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
                                        <i class="fas fa-box me-1"></i> 
                                        Products sold successfully
                                        <br>
                                        <i class="fas fa-users me-1"></i> 
                                        From <?php echo $total_orders; ?> customers
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card requests">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_requests); ?></div>
                                    <div class="stat-label">Customer Requests</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-clock me-1"></i> 
                                        <?php echo $pending_requests; ?> pending
                                        <br>
                                        <i class="fas fa-tractor me-1"></i> 
                                        Assigned by admin
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-inbox"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Product Status Summary -->
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
                                    Manage your farm products
                                </span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="add_product.php" class="quick-action-btn">
                                        <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                        <div class="fw-bold">Add Product</div>
                                        <small class="text-muted">List new spice</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="manage_products.php" class="quick-action-btn">
                                        <i class="fas fa-edit fa-2x mb-2"></i>
                                        <div class="fw-bold">Manage Products</div>
                                        <small class="text-muted"><?php echo $total_products; ?> products</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="customer_requests.php" class="quick-action-btn">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <div class="fw-bold">Customer Requests</div>
                                        <small class="text-muted"><?php echo $pending_requests; ?> pending</small>
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="my_sales.php" class="quick-action-btn">
                                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                                        <div class="fw-bold">Sales Report</div>
                                        <small class="text-muted">View analytics</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Status Summary -->
                    <div class="col-md-4">
                        <div class="analytics-card h-100">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-pie me-2" style="color: var(--farmer-brown);"></i>
                                Product Status Summary
                            </h5>
                            <div class="product-status-summary">
                                <div class="product-status-item">
                                    <div class="product-status-dot status-approved"></div>
                                    <div class="flex-grow-1">Approved Products</div>
                                    <div class="fw-bold"><?php echo $approved_products; ?></div>
                                </div>
                                <div class="product-status-item">
                                    <div class="product-status-dot status-pending"></div>
                                    <div class="flex-grow-1">Pending Approval</div>
                                    <div class="fw-bold"><?php echo $pending_products; ?></div>
                                </div>
                                <div class="product-status-item">
                                    <div class="product-status-dot status-rejected"></div>
                                    <div class="flex-grow-1">Rejected Products</div>
                                    <div class="fw-bold"><?php echo $rejected_products; ?></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="alert alert-warning p-2 mb-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Stock Alert:</strong> 
                                    <?php echo $low_stock_count; ?> products low, 
                                    <?php echo $out_of_stock_count; ?> out of stock
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Selling Products -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="analytics-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2" style="color: var(--farmer-gold);"></i>
                                    Top Selling Products
                                </h5>
                                <a href="my_sales.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-bar me-1"></i> View Details
                                </a>
                            </div>
                            
                            <?php if($top_products->num_rows > 0): 
                                $count = 1;
                                while($product = $top_products->fetch_assoc()): 
                                    $stock_class = $product['stock'] == 0 ? 'stock-out' : ($product['stock'] < 10 ? 'stock-low' : 'stock-good');
                            ?>
                            <div class="top-product-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2">#<?php echo $count++; ?></span>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?php echo $product['total_sold']; ?> sold</div>
                                        <small>
                                            <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                            Stock: <?php echo $product['stock']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-seedling"></i>
                                </div>
                                <h5 class="text-muted mb-3">No sales data</h5>
                                <p class="text-muted mb-4">
                                    Your products haven't been sold yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Customer Requests -->
                    <div class="col-md-6">
                        <div class="analytics-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-inbox me-2" style="color: var(--farmer-blue);"></i>
                                    Recent Customer Requests
                                </h5>
                                <a href="customer_requests.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View All
                                </a>
                            </div>
                            
                            <?php if($recent_requests->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while($request = $recent_requests->fetch_assoc()): ?>
                                <div class="list-group-item border-0 py-2 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['product_name']); ?></div>
                                            <small class="text-muted">
                                                From: <?php echo htmlspecialchars($request['customer_name']); ?>
                                            </small>
                                            <br>
                                            <small>
                                                <i class="fas fa-box me-1"></i>
                                                Qty: <?php echo $request['quantity_requested']; ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M d', strtotime($request['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php
                                            $status_class = 'badge-' . $request['status'];
                                            $status_icon = 'fa-clock';
                                            
                                            switch($request['status']) {
                                                case 'Completed': $status_icon = 'fa-check-circle'; break;
                                                case 'Approved': $status_icon = 'fa-check-double'; break;
                                                case 'Rejected': $status_icon = 'fa-times-circle'; break;
                                                case 'Reviewed': $status_icon = 'fa-eye'; break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?> me-1"></i> 
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h5 class="text-muted mb-3">No requests</h5>
                                <p class="text-muted mb-4">
                                    No customer requests assigned to you yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Farmer Profile -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2" style="color: var(--farmer-green);"></i>
                                    Recent Sales Orders
                                </h5>
                                <a href="my_orders.php" class="btn btn-outline-primary btn-sm">
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
                                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" 
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
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h5 class="text-muted mb-3">No orders found</h5>
                                <p class="text-muted mb-4">
                                    There are no orders for your products yet.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Farmer Profile -->
                    <div class="col-md-4">
                        <div class="farmer-profile-card">
                            <div class="text-center mb-4">
                                <div class="profile-avatar">
                                    <?php if(!empty($farmer['profile_image'])): ?>
                                        <img src="../assets/images/profile_images/<?php echo $farmer['profile_image']; ?>" 
                                             alt="Farmer" class="w-100 h-100 rounded-circle">
                                    <?php else: ?>
                                        <i class="fas fa-tractor"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($farmer['name']); ?></h5>
                                <p class="text-muted mb-3">Verified Farmer</p>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="fw-bold text-primary"><?php echo $total_products; ?></div>
                                        <small class="text-muted">Products Listed</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-success"><?php echo $total_orders; ?></div>
                                        <small class="text-muted">Orders Fulfilled</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="fas fa-info-circle me-2"></i> Farm Information</h6>
                                <div class="small">
                                    <p class="mb-1">
                                        <i class="fas fa-envelope me-2"></i> 
                                        <?php echo htmlspecialchars($farmer['email']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-phone me-2"></i> 
                                        <?php echo htmlspecialchars($farmer['phone']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i> 
                                        <?php echo htmlspecialchars($farmer['farm_location'] ?: $farmer['address']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-edit me-2"></i> Edit Profile
                                </a>
                                <a href="add_product.php" class="btn btn-outline-success">
                                    <i class="fas fa-plus me-2"></i> Add New Product
                                </a>
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
                                <i class="fas fa-seedling me-1"></i> 
                                SpiceCeylon Farmer Panel v1.0 • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-tractor me-1"></i> 
                                Farm Status: <span class="text-success">Active</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-box me-1"></i> 
                                Products: <?php echo $total_products; ?> listed, <?php echo $approved_products; ?> active
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
            
            // Update stock indicators
            $('.stock-indicator').each(function() {
                const stock = parseInt($(this).text());
                if (stock === 0) {
                    $(this).addClass('stock-out');
                } else if (stock < 10) {
                    $(this).addClass('stock-low');
                } else {
                    $(this).addClass('stock-good');
                }
            });
        });
    </script>
</body>
</html>