<?php
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Check admin role permissions
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$allowed_roles = ['super_admin', 'admin', 'content_manager'];

if (!in_array($admin_role, $allowed_roles)) {
    $_SESSION['message'] = "You don't have permission to access this page";
    $_SESSION['message_type'] = 'danger';
    header("Location: dashboard.php");
    exit();
}

// Function to notify customer
function notifyCustomer($conn, $request_id, $status, $farmer_name = '') {
    // Get customer email and request details
    $stmt = $conn->prepare("
        SELECT u.email, u.name as customer_name, pr.product_name 
        FROM product_requests pr 
        JOIN users u ON pr.customer_id = u.user_id 
        WHERE pr.request_id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $customer_email = $data['email'];
        $customer_name = $data['customer_name'];
        $product_name = $data['product_name'];
        
        // Log notification
        $message = "Customer {$customer_name} notified about request status: {$status} for {$product_name}" . 
                  ($farmer_name ? " (Assigned to: {$farmer_name})" : "");
        
        $_SESSION['last_notification'] = [
            'customer' => $customer_name,
            'product' => $product_name,
            'status' => $status,
            'farmer' => $farmer_name
        ];
    }
}

// Handle POST actions (for farmer assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'assign') {
        $request_id = (int)$_POST['request_id'];
        $csrf_token = $_POST['csrf_token'];
        $assigned_farmer_id = isset($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : 0;
        
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            $_SESSION['message'] = "Security token invalid. Please try again.";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        // Validate farmer ID
        if ($assigned_farmer_id <= 0) {
            $_SESSION['message'] = "Please select a farmer to assign.";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        // Check if request exists
        $check_stmt = $conn->prepare("SELECT status, product_name FROM product_requests WHERE request_id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $_SESSION['message'] = "Request not found";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        $request_data = $check_result->fetch_assoc();
        
        // Get farmer name for notification
        $farmer_stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
        $farmer_stmt->bind_param("i", $assigned_farmer_id);
        $farmer_stmt->execute();
        $farmer_result = $farmer_stmt->get_result();
        
        if ($farmer_result->num_rows === 0) {
            $_SESSION['message'] = "Selected farmer not found.";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        $farmer_name = $farmer_result->fetch_assoc()['name'];
        
        // Update request status and assign farmer
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Approved', assigned_farmer_id = ?, updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("ii", $assigned_farmer_id, $request_id);
        
        if ($stmt->execute()) {
            // Log the assignment
            $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
            $admin_id = $_SESSION['admin_id'];
            $old_status = $request_data['status'];
            $new_status = 'Approved';
            $notes = "Assigned to farmer: " . $farmer_name . " (ID: " . $assigned_farmer_id . ")";
            $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Store farmer assignment notification
            $_SESSION['farmer_assignment_notification'] = [
                'farmer_name' => $farmer_name,
                'farmer_id' => $assigned_farmer_id,
                'product_name' => $request_data['product_name'],
                'request_id' => $request_id
            ];
            
            // Notify customer
            notifyCustomer($conn, $request_id, 'Approved', $farmer_name);
            
            $_SESSION['message'] = "Request approved and assigned to farmer " . htmlspecialchars($farmer_name) . "!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error assigning request to farmer.";
            $_SESSION['message_type'] = 'danger';
        }
        
        $stmt->close();
        
        // Redirect back to same page
        header("Location: manage_requests.php");
        exit();
    }
}

// Handle GET actions with CSRF protection
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    $request_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $csrf_token = $_GET['csrf_token'];
    
    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $_SESSION['message'] = "Security token invalid. Please try again.";
        $_SESSION['message_type'] = 'danger';
        header("Location: manage_requests.php");
        exit();
    }
    
    // Validate action
    $allowed_actions = ['view', 'approve', 'reject', 'delete', 'review', 'complete', 'assign'];
    if (!in_array($action, $allowed_actions)) {
        $_SESSION['message'] = "Invalid action specified";
        $_SESSION['message_type'] = 'danger';
        header("Location: manage_requests.php");
        exit();
    }
    
    // Check if request exists and get current status
    $check_stmt = $conn->prepare("SELECT status, assigned_farmer_id FROM product_requests WHERE request_id = ?");
    $check_stmt->bind_param("i", $request_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['message'] = "Request not found";
        $_SESSION['message_type'] = 'danger';
        header("Location: manage_requests.php");
        exit();
    }
    
    $request_data = $check_result->fetch_assoc();
    $current_status = $request_data['status'];
    $current_farmer_id = $request_data['assigned_farmer_id'];
    
    if ($action == 'view') {
        header("Location: view_request.php?id=$request_id");
        exit;
    }
    elseif ($action == 'review') {
        // Mark as reviewed
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Reviewed', updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Request marked as reviewed!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating request";
            $_SESSION['message_type'] = 'danger';
        }
    }
    elseif ($action == 'approve') {
        // Check if farmer_id is provided for assignment
        $assigned_farmer_id = isset($_GET['farmer_id']) ? (int)$_GET['farmer_id'] : null;
        
        if ($assigned_farmer_id) {
            // Get farmer name for notification
            $farmer_stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
            $farmer_stmt->bind_param("i", $assigned_farmer_id);
            $farmer_stmt->execute();
            $farmer_result = $farmer_stmt->get_result();
            $farmer_name = $farmer_result->num_rows > 0 ? $farmer_result->fetch_assoc()['name'] : '';
            
            // Update status to Approved and assign farmer
            $stmt = $conn->prepare("UPDATE product_requests SET status = 'Approved', assigned_farmer_id = ?, updated_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("ii", $assigned_farmer_id, $request_id);
            if ($stmt->execute()) {
                // Notify customer
                notifyCustomer($conn, $request_id, 'Approved', $farmer_name);
                
                $_SESSION['message'] = "Request approved and assigned to farmer! Customer has been notified.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error approving request";
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            // Update status to Approved without assignment
            $stmt = $conn->prepare("UPDATE product_requests SET status = 'Approved', updated_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                // Notify customer
                notifyCustomer($conn, $request_id, 'Approved');
                
                $_SESSION['message'] = "Request approved successfully! Customer has been notified.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error approving request";
                $_SESSION['message_type'] = 'danger';
            }
        }
    }
    elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Rejected', updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            // Notify customer
            notifyCustomer($conn, $request_id, 'Rejected');
            
            $_SESSION['message'] = "Request rejected! Customer has been notified.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error rejecting request";
            $_SESSION['message_type'] = 'danger';
        }
    }
    elseif ($action == 'complete') {
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Completed', updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            // Notify customer
            notifyCustomer($conn, $request_id, 'Completed');
            
            $_SESSION['message'] = "Request marked as completed! Customer has been notified.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating request";
            $_SESSION['message_type'] = 'danger';
        }
    }
    elseif ($action == 'delete') {
        // Check if admin has permission to delete
        if (!in_array($admin_role, ['super_admin', 'admin'])) {
            $_SESSION['message'] = "You don't have permission to delete requests";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        $stmt = $conn->prepare("DELETE FROM product_requests WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Request deleted!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting request";
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    header("Location: manage_requests.php");
    exit;
}

// Filter parameters with sanitization
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate status filter
$valid_statuses = ['Pending', 'Reviewed', 'Approved', 'Rejected', 'Completed'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'Pending';
}

// Build query with prepared statements
$query = "SELECT pr.*, 
                 u.name as customer_name, 
                 u.email as customer_email,
                 f.name as farmer_name,
                 f.email as farmer_email
          FROM product_requests pr 
          JOIN users u ON pr.customer_id = u.user_id 
          LEFT JOIN users f ON pr.assigned_farmer_id = f.user_id
          WHERE pr.status = ?";

$count_query = "SELECT COUNT(*) as total FROM product_requests pr WHERE pr.status = ?";

$params = [$status_filter];
$count_params = [$status_filter];
$param_types = "s";
$count_param_types = "s";

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $query .= " AND (pr.product_name LIKE ? OR pr.description LIKE ? OR u.name LIKE ? OR f.name LIKE ?)";
    $count_query .= " AND (pr.product_name LIKE ? OR pr.description LIKE ? OR pr.customer_id IN (SELECT user_id FROM users WHERE name LIKE ?) OR pr.assigned_farmer_id IN (SELECT user_id FROM users WHERE name LIKE ?))";
    
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
    $count_param_types .= "ssss";
}

// Order by
$query .= " ORDER BY 
            CASE 
                WHEN pr.urgency = 'High' THEN 1
                WHEN pr.urgency = 'Medium' THEN 2
                WHEN pr.urgency = 'Low' THEN 3
                ELSE 4
            END,
            pr.created_at DESC";

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare($count_query);
if ($count_params) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_requests = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_requests / $limit);

