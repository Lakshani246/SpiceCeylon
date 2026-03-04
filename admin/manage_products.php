<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
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

// ========== FIXED: Handle AJAX actions for approve/reject ==========
if (isset($_POST['ajax_action']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $action = $_POST['ajax_action'];
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    if ($action == 'approve') {
        $conn->query("UPDATE products SET admin_approved = 'approved', approved_by = '$admin_id', approved_at = NOW() WHERE product_id = '$product_id'");
        $response['success'] = true;
        $response['message'] = "Product approved successfully!";
    }
    elseif ($action == 'reject') {
        $reason = $conn->real_escape_string($_POST['rejection_reason']);
        $conn->query("UPDATE products SET admin_approved = 'rejected', approved_by = '$admin_id', approved_at = NOW(), rejection_reason = '$reason' WHERE product_id = '$product_id'");
        $response['success'] = true;
        $response['message'] = "Product rejected!";
    }
    
    echo json_encode($response);
    exit;
}

// ========== Handle AJAX request for product data ==========
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_product' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    header('Content-Type: application/json');
    
    $response = ['success' => false];
    
    // Get product details
    $product_query = "SELECT p.*, u.name as farmer_name, u.email as farmer_email, u.phone as farmer_phone, u.farm_location 
                     FROM products p 
                     JOIN users u ON p.farmer_id = u.user_id 
                     WHERE p.product_id = '$product_id'";
    $product_result = $conn->query($product_query);
    
    if ($product_result && $product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        
        // Check image path
        $image_path = '';
        if ($product['image']) {
            if (file_exists('../assets/images/' . $product['image'])) {
                $image_path = '../assets/images/' . $product['image'];
            } elseif (file_exists('../uploads/products/' . $product['image'])) {
                $image_path = '../uploads/products/' . $product['image'];
            } else {
                $image_path = '../assets/images/default-spice.jpg';
            }
        } else {
            $image_path = '../assets/images/default-spice.jpg';
        }
        $product['image_url'] = $image_path;
        
        $response['success'] = true;
        $response['product'] = $product;
    }
    
    echo json_encode($response);
    exit;
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Build query
$where_conditions = ["p.admin_approved = '" . $conn->real_escape_string($status_filter) . "'"];
$count_conditions = ["p.admin_approved = '" . $conn->real_escape_string($status_filter) . "'"];

if ($category_filter) {
    $where_conditions[] = "p.category = '" . $conn->real_escape_string($category_filter) . "'";
    $count_conditions[] = "p.category = '" . $conn->real_escape_string($category_filter) . "'";
}

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions[] = "(p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%' OR u.name LIKE '%$search_escaped%' OR p.category LIKE '%$search_escaped%')";
    $count_conditions[] = "(p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%' OR p.farmer_id IN (SELECT user_id FROM users WHERE name LIKE '%$search_escaped%') OR p.category LIKE '%$search_escaped%')";
}

$where_clause = implode(' AND ', $where_conditions);
$count_where_clause = implode(' AND ', $count_conditions);

$query = "SELECT p.*, u.name as farmer_name, u.email as farmer_email 
          FROM products p 
          JOIN users u ON p.farmer_id = u.user_id 
          WHERE $where_clause
          ORDER BY p.created_at DESC";

$count_query = "SELECT COUNT(*) as total FROM products p WHERE $count_where_clause";

// Pagination
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_result = $conn->query($count_query);
$total_products = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_products / $limit);

$query .= " LIMIT $limit OFFSET $offset";

$products_result = $conn->query($query);

// Get counts
$pending_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE admin_approved = 'pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE admin_approved = 'approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE admin_approved = 'rejected'")->fetch_assoc()['count'];
$total_all = $pending_count + $approved_count + $rejected_count;

// Get category counts
$category_counts = [];
$categories = $conn->query("SELECT category, COUNT(*) as count FROM products GROUP BY category ORDER BY category");
while($cat = $categories->fetch_assoc()) {
    $category_counts[$cat['category']] = $cat['count'];
}

