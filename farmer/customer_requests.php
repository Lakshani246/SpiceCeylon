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

// Get farmer statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE farmer_id = $farmer_id")->fetch_assoc()['count'];
$total_requests = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE assigned_farmer_id = $farmer_id")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE assigned_farmer_id = $farmer_id AND status = 'Pending' OR status = 'Approved'")->fetch_assoc()['count'];
$completed_requests = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE assigned_farmer_id = $farmer_id AND status = 'Completed'")->fetch_assoc()['count'];

// Handle status update from farmer
$update_success = false;
$update_error = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'];
    $farmer_notes = trim($_POST['farmer_notes']);
    
    // Verify the request belongs to this farmer
    $verify_query = $conn->prepare("SELECT * FROM product_requests WHERE request_id = ? AND assigned_farmer_id = ?");
    $verify_query->bind_param("ii", $request_id, $farmer_id);
    $verify_query->execute();
    $request = $verify_query->get_result()->fetch_assoc();
    
    if ($request) {
        // Update request status with farmer notes
        $update_query = $conn->prepare("
            UPDATE product_requests SET 
            status = ?, 
            admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Farmer Update: ', NOW(), ' - ', ?, ']'),
            updated_at = NOW()
            WHERE request_id = ?
        ");
        $update_query->bind_param("ssi", $new_status, $farmer_notes, $request_id);
        
        if ($update_query->execute()) {
            $update_success = true;
            
            // Log the update if request_history table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'request_history'");
            if ($table_check->num_rows > 0) {
                $history_query = $conn->prepare("
                    INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes)
                    VALUES (?, NULL, ?, ?, ?)
                ");
                $notes = "Updated by farmer: " . ($farmer_notes ?: 'No notes provided');
                $history_query->bind_param("isss", $request_id, $request['status'], $new_status, $notes);
                $history_query->execute();
            }
            
        } else {
            $update_error = true;
            $error_message = "Error updating request status. Please try again.";
        }
    } else {
        $update_error = true;
        $error_message = "Request not found or you don't have permission to update it.";
    }
}