// Adjust page if out of bounds
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Get requests with pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$requests_stmt = $conn->prepare($query);
if ($params) {
    $requests_stmt->bind_param($param_types, ...$params);
}
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Get counts for all statuses using prepared statements
function getRequestCount($conn, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_requests WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

$pending_count = getRequestCount($conn, 'Pending');
$reviewed_count = getRequestCount($conn, 'Reviewed');
$approved_count = getRequestCount($conn, 'Approved');
$rejected_count = getRequestCount($conn, 'Rejected');
$completed_count = getRequestCount($conn, 'Completed');
$total_all = $pending_count + $reviewed_count + $approved_count + $rejected_count + $completed_count;

// Get all farmers for dropdown
$farmers_query = $conn->prepare("SELECT user_id, name, email, farm_location FROM users WHERE role = 'farmer' AND status = 'active' ORDER BY name");
$farmers_query->execute();
$farmers_result = $farmers_query->get_result();

// Check for messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message']);
unset($_SESSION['message_type']);

// Check for farmer assignment notification
if (isset($_SESSION['farmer_assignment_notification'])) {
    $farmer_assignment = $_SESSION['farmer_assignment_notification'];
    unset($_SESSION['farmer_assignment_notification']);
}

// Show last notification if exists
if (isset($_SESSION['last_notification'])) {
    $last_notification = $_SESSION['last_notification'];
    unset($_SESSION['last_notification']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Request Management - SpiceCeylon Admin</title>
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
            --reviewed: #3498db;
            --approved: #27ae60;
            --rejected: #e74c3c;
            --completed: #9b59b6;
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
        
        .request-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
        }
        
        .request-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .request-card.Pending { border-left: 4px solid var(--pending); }
        .request-card.Reviewed { border-left: 4px solid var(--reviewed); }
        .request-card.Approved { border-left: 4px solid var(--approved); }
        .request-card.Rejected { border-left: 4px solid var(--rejected); }
        .request-card.Completed { border-left: 4px solid var(--completed); }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-Pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-Reviewed { background: rgba(52, 152, 219, 0.15); color: var(--reviewed); }
        .badge-Approved { background: rgba(39, 174, 96, 0.15); color: var(--approved); }
        .badge-Rejected { background: rgba(231, 76, 60, 0.15); color: var(--rejected); }
        .badge-Completed { background: rgba(155, 89, 182, 0.15); color: var(--completed); }
        
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
        
        .stat-card.reviewed {
            background: linear-gradient(135deg, var(--reviewed), #2980b9);
        }
        
        .stat-card.approved {
            background: linear-gradient(135deg, var(--approved), #219653);
        }
        
        .stat-card.rejected {
            background: linear-gradient(135deg, var(--rejected), #c0392b);
        }
        
        .stat-card.completed {
            background: linear-gradient(135deg, var(--completed), #8e44ad);
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
        
        .spice-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--spice-red), #d35400);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .action-buttons .btn {
            padding: 5px 12px;
            font-size: 0.85rem;
            margin: 2px;
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
        
        .urgency-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
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
        
        .farmer-badge {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #2980b9;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            margin-left: 5px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--spice-red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .farmer-assigned-alert {
            background: linear-gradient(135deg, #d1e7dd, #a3cfbb);
            border: 1px solid #badbcc;
            color: #0f5132;
            border-left: 4px solid var(--spice-green);
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
                                <i class="fas fa-boxes me-2" style="color: var(--spice-red);"></i>
                                Product Request Management
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Manage product requests from customers and assign to farmers
                            </p>
                        </div>
                        <div style="background: linear-gradient(135deg, var(--spice-red), #d35400); color: white; padding: 10px 20px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 10px rgba(184, 92, 56, 0.3); position: relative;">
                            <i class="fas fa-inbox me-1"></i> Total Requests: <?php echo number_format($total_all); ?>
                            <?php if(isset($last_notification) || isset($farmer_assignment)): ?>
                            <span class="notification-badge" title="Recent notification">
                                <i class="fas fa-bell"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i> 
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Farmer Assignment Notification -->
                <?php if(isset($farmer_assignment)): ?>
                <div class="alert farmer-assigned-alert alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-user-tie me-2"></i>
                    <strong>✓ Request Assigned Successfully!</strong><br>
                    <strong><?php echo htmlspecialchars($farmer_assignment['farmer_name']); ?></strong> (ID: <?php echo $farmer_assignment['farmer_id']; ?>) has been assigned to: <br>
                    <strong>"<?php echo htmlspecialchars($farmer_assignment['product_name']); ?>"</strong> (Request ID: #<?php echo $farmer_assignment['request_id']; ?>)<br>
                    <small class="text-muted">The farmer will see this request in their dashboard immediately.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Last Customer Notification Info -->
                <?php if(isset($last_notification)): ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-bell me-2"></i>
                    <strong>Customer Notified:</strong> 
                    <?php echo htmlspecialchars($last_notification['customer']); ?> was notified about 
                    "<?php echo htmlspecialchars($last_notification['product']); ?>" - 
                    Status: <?php echo $last_notification['status']; ?>
                    <?php if(!empty($last_notification['farmer'])): ?>
                        (Assigned to: <?php echo htmlspecialchars($last_notification['farmer']); ?>)
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Status Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <a href="manage_requests.php?status=Pending" class="text-decoration-none">
                            <div class="stat-card pending <?php echo $status_filter == 'Pending' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($pending_count); ?></div>
                                        <div class="stat-label">Pending Requests</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-clock me-1"></i> Awaiting review
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="manage_requests.php?status=Reviewed" class="text-decoration-none">
                            <div class="stat-card reviewed <?php echo $status_filter == 'Reviewed' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($reviewed_count); ?></div>
                                        <div class="stat-label">Reviewed</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-eye me-1"></i> Under consideration
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="manage_requests.php?status=Approved" class="text-decoration-none">
                            <div class="stat-card approved <?php echo $status_filter == 'Approved' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($approved_count); ?></div>
                                        <div class="stat-label">Approved</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-check me-1"></i> Assigned to farmers
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="manage_requests.php?status=Rejected" class="text-decoration-none">
                            <div class="stat-card rejected <?php echo $status_filter == 'Rejected' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($rejected_count); ?></div>
                                        <div class="stat-label">Rejected</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-times me-1"></i> Request declined
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-times"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="manage_requests.php?status=Completed" class="text-decoration-none">
                            <div class="stat-card completed <?php echo $status_filter == 'Completed' ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($completed_count); ?></div>
                                        <div class="stat-label">Completed</div>
                                        <div class="small opacity-75 mt-2">
                                            <i class="fas fa-check-double me-1"></i> Request fulfilled
                                        </div>
                                    </div>
                                    <div class="display-6 opacity-50">
                                        <i class="fas fa-check-double"></i>
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
                                Filter Requests
                            </h5>
                            <form method="GET" action="manage_requests.php" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Product name, customer, farmer, description..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Reviewed" <?php echo $status_filter == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="fas fa-filter me-1"></i> Apply
                                        </button>
                                        <a href="manage_requests.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-4 h-100">
                                <h6 class="mb-3">
                                    <i class="fas fa-chart-bar me-2" style="color: var(--spice-blue);"></i>
                                    Quick Stats
                                </h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Pending:</span>
                                        <strong class="text-warning"><?php echo $pending_count; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Reviewed:</span>
                                        <strong class="text-primary"><?php echo $reviewed_count; ?></strong>
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
                                        <span class="text-muted">Completed:</span>
                                        <strong style="color: var(--spice-purple);"><?php echo $completed_count; ?></strong>
                                    </div>
                                </div>
                                <hr class="my-3">
                                <div class="small text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Approved requests are assigned to farmers for fulfillment.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests List -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--spice-red);"></i>
                            Product Request List (<?php echo $total_requests; ?> requests)
                        </h5>
                        <div class="text-muted small">
                            <i class="fas fa-user-tie me-1"></i>
                            <?php echo $farmers_result->num_rows; ?> active farmers available
                        </div>
                    </div>
                    
                    <?php if($requests_result->num_rows > 0): ?>
                        <?php while($request = $requests_result->fetch_assoc()): ?>
                        <div class="request-card <?php echo $request['status']; ?>">
                            <div class="row align-items-center">
                                <!-- Request Icon -->
                                <div class="col-md-1">
                                    <div class="spice-icon">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                                
                                <!-- Request Details -->
                                <div class="col-md-3">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($request['product_name']); ?></h6>
                                    <div class="small text-muted">
                                        <div class="mb-1">
                                            <i class="fas fa-user me-1"></i> 
                                            <?php echo htmlspecialchars($request['customer_name']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar me-1"></i> 
                                            <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                        </div>
                                        <?php if(isset($request['category'])): ?>
                                        <div class="mt-1">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($request['category']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if(!empty($request['farmer_name'])): ?>
                                        <div class="mt-1">
                                            <span class="farmer-badge">
                                                <i class="fas fa-user-tie me-1"></i>
                                                <?php echo htmlspecialchars($request['farmer_name']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Quantity & Description -->
                                <div class="col-md-3">
                                    <div class="small">
                                        <div class="mb-2">
                                            <span class="badge bg-info">
                                                <i class="fas fa-hashtag me-1"></i> 
                                                Quantity: <?php echo $request['quantity_requested']; ?>
                                            </span>
                                            <?php if(isset($request['urgency'])): ?>
                                            <span class="badge urgency-badge bg-<?php 
                                                if($request['urgency'] == 'High') echo 'danger';
                                                elseif($request['urgency'] == 'Medium') echo 'warning';
                                                else echo 'secondary';
                                            ?> ms-1">
                                                <?php echo $request['urgency']; ?> Priority
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($request['description']): ?>
                                            <div class="text-muted">
                                                <i class="fas fa-comment me-1"></i> 
                                                <?php echo substr(htmlspecialchars($request['description']), 0, 80); ?>
                                                <?php if(strlen($request['description']) > 80): ?>...<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-2">
                                    <span class="status-badge badge-<?php echo $request['status']; ?>">
                                        <?php if($request['status'] == 'Pending'): ?>
                                            <i class="fas fa-clock me-1"></i> Pending
                                        <?php elseif($request['status'] == 'Reviewed'): ?>
                                            <i class="fas fa-eye me-1"></i> Reviewed
                                        <?php elseif($request['status'] == 'Approved'): ?>
                                            <i class="fas fa-check me-1"></i> Approved
                                        <?php elseif($request['status'] == 'Completed'): ?>
                                            <i class="fas fa-check-double me-1"></i> Completed
                                        <?php else: ?>
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-3">
                                    <div class="action-buttons d-flex justify-content-end">
                                        <?php if($request['status'] == 'Pending'): ?>
                                            <a href="manage_requests.php?action=review&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-info btn-sm me-1"
                                               onclick="return confirm('Mark this request as reviewed?')">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                            
                                            <!-- Assign & Approve button -->
                                            <button class="btn btn-success btn-sm me-1 btn-assign-farmer" 
                                                    data-request-id="<?php echo $request['request_id']; ?>">
                                                <i class="fas fa-user-tie me-1"></i> Assign & Approve
                                            </button>
                                            
                                            <!-- Direct approve without assignment -->
                                            <a href="manage_requests.php?action=approve&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-outline-success btn-sm me-1"
                                               onclick="return confirm('Approve without assigning to a farmer?\n\nCustomer will be notified immediately.\nFarmer will need to be assigned later.')">
                                                <i class="fas fa-check-circle"></i> Direct Approve
                                            </a>
                                            
                                            <a href="manage_requests.php?action=reject&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Reject this product request? Customer will be notified.')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                            
                                        <?php elseif($request['status'] == 'Reviewed'): ?>
                                            <!-- Assign & Approve button -->
                                            <button class="btn btn-success btn-sm me-1 btn-assign-farmer" 
                                                    data-request-id="<?php echo $request['request_id']; ?>">
                                                <i class="fas fa-user-tie me-1"></i> Assign & Approve
                                            </button>
                                            
                                            <!-- Direct approve without assignment -->
                                            <a href="manage_requests.php?action=approve&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-outline-success btn-sm me-1"
                                               onclick="return confirm('Approve without assigning to a farmer?\n\nCustomer will be notified immediately.\nFarmer will need to be assigned later.')">
                                                <i class="fas fa-check-circle"></i> Direct Approve
                                            </a>
                                            
                                            <a href="manage_requests.php?action=reject&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Reject this product request? Customer will be notified.')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                            
                                        <?php elseif($request['status'] == 'Approved'): ?>
                                            <a href="manage_requests.php?action=complete&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-secondary btn-sm me-1"
                                               onclick="return confirm('Mark this request as completed? Customer will be notified.')">
                                                <i class="fas fa-check-double"></i> Complete
                                            </a>
                                            
                                            <!-- Reassign button if farmer hasn't accepted yet -->
                                            <?php if(empty($request['farmer_name'])): ?>
                                                <button class="btn btn-warning btn-sm me-1 btn-assign-farmer" 
                                                        data-request-id="<?php echo $request['request_id']; ?>">
                                                    <i class="fas fa-sync-alt me-1"></i> Reassign
                                                </button>
                                            <?php endif; ?>
                                            
                                        <?php endif; ?>
                                        
                                        <?php if(in_array($admin_role, ['super_admin', 'admin'])): ?>
                                            <a href="manage_requests.php?action=delete&id=<?php echo $request['request_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('Delete this request permanently? This cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Show farmer info if assigned -->
                                    <?php if(!empty($request['assigned_farmer_id'])): ?>
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-user-tie me-1"></i> 
                                            <?php if(!empty($request['farmer_name'])): ?>
                                                Assigned to: <?php echo htmlspecialchars($request['farmer_name']); ?>
                                            <?php else: ?>
                                                Farmer ID: <?php echo $request['assigned_farmer_id']; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <h5 class="text-muted mb-3">No product requests found</h5>
                            <p class="text-muted mb-4">
                                <?php 
                                if(!empty($search)) {
                                    echo "No requests found matching '" . htmlspecialchars($search) . "'";
                                } else {
                                    echo "No requests with status '" . htmlspecialchars($status_filter) . "' found.";
                                }
                                ?>
                            </p>
                            <a href="manage_requests.php" class="btn btn-primary">
                                <i class="fas fa-redo me-1"></i> View All Requests
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_requests.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left me-1"></i> Previous
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_requests.php?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
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
                            <a class="page-link" href="manage_requests.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Farmer Assignment Modal -->
    <div class="modal fade" id="assignFarmerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--spice-red); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie me-2"></i> Assign to Farmer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignFarmerForm" method="POST" action="manage_requests.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="request_id" id="assignRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Farmer</label>
                            <select class="form-select" name="farmer_id" id="farmerSelect" required>
                                <option value="">-- Select a farmer --</option>
                                <?php
                                // Reset farmers result pointer
                                $farmers_result->data_seek(0);
                                while($farmer = $farmers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $farmer['user_id']; ?>">
                                        <?php echo htmlspecialchars($farmer['name']); ?> 
                                        <?php if(!empty($farmer['farm_location'])): ?>
                                            (<?php echo htmlspecialchars($farmer['farm_location']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">This farmer will receive the request in their dashboard</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> The selected farmer will receive this request and can:
                            <ul class="mb-0 mt-2">
                                <li>Accept the request (Customer will be notified)</li>
                                <li>Reject the request with a reason</li>
                                <li>Mark as completed when fulfilled</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i> Assign & Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Farmer assignment modal
            $('.btn-assign-farmer').click(function() {
                var requestId = $(this).data('request-id');
                $('#assignRequestId').val(requestId);
                $('#assignFarmerModal').modal('show');
            });
            
            // Handle direct approve confirmation
            $('a[href*="action=approve"]').not('.btn-assign-farmer').click(function(e) {
                return confirm('Approve without assigning to a farmer?\n\nCustomer will be notified immediately.\nFarmer will need to be assigned later.');
            });
            
            // Handle reject confirmation
            $('a[href*="action=reject"]').click(function(e) {
                return confirm('Reject this request?\n\nCustomer will be notified of the rejection.');
            });
            
            // Handle complete confirmation
            $('a[href*="action=complete"]').click(function(e) {
                return confirm('Mark this request as completed?\n\nCustomer will be notified that their request has been fulfilled.');
            });
            
            // Handle delete confirmation
            $('a[href*="action=delete"]').click(function(e) {
                return confirm('Delete this request permanently?\n\nThis action cannot be undone.');
            });
        });
    </script>
</body>
</html>