// Check for messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - SpiceCeylon Admin</title>
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
            --approved: #27ae60;
            --rejected: #e74c3c;
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
        
        .product-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .product-card.pending { border-left: 4px solid var(--pending); }
        .product-card.approved { border-left: 4px solid var(--approved); }
        .product-card.rejected { border-left: 4px solid var(--rejected); }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-approved { background: rgba(39, 174, 96, 0.15); color: var(--approved); }
        .badge-rejected { background: rgba(231, 76, 60, 0.15); color: var(--rejected); }
        
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            color: white;
            height: 100%;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pending {
            background: linear-gradient(135deg, var(--pending), #e67e22);
        }
        
        .stat-card.approved {
            background: linear-gradient(135deg, var(--approved), #219653);
        }
        
        .stat-card.rejected {
            background: linear-gradient(135deg, var(--rejected), #c0392b);
        }
        
        .stat-card.active {
            box-shadow: 0 0 0 3px white, 0 0 0 6px var(--spice-red);
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
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .product-image-container {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            margin-right: 15px;
        }
        
        .product-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .action-buttons .btn {
            padding: 5px 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .category-badge {
            background: rgba(52, 152, 219, 0.1);
            color: var(--spice-blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .pagination .page-link {
            color: var(--spice-dark);
            border: 1px solid #e9ecef;
            margin: 0 2px;
            border-radius: 8px;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--spice-blue), var(--spice-purple));
            border-color: transparent;
            color: white;
        }
        
        .metric-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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
        
        .price-tag {
            background: linear-gradient(135deg, var(--spice-green), #219653);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ========== MODAL STYLES ========== */
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: none;
            color: white;
        }
        
        .modal-header.approve-header {
            background: linear-gradient(135deg, var(--approved), #219653);
        }
        
        .modal-header.reject-header {
            background: linear-gradient(135deg, var(--rejected), #c0392b);
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
            transition: all 0.3s;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        
        @media (min-width: 576px) {
            .modal-dialog-centered {
                min-height: calc(100% - 1.75rem);
            }
        }
        
        /* Product Detail Card in Modal */
        .product-detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 4px solid var(--spice-green);
        }
        
        .product-detail-card h6 {
            color: var(--spice-dark);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 12px;
        }
        
        .detail-label {
            width: 120px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .detail-value {
            flex: 1;
            color: var(--spice-dark);
            font-weight: 500;
        }
        
        .modal-product-image {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8f9fa;
            padding: 10px;
        }
        
        /* ========== CENTERED CONFIRMATION MODALS ========== */
        .confirm-modal .modal-content {
            border-radius: 16px;
        }
        
        .confirm-modal .modal-body {
            text-align: center;
            padding: 30px;
        }
        
        .confirm-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .confirm-icon.approve {
            color: var(--approved);
        }
        
        .confirm-icon.reject {
            color: var(--rejected);
        }
        
        /* ========== LOADING SPINNER ========== */
        .modal-spinner {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--spice-green);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* ========== CUSTOM ALERT ========== */
        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 350px;
            max-width: 450px;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 6px solid;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
                visibility: hidden;
            }
        }
        
        .alert-custom.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-custom.warning {
            background: linear-gradient(135deg, #fff3cd, #ffe8a1);
            border-left-color: #ffc107;
            color: #856404;
        }
        
        .alert-custom.danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .alert-custom.info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        
        .alert-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .alert-custom.success .alert-icon {
            background: #28a745;
            color: white;
        }
        
        .alert-custom.warning .alert-icon {
            background: #ffc107;
            color: white;
        }
        
        .alert-custom.danger .alert-icon {
            background: #dc3545;
            color: white;
        }
        
        .alert-custom.info .alert-icon {
            background: #17a2b8;
            color: white;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .alert-message {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .alert-close {
            color: inherit;
            opacity: 0.5;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .alert-close:hover {
            opacity: 1;
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
                                <i class="fas fa-leaf me-2" style="color: var(--spice-green);"></i>
                                Product Management
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Review and approve products from farmers
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-red), #d35400); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(184, 92, 56, 0.3);">
                            <i class="fas fa-box me-1"></i> Total Products: <?php echo number_format($total_all); ?>
                        </div>
                    </div>
                </div>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <a href="manage_products.php?status=pending" class="text-decoration-none">
                            <div class="stat-card pending <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($pending_count); ?></div>
                                        <div class="stat-label">Pending Review</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-clock me-1"></i> Awaiting approval
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="manage_products.php?status=approved" class="text-decoration-none">
                            <div class="stat-card approved <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($approved_count); ?></div>
                                        <div class="stat-label">Approved Products</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-check-circle me-1"></i> Live in marketplace
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="manage_products.php?status=rejected" class="text-decoration-none">
                            <div class="stat-card rejected <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($rejected_count); ?></div>
                                        <div class="stat-label">Rejected Products</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-times-circle me-1"></i> Requires changes
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="fas fa-filter me-2" style="color: var(--spice-purple);"></i>
                                Filter Products
                            </h5>
                            <form method="GET" action="manage_products.php" class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Product name, description, farmer..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Category</label>
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php 
                                        if ($categories_result) {
                                            $categories_result->data_seek(0);
                                            while($cat = $categories_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                                <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category']); ?> 
                                                (<?php echo $category_counts[$cat['category']] ?? 0; ?>)
                                            </option>
                                        <?php endwhile; } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-12 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="fas fa-filter me-1"></i> Apply Filters
                                        </button>
                                        <a href="manage_products.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-4 h-100">
                                <h6 class="mb-3">
                                    <i class="fas fa-chart-pie me-2" style="color: var(--spice-blue);"></i>
                                    Quick Stats
                                </h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Pending:</span>
                                        <strong class="text-warning"><?php echo $pending_count; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Approved:</span>
                                        <strong class="text-success"><?php echo $approved_count; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Rejected:</span>
                                        <strong class="text-danger"><?php echo $rejected_count; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Total:</span>
                                        <strong class="text-primary"><?php echo $total_all; ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--spice-red);"></i>
                            Product List (<?php echo $total_products; ?> products)
                        </h5>
                        <?php if($category_filter): ?>
                            <span class="category-badge">
                                <i class="fas fa-tag me-1"></i> 
                                Category: <?php echo htmlspecialchars($category_filter); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($products_result && $products_result->num_rows > 0): ?>
                        <?php while($product = $products_result->fetch_assoc()): 
                            // Check image path - corrected to look in assets/images
                            $image_path = '';
                            if ($product['image']) {
                                // First check if it's already a full path
                                if (file_exists($product['image'])) {
                                    $image_path = $product['image'];
                                } 
                                // Check in assets/images/
                                elseif (file_exists('../assets/images/' . $product['image'])) {
                                    $image_path = '../assets/images/' . $product['image'];
                                }
                                // Check in uploads/products/
                                elseif (file_exists('../uploads/products/' . $product['image'])) {
                                    $image_path = '../uploads/products/' . $product['image'];
                                }
                                // Check if image contains uploads/
                                elseif (strpos($product['image'], 'uploads/') !== false && file_exists('../' . $product['image'])) {
                                    $image_path = '../' . $product['image'];
                                }
                            }
                        ?>
                        <div class="product-card <?php echo $product['admin_approved']; ?>">
                            <div class="row align-items-center">
                                <!-- Product Image -->
                                <div class="col-md-2">
                                    <div class="product-image-container">
                                        <?php if($image_path && file_exists($image_path)): ?>
                                            <img src="<?php echo $image_path; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center h-100">
                                                <i class="fas fa-leaf fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Product Details -->
                                <div class="col-md-3">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <p class="text-muted small mb-2">
                                        <?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>
                                        <?php if(strlen($product['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    <div class="small text-muted">
                                        <div class="mb-1">
                                            <i class="fas fa-user me-1"></i> 
                                            <?php echo htmlspecialchars($product['farmer_name']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar me-1"></i> 
                                            <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Category & Stock -->
                                <div class="col-md-2">
                                    <?php if($product['category']): ?>
                                        <span class="category-badge mb-2 d-inline-block">
                                            <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-cubes me-1"></i> 
                                        Stock: <strong><?php echo $product['stock']; ?></strong>
                                    </div>
                                    <?php if($product['admin_approved'] == 'approved' && $product['approved_at']): ?>
                                        <div class="small text-success mt-2">
                                            <i class="fas fa-calendar-check me-1"></i> 
                                            Approved: <?php echo date('M d', strtotime($product['approved_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Price -->
                                <div class="col-md-2">
                                    <div class="price-tag">
                                        LKR <?php echo number_format($product['price'], 2); ?>
                                    </div>
                                    <?php if($product['admin_approved'] == 'rejected' && $product['rejection_reason']): ?>
                                        <div class="small text-danger mt-2">
                                            <i class="fas fa-exclamation-circle me-1"></i> 
                                            <?php echo substr(htmlspecialchars($product['rejection_reason']), 0, 60); ?>
                                            <?php if(strlen($product['rejection_reason']) > 60): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Status & Actions -->
                                <div class="col-md-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="status-badge badge-<?php echo $product['admin_approved']; ?>">
                                            <?php if($product['admin_approved'] == 'pending'): ?>
                                                <i class="fas fa-clock me-1"></i> Pending
                                            <?php elseif($product['admin_approved'] == 'approved'): ?>
                                                <i class="fas fa-check-circle me-1"></i> Approved
                                            <?php else: ?>
                                                <i class="fas fa-times-circle me-1"></i> Rejected
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="action-buttons d-flex justify-content-end mt-2">
                                        <!-- View Button with AJAX -->
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1 view-product-btn" 
                                                data-product-id="<?php echo $product['product_id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if($product['admin_approved'] == 'pending'): ?>
                                            <button class="btn btn-success btn-sm me-1" onclick="confirmApprove(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['farmer_name']); ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['farmer_name']); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif($product['admin_approved'] == 'approved'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="showRejectModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['farmer_name']); ?>')">
                                                <i class="fas fa-undo me-1"></i> Unapprove
                                            </button>
                                        <?php elseif($product['admin_approved'] == 'rejected'): ?>
                                            <button class="btn btn-success btn-sm" onclick="confirmApprove(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['farmer_name']); ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <h5 class="text-muted mb-3">No products found</h5>
                            <p class="text-muted mb-4">
                                <?php 
                                if(!empty($search)) {
                                    echo "No products found matching '" . htmlspecialchars($search) . "'";
                                } elseif($category_filter) {
                                    echo "No products in category '" . htmlspecialchars($category_filter) . "' with status '" . $status_filter . "'";
                                } else {
                                    echo "No products with status '" . $status_filter . "' found.";
                                }
                                ?>
                            </p>
                            <a href="manage_products.php" class="btn btn-primary">
                                <i class="fas fa-redo me-1"></i> View All Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_products.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left me-1"></i> Previous
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_products.php?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_products.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AJAX Modal for Product Details -->
    <div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productDetailModalLabel">
                        <i class="fas fa-leaf me-2"></i>
                        Product Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="productModalContent">
                    <div class="modal-spinner">
                        <div class="spinner"></div>
                        <p>Loading product details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal (Single Modal for All Approve/Reject Actions) -->
    <div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="actionModalHeader">
                    <h5 class="modal-title" id="actionModalTitle">
                        <i class="fas fa-check-circle me-2"></i>
                        Confirm Action
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="actionModalBody">
                    <div class="confirm-icon" id="actionModalIcon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h5 class="mb-3" id="actionModalQuestion">Are you sure?</h5>
                    <p class="text-muted mb-0" id="actionModalProductName"></p>
                    <p class="text-muted" id="actionModalFarmerName"></p>
                    
                    <!-- Rejection Reason Field (hidden by default) -->
                    <div id="rejectionReasonField" style="display: none;" class="mt-3">
                        <textarea class="form-control" id="rejectionReason" rows="3" placeholder="Reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-center" id="actionModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn" id="actionConfirmBtn">
                        <i class="fas fa-check me-1"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store current action data
        let currentAction = {
            productId: null,
            actionType: null,
            productName: '',
            farmerName: ''
        };
        
        // Show approve confirmation
        function confirmApprove(productId, productName, farmerName) {
            currentAction = {
                productId: productId,
                actionType: 'approve',
                productName: productName,
                farmerName: farmerName
            };
            
            // Update modal for approve
            $('#actionModalHeader').removeClass('reject-header').addClass('approve-header');
            $('#actionModalTitle').html('<i class="fas fa-check-circle me-2"></i> Confirm Approval');
            $('#actionModalIcon').html('<i class="fas fa-check-circle" style="color: #27ae60; font-size: 4rem;"></i>');
            $('#actionModalQuestion').text('Approve this product?');
            $('#actionModalProductName').text('Product: ' + productName);
            $('#actionModalFarmerName').text('Farmer: ' + farmerName);
            $('#rejectionReasonField').hide();
            
            // Update confirm button
            $('#actionConfirmBtn').removeClass('btn-danger btn-warning').addClass('btn-success').html('<i class="fas fa-check me-1"></i> Approve Product');
            
            // Show modal
            $('#actionConfirmModal').modal('show');
        }
        
        // Show reject modal
        function showRejectModal(productId, productName, farmerName) {
            currentAction = {
                productId: productId,
                actionType: 'reject',
                productName: productName,
                farmerName: farmerName
            };
            
            // Update modal for reject
            $('#actionModalHeader').removeClass('approve-header').addClass('reject-header');
            $('#actionModalTitle').html('<i class="fas fa-exclamation-triangle me-2"></i> Reject Product');
            $('#actionModalIcon').html('<i class="fas fa-times-circle" style="color: #e74c3c; font-size: 4rem;"></i>');
            $('#actionModalQuestion').text('Reject this product?');
            $('#actionModalProductName').text('Product: ' + productName);
            $('#actionModalFarmerName').text('Farmer: ' + farmerName);
            $('#rejectionReasonField').show();
            
            // Update confirm button
            $('#actionConfirmBtn').removeClass('btn-success btn-warning').addClass('btn-danger').html('<i class="fas fa-times me-1"></i> Reject Product');
            
            // Show modal
            $('#actionConfirmModal').modal('show');
        }
        
        // Handle confirm button click
        $('#actionConfirmBtn').on('click', function() {
            const productId = currentAction.productId;
            const actionType = currentAction.actionType;
            
            if (!productId || !actionType) return;
            
            // Prepare data
            let postData = {
                ajax_action: actionType,
                product_id: productId
            };
            
            // Add rejection reason if rejecting
            if (actionType === 'reject') {
                const reason = $('#rejectionReason').val().trim();
                if (!reason) {
                    alert('Please provide a rejection reason');
                    return;
                }
                postData.rejection_reason = reason;
            }
            
            // Close modal
            $('#actionConfirmModal').modal('hide');
            
            // Show loading indicator (optional)
            $('body').append('<div class="modal-spinner" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;"><div class="spinner"></div><p>Processing...</p></div>');
            
            // Send AJAX request
            $.ajax({
                url: 'manage_products.php',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    $('.modal-spinner').remove();
                    
                    if (response.success) {
                        // Show success message
                        showCustomAlert(response.message, 'success');
                        
                        // Reload page after 1 second to reflect changes
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showCustomAlert(response.message || 'Action failed', 'danger');
                    }
                },
                error: function() {
                    $('.modal-spinner').remove();
                    showCustomAlert('An error occurred', 'danger');
                }
            });
        });
        
        // Custom alert function
        function showCustomAlert(message, type) {
            const icon = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-times-circle' : 'fa-info-circle');
            const title = type === 'success' ? 'Success!' : (type === 'danger' ? 'Error!' : 'Info!');
            
            const alertHtml = `
                <div class="alert-custom ${type}" id="customAlert">
                    <div class="alert-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">${title}</div>
                        <div class="alert-message">${message}</div>
                    </div>
                    <div class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            setTimeout(function() {
                $('#customAlert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Clear rejection reason when modal is hidden
        $('#actionConfirmModal').on('hidden.bs.modal', function() {
            $('#rejectionReason').val('');
        });
        
        // View product details (your existing code)
        $(document).ready(function() {
            $('.view-product-btn').on('click', function(e) {
                e.preventDefault();
                const productId = $(this).data('product-id');
                
                $('#productDetailModal').modal('show');
                
                $.ajax({
                    url: 'manage_products.php?ajax=get_product&id=' + productId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayProductDetails(response);
                        } else {
                            $('#productModalContent').html('<div class="alert alert-danger">Failed to load product details.</div>');
                        }
                    },
                    error: function() {
                        $('#productModalContent').html('<div class="alert alert-danger">Error loading product details.</div>');
                    }
                });
            });
            
            function displayProductDetails(data) {
                const product = data.product;
                
                let statusClass = 'badge-pending';
                let statusIcon = 'fa-clock';
                let statusText = 'Pending';
                
                if (product.admin_approved === 'approved') {
                    statusClass = 'badge-approved';
                    statusIcon = 'fa-check-circle';
                    statusText = 'Approved';
                } else if (product.admin_approved === 'rejected') {
                    statusClass = 'badge-rejected';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Rejected';
                }
                
                const html = `
                    <div class="row">
                        <div class="col-md-5">
                            <div class="product-detail-card">
                                <h6><i class="fas fa-image me-2" style="color: #3498db;"></i>Product Image</h6>
                                <div class="text-center">
                                    <img src="${product.image_url}" alt="${product.name}" class="modal-product-image">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="product-detail-card">
                                <h6><i class="fas fa-info-circle me-2" style="color: #3498db;"></i>Product Information</h6>
                                <div class="detail-item">
                                    <span class="detail-label">Name:</span>
                                    <span class="detail-value"><strong>${product.name}</strong></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Category:</span>
                                    <span class="detail-value">${product.category || 'Uncategorized'}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Price:</span>
                                    <span class="detail-value"><strong class="text-success">LKR ${parseFloat(product.price).toFixed(2)}</strong></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Stock:</span>
                                    <span class="detail-value">${product.stock} kg</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value">
                                        <span class="status-badge ${statusClass}">
                                            <i class="fas ${statusIcon} me-1"></i> ${statusText}
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Submitted:</span>
                                    <span class="detail-value">${new Date(product.created_at).toLocaleDateString()}</span>
                                </div>
                                ${product.approved_at ? `
                                <div class="detail-item">
                                    <span class="detail-label">Approved:</span>
                                    <span class="detail-value">${new Date(product.approved_at).toLocaleDateString()}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="product-detail-card">
                                <h6><i class="fas fa-align-left me-2" style="color: #27ae60;"></i>Description</h6>
                                <p>${product.description || 'No description provided.'}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="product-detail-card">
                                <h6><i class="fas fa-user me-2" style="color: #27ae60;"></i>Farmer Information</h6>
                                <div class="detail-item">
                                    <span class="detail-label">Name:</span>
                                    <span class="detail-value">${product.farmer_name}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value">${product.farmer_email}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value">${product.farmer_phone || 'Not provided'}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value">${product.farm_location || 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${product.rejection_reason ? `
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="product-detail-card" style="border-left-color: #e74c3c;">
                                <h6><i class="fas fa-exclamation-triangle me-2" style="color: #e74c3c;"></i>Rejection Reason</h6>
                                <p class="text-danger">${product.rejection_reason}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                `;
                
                $('#productModalContent').html(html);
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
        
        // Highlight active stat card
        const currentStatus = "<?php echo $status_filter; ?>";
        $('.stat-card').removeClass('active');
        $(`.stat-card.${currentStatus.toLowerCase()}`).addClass('active');
        
        // Auto-focus on search field if there's a search
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.has('search') && urlParams.get('search')) {
                document.querySelector('input[name="search"]').focus();
            }
        });
    </script>
</body>
</html>