// Handle adding product from request
$product_added = false;
$product_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $request_id = intval($_POST['request_id']);
    $product_name = trim($_POST['product_name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // Verify the request belongs to this farmer
    $verify_query = $conn->prepare("SELECT * FROM product_requests WHERE request_id = ? AND assigned_farmer_id = ?");
    $verify_query->bind_param("ii", $request_id, $farmer_id);
    $verify_query->execute();
    $request = $verify_query->get_result()->fetch_assoc();
    
    if ($request) {
        // Insert new product
        $product_query = $conn->prepare("
            INSERT INTO products (farmer_id, name, description, category, price, stock, image, status, admin_approved)
            VALUES (?, ?, ?, ?, ?, ?, 'default.jpg', 'Pending', 'pending')
        ");
        $product_query->bind_param("isssdii", $farmer_id, $product_name, $description, $category, $price, $stock);
        
        if ($product_query->execute()) {
            $new_product_id = $product_query->insert_id;
            $product_added = true;
            
            // Update request status to Reviewed
            $update_query = $conn->prepare("
                UPDATE product_requests SET 
                status = 'Reviewed',
                admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Farmer added product to catalog. Product ID: ', ?, ']'),
                updated_at = NOW()
                WHERE request_id = ?
            ");
            $update_query->bind_param("ii", $new_product_id, $request_id);
            $update_query->execute();
            
            // Log in history if table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'request_history'");
            if ($table_check->num_rows > 0) {
                $history_query = $conn->prepare("
                    INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes)
                    VALUES (?, NULL, ?, 'Reviewed', 'Farmer added product to catalog')
                ");
                $history_query->bind_param("is", $request_id, $request['status']);
                $history_query->execute();
            }
            
        } else {
            $product_error = true;
            $error_message = "Error adding product. Please try again.";
        }
    } else {
        $product_error = true;
        $error_message = "Request not found or you don't have permission to add product.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$urgency_filter = isset($_GET['urgency']) ? $_GET['urgency'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$query_params = [$farmer_id];
$query_conditions = "WHERE assigned_farmer_id = ?";
$query_types = "i";

if ($status_filter != 'all') {
    $query_conditions .= " AND status = ?";
    $query_params[] = $status_filter;
    $query_types .= "s";
}

if ($urgency_filter != 'all') {
    $query_conditions .= " AND urgency = ?";
    $query_params[] = $urgency_filter;
    $query_types .= "s";
}

if (!empty($search_query)) {
    $query_conditions .= " AND (product_name LIKE ? OR description LIKE ?)";
    $search_param = "%$search_query%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_types .= "ss";
}

// Get all requests with filters
$requests_query = $conn->prepare("
    SELECT 
        pr.*,
        u.name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        (SELECT COUNT(*) FROM products p WHERE p.farmer_id = pr.assigned_farmer_id AND p.name LIKE CONCAT('%', pr.product_name, '%')) as similar_products
    FROM product_requests pr
    JOIN users u ON pr.customer_id = u.user_id
    $query_conditions
    ORDER BY 
        CASE WHEN pr.status = 'Pending' THEN 1
             WHEN pr.status = 'Approved' THEN 2
             WHEN pr.status = 'Reviewed' THEN 3
             WHEN pr.status = 'Rejected' THEN 4
             WHEN pr.status = 'Completed' THEN 5
             ELSE 6 END,
        CASE WHEN pr.urgency = 'High' THEN 1
             WHEN pr.urgency = 'Medium' THEN 2
             ELSE 3 END,
        pr.created_at DESC
");

// Bind parameters dynamically
$bind_params = array_merge([$query_types], $query_params);
$refs = [];
foreach ($bind_params as $key => $value) {
    $refs[$key] = &$bind_params[$key];
}
call_user_func_array([$requests_query, 'bind_param'], $refs);

$requests_query->execute();
$requests = $requests_query->get_result();

// Get categories for dropdown (from your products table structure)
$categories_query = $conn->query("
    SELECT DISTINCT category FROM products WHERE farmer_id = $farmer_id 
    UNION 
    SELECT 'Whole Spices' 
    UNION 
    SELECT 'Spices' 
    UNION 
    SELECT 'Leaves & Herbs' 
    UNION 
    SELECT 'Roots & Bulbs' 
    UNION 
    SELECT 'Fruits & Pods' 
    UNION 
    SELECT 'Chilies & Peppers' 
    UNION 
    SELECT 'Powders & Pastes' 
    UNION 
    SELECT 'Specialty Spices'
    ORDER BY category
");

// Get farmer's products for reference
$farmer_products = $conn->query("
    SELECT name, category, price, stock 
    FROM products 
    WHERE farmer_id = $farmer_id 
    ORDER BY name
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Requests - Farmer Dashboard</title>
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
            --reviewed: #3498db;
            --approved: #27ae60;
            --rejected: #e74c3c;
            --completed: #2ecc71;
            --high-urgency: #e74c3c;
            --medium-urgency: #f39c12;
            --low-urgency: #2ecc71;
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
        
        .requests-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .requests-card:hover {
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
        
        .form-label {
            font-weight: 600;
            color: var(--farmer-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--farmer-green);
            box-shadow: 0 0 0 0.25rem rgba(39, 174, 96, 0.25);
        }
        
        .btn-primary {
            background: var(--farmer-green);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-outline-primary {
            border-color: var(--farmer-green);
            color: var(--farmer-green);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--farmer-green);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .request-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--pending);
            transition: all 0.3s ease;
        }
        
        .request-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .request-item.Completed {
            border-left-color: var(--completed);
            background: rgba(46, 204, 113, 0.05);
        }
        
        .request-item.Approved {
            border-left-color: var(--approved);
            background: rgba(39, 174, 96, 0.05);
        }
        
        .request-item.Rejected {
            border-left-color: var(--rejected);
            background: rgba(231, 76, 60, 0.05);
        }
        
        .request-item.Reviewed {
            border-left-color: var(--reviewed);
            background: rgba(52, 152, 219, 0.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-Pending {
            background: rgba(243, 156, 18, 0.15);
            color: var(--pending);
        }
        
        .badge-Reviewed {
            background: rgba(52, 152, 219, 0.15);
            color: var(--reviewed);
        }
        
        .badge-Approved {
            background: rgba(39, 174, 96, 0.15);
            color: var(--approved);
        }
        
        .badge-Rejected {
            background: rgba(231, 76, 60, 0.15);
            color: var(--rejected);
        }
        
        .badge-Completed {
            background: rgba(46, 204, 113, 0.15);
            color: var(--completed);
        }
        
        .urgency-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-High {
            background: rgba(231, 76, 60, 0.15);
            color: var(--high-urgency);
        }
        
        .badge-Medium {
            background: rgba(243, 156, 18, 0.15);
            color: var(--medium-urgency);
        }
        
        .badge-Low {
            background: rgba(46, 204, 113, 0.15);
            color: var(--low-urgency);
        }
        
        .customer-info {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--farmer-green), #219653);
            color: white;
            border-radius: 12px 12px 0 0;
            border-bottom: none;
            padding: 20px 30px;
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
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--farmer-green);
            border-left: 4px solid var(--farmer-green);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        .notes-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid var(--farmer-blue);
        }
        
        .similar-products {
            background: rgba(155, 89, 182, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #9b59b6;
        }
        
        .tab-content {
            padding: 25px 0;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 8px 8px 0 0;
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--farmer-green);
            background-color: white;
            border-bottom: 3px solid var(--farmer-green);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--farmer-green);
            border-color: transparent;
        }
        
        .request-details {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--farmer-green);
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        /* Admin assignment badge */
        .admin-assignment {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            margin-bottom: 10px;
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
                        <a class="nav-link active" href="customer_requests.php">
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
                                <i class="fas fa-inbox me-2" style="color: var(--farmer-green);"></i>
                                Customer Requests
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Manage customer requests assigned to you by admin. Add requested products to your catalog or update request status.
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--farmer-green), #219653); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(39, 174, 96, 0.3);">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if($update_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Request status updated successfully! Admin will be notified.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($update_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($product_added): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Product added to catalog successfully! It will be reviewed by admin.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($product_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #219653);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_requests); ?></div>
                                    <div class="stat-label">Total Requests</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-inbox me-1"></i> 
                                        Assigned by admin
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($pending_requests); ?></div>
                                    <div class="stat-label">Pending/Approved</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-clock me-1"></i> 
                                        Need your attention
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($completed_requests); ?></div>
                                    <div class="stat-label">Completed</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-check-circle me-1"></i> 
                                        Successfully fulfilled
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                                    <div class="stat-label">Your Products</div>
                                    <div class="small opacity-75 mt-2">
                                        <i class="fas fa-leaf me-1"></i> 
                                        In catalog
                                    </div>
                                </div>
                                <div class="display-6 opacity-50">
                                    <i class="fas fa-seedling"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-card">
                    <h5 class="mb-3">
                        <i class="fas fa-filter me-2" style="color: var(--farmer-blue);"></i>
                        Filter Requests
                    </h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Reviewed" <?php echo $status_filter == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="urgency" class="form-label">Urgency</label>
                            <select class="form-select" id="urgency" name="urgency">
                                <option value="all" <?php echo $urgency_filter == 'all' ? 'selected' : ''; ?>>All Urgency</option>
                                <option value="High" <?php echo $urgency_filter == 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Medium" <?php echo $urgency_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="Low" <?php echo $urgency_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by product name or description" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Requests Content -->
                <div class="row">
                    <div class="col-12">
                        <div class="requests-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2" style="color: var(--farmer-green);"></i>
                                    Customer Requests Assigned to You
                                </h5>
                                <span class="badge bg-info">
                                    <?php echo $requests->num_rows; ?> requests found
                                </span>
                            </div>
                            
                            <?php if($requests->num_rows > 0): 
                                $requests->data_seek(0);
                                while($request = $requests->fetch_assoc()): 
                                    $status_class = strtolower($request['status']);
                            ?>
                            <div class="request-item <?php echo $request['status']; ?>">
                                <!-- Admin Assignment Badge -->
                                <div class="admin-assignment">
                                    <i class="fas fa-user-tie me-1"></i>
                                    Assigned by Admin
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="mb-1">
                                                    <?php echo htmlspecialchars($request['product_name']); ?>
                                                    <span class="urgency-badge badge-<?php echo $request['urgency']; ?> ms-2">
                                                        <?php echo $request['urgency']; ?> Priority
                                                    </span>
                                                </h5>
                                                <p class="text-muted mb-2">
                                                    <i class="fas fa-tag me-1"></i>
                                                    Category: <?php echo htmlspecialchars($request['category']); ?>
                                                </p>
                                                <p class="mb-0">
                                                    <?php echo nl2br(htmlspecialchars($request['description'])); ?>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <span class="status-badge badge-<?php echo $request['status']; ?>">
                                                    <i class="fas 
                                                        <?php 
                                                        switch($request['status']) {
                                                            case 'Pending': echo 'fa-clock'; break;
                                                            case 'Approved': echo 'fa-user-tie'; break;
                                                            case 'Reviewed': echo 'fa-eye'; break;
                                                            case 'Rejected': echo 'fa-times-circle'; break;
                                                            case 'Completed': echo 'fa-check-circle'; break;
                                                            default: echo 'fa-circle';
                                                        }
                                                        ?> me-1">
                                                    </i> 
                                                    <?php echo $request['status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="customer-info">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-user me-2"></i> Customer Details
                                                    </h6>
                                                    <p class="mb-1">
                                                        <strong><?php echo htmlspecialchars($request['customer_name']); ?></strong>
                                                    </p>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-envelope me-1"></i>
                                                        <?php echo htmlspecialchars($request['customer_email']); ?>
                                                    </p>
                                                    <p class="mb-0 small">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo htmlspecialchars($request['customer_phone']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="customer-info">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-info-circle me-2"></i> Request Details
                                                    </h6>
                                                    <p class="mb-1">
                                                        <i class="fas fa-box me-1"></i>
                                                        Quantity: <?php echo $request['quantity_requested']; ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Requested: <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                    </p>
                                                    <?php if($request['updated_at'] != $request['created_at']): ?>
                                                    <p class="mb-0">
                                                        <i class="fas fa-history me-1"></i>
                                                        Updated: <?php echo date('M d, Y', strtotime($request['updated_at'])); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if($request['similar_products'] > 0): ?>
                                        <div class="similar-products">
                                            <h6 class="mb-2">
                                                <i class="fas fa-lightbulb me-2"></i> Product Suggestion
                                            </h6>
                                            <p class="mb-0 small">
                                                You already have <?php echo $request['similar_products']; ?> similar product(s) in your catalog.
                                                <a href="manage_products.php?search=<?php echo urlencode($request['product_name']); ?>" class="text-decoration-none">
                                                    View similar products <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($request['admin_notes'])): ?>
                                        <div class="notes-box">
                                            <h6 class="mb-2">
                                                <i class="fas fa-sticky-note me-2"></i> Admin Notes
                                            </h6>
                                            <p class="mb-0 small">
                                                <?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="request-details">
                                            <h6 class="mb-3">
                                                <i class="fas fa-cogs me-2"></i> Farmer Actions
                                            </h6>
                                            
                                            <?php if($request['status'] == 'Pending' || $request['status'] == 'Approved'): ?>
                                            <div class="action-buttons mb-3">
                                                <button type="button" class="btn btn-outline-success btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#addProductModal<?php echo $request['request_id']; ?>">
                                                    <i class="fas fa-plus-circle me-1"></i> Add Product
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $request['request_id']; ?>">
                                                    <i class="fas fa-edit me-1"></i> Update Status
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="timeline">
                                                <div class="timeline-item">
                                                    <div class="timeline-date">
                                                        <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?>
                                                    </div>
                                                    <div class="small">
                                                        Request submitted by customer
                                                    </div>
                                                </div>
                                                
                                                <?php if($request['status'] != 'Pending'): ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-date">
                                                        <?php echo date('M d, Y', strtotime($request['updated_at'])); ?>
                                                    </div>
                                                    <div class="small">
                                                        Status: <span class="fw-bold"><?php echo $request['status']; ?></span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <!-- Quick add to products link -->
                                                <a href="add_product.php?request_id=<?php echo $request['request_id']; ?>&product_name=<?php echo urlencode($request['product_name']); ?>&category=<?php echo urlencode($request['category']); ?>&description=<?php echo urlencode($request['description']); ?>" 
                                                   class="btn btn-outline-primary w-100 mb-2">
                                                    <i class="fas fa-leaf me-2"></i> Quick Add Product
                                                </a>
                                                
                                                <?php if($request['status'] == 'Pending' || $request['status'] == 'Approved'): ?>
                                                <div class="alert alert-warning p-2 mb-0">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <small>This request needs your action</small>
                                                </div>
                                                <?php elseif($request['status'] == 'Completed'): ?>
                                                <div class="alert alert-success p-2 mb-0">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <small>Request completed successfully</small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add Product Modal -->
                            <div class="modal fade" id="addProductModal<?php echo $request['request_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-plus-circle me-2"></i>
                                                Add Product from Request
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <input type="hidden" name="add_product" value="1">
                                                
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Adding this product will make it available in your catalog after admin approval.
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="product_name_<?php echo $request['request_id']; ?>" class="form-label">Product Name *</label>
                                                            <input type="text" class="form-control" id="product_name_<?php echo $request['request_id']; ?>" 
                                                                   name="product_name" value="<?php echo htmlspecialchars($request['product_name']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="category_<?php echo $request['request_id']; ?>" class="form-label">Category *</label>
                                                            <select class="form-select" id="category_<?php echo $request['request_id']; ?>" name="category" required>
                                                                <option value="">Select Category</option>
                                                                <?php 
                                                                $categories_query->data_seek(0);
                                                                while($category = $categories_query->fetch_assoc()): 
                                                                    $selected = $request['category'] == $category['category'] ? 'selected' : '';
                                                                ?>
                                                                <option value="<?php echo htmlspecialchars($category['category']); ?>" <?php echo $selected; ?>>
                                                                    <?php echo htmlspecialchars($category['category']); ?>
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="description_<?php echo $request['request_id']; ?>" class="form-label">Description *</label>
                                                    <textarea class="form-control" id="description_<?php echo $request['request_id']; ?>" 
                                                              name="description" rows="3" required><?php echo htmlspecialchars($request['description']); ?></textarea>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="price_<?php echo $request['request_id']; ?>" class="form-label">Price (Rs.) *</label>
                                                            <input type="number" class="form-control" id="price_<?php echo $request['request_id']; ?>" 
                                                                   name="price" min="1" step="0.01" required placeholder="Enter price per unit">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="stock_<?php echo $request['request_id']; ?>" class="form-label">Initial Stock *</label>
                                                            <input type="number" class="form-control" id="stock_<?php echo $request['request_id']; ?>" 
                                                                   name="stock" min="1" value="<?php echo $request['quantity_requested']; ?>" required>
                                                            <small class="text-muted">Based on requested quantity: <?php echo $request['quantity_requested']; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-plus-circle me-2"></i> Add Product
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateStatusModal<?php echo $request['request_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-edit me-2"></i>
                                                Update Request Status
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                
                                                <div class="mb-3">
                                                    <label for="status_<?php echo $request['request_id']; ?>" class="form-label">Status *</label>
                                                    <select class="form-select" id="status_<?php echo $request['request_id']; ?>" name="status" required>
                                                        <?php if($request['status'] == 'Pending'): ?>
                                                        <option value="Reviewed" <?php echo $request['status'] == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                        <option value="Completed" <?php echo $request['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <?php elseif($request['status'] == 'Approved'): ?>
                                                        <option value="Reviewed" <?php echo $request['status'] == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                        <option value="Completed" <?php echo $request['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <?php endif; ?>
                                                        <option value="Rejected" <?php echo $request['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                    <small class="text-muted">Admin will be notified of your update</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="farmer_notes_<?php echo $request['request_id']; ?>" class="form-label">Your Notes</label>
                                                    <textarea class="form-control" id="farmer_notes_<?php echo $request['request_id']; ?>" 
                                                              name="farmer_notes" rows="3" 
                                                              placeholder="Add any notes or comments about this request..."><?php 
                                                        if($request['status'] == 'Rejected') echo "Unable to fulfill request. ";
                                                        elseif($request['status'] == 'Completed') echo "Request fulfilled successfully. ";
                                                    ?></textarea>
                                                    <small class="text-muted">These notes will be visible to admin.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i> Update Status
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php endwhile; ?>
                            
                            <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h5 class="text-muted mb-3">No requests found</h5>
                                <p class="text-muted mb-4">
                                    <?php if($status_filter != 'all' || $urgency_filter != 'all' || !empty($search_query)): ?>
                                    No requests match your current filters. Try changing your filter criteria.
                                    <?php else: ?>
                                    No customer requests have been assigned to you yet.
                                    <?php endif; ?>
                                </p>
                                <?php if($status_filter != 'all' || $urgency_filter != 'all' || !empty($search_query)): ?>
                                <a href="customer_requests.php" class="btn btn-primary">
                                    <i class="fas fa-redo me-2"></i> Clear Filters
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-lightbulb fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-tips me-2"></i> Tips for Handling Requests
                                    </h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Respond quickly</strong> to high-priority requests assigned by admin</li>
                                        <li><strong>Add requested products</strong> to your catalog to increase your product range</li>
                                        <li><strong>Update status regularly</strong> to keep admin informed about progress</li>
                                        <li><strong>Check for similar products</strong> before adding new ones to avoid duplicates</li>
                                        <li><strong>Add detailed notes</strong> when updating status for better communication with admin</li>
                                        <li><strong>Mark as 'Completed'</strong> when you have fulfilled the customer's request</li>
                                    </ul>
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
                                <i class="fas fa-inbox me-1"></i> 
                                Customer Requests Management • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-clock me-1"></i> 
                                Pending/Approved: <?php echo $pending_requests; ?> requests • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-check-circle me-1"></i> 
                                Completed: <?php echo $completed_requests; ?> requests
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
        // Initialize tooltips
        $(document).ready(function() {
            // Add hover effect to cards
            $('.requests-card, .stat-card, .request-item').hover(
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
            
            // Form validation for add product modal
            $('form').on('submit', function(e) {
                if ($(this).find('input[name="add_product"]').length > 0) {
                    const price = $(this).find('input[name="price"]').val();
                    const stock = $(this).find('input[name="stock"]').val();
                    
                    if (price <= 0) {
                        e.preventDefault();
                        alert('Price must be greater than 0!');
                        return;
                    }
                    
                    if (stock < 1) {
                        e.preventDefault();
                        alert('Stock must be at least 1!');
                        return;
                    }
                }
                
                if ($(this).find('input[name="update_status"]').length > 0) {
                    const farmerNotes = $(this).find('textarea[name="farmer_notes"]').val().trim();
                    const status = $(this).find('select[name="status"]').val();
                    
                    if (status === 'Rejected' && farmerNotes.length < 10) {
                        e.preventDefault();
                        alert('Please provide a reason (at least 10 characters) for rejecting this request.');
                        return;
                    }
                }
            });
            
            // Auto-fill price based on similar products
            $('input[name="price"]').on('focus', function() {
                if (!$(this).val()) {
                    // Suggest price based on similar products
                    $(this).val('500.00');
                }
            });
        });
    </script>
</body>
</html>