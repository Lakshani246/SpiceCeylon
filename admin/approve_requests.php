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
function notifyCustomer($conn, $request_id, $status, $farmer_name = '', $reject_reason = '') {
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
                  ($farmer_name ? " (Assigned to: {$farmer_name})" : "") .
                  ($reject_reason ? " - Reason: {$reject_reason}" : "");
        
        $_SESSION['last_notification'] = [
            'customer' => $customer_name,
            'product' => $product_name,
            'status' => $status,
            'farmer' => $farmer_name,
            'reason' => $reject_reason
        ];
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'assign') {
        $request_id = (int)$_POST['request_id'];
        $csrf_token = $_POST['csrf_token'];
        $assigned_farmer_id = isset($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : 0;
        
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            echo json_encode(['success' => false, 'message' => "Security token invalid. Please try again."]);
            exit();
        }
        
        // Validate farmer ID
        if ($assigned_farmer_id <= 0) {
            echo json_encode(['success' => false, 'message' => "Please select a farmer to assign."]);
            exit();
        }
        
        // Check if request exists
        $check_stmt = $conn->prepare("SELECT status, product_name FROM product_requests WHERE request_id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Request not found"]);
            exit();
        }
        
        $request_data = $check_result->fetch_assoc();
        
        // Get farmer name for notification
        $farmer_stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $farmer_stmt->bind_param("i", $assigned_farmer_id);
        $farmer_stmt->execute();
        $farmer_result = $farmer_stmt->get_result();
        
        if ($farmer_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Selected farmer not found."]);
            exit();
        }
        
        $farmer_data = $farmer_result->fetch_assoc();
        $farmer_name = $farmer_data['name'];
        $farmer_email = $farmer_data['email'];
        
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
            
            echo json_encode([
                'success' => true,
                'message' => "Request approved and assigned to farmer " . htmlspecialchars($farmer_name) . "!",
                'farmer_name' => $farmer_name,
                'farmer_email' => $farmer_email,
                'request_id' => $request_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error assigning request to farmer."]);
        }
        
        $stmt->close();
        exit();
    }
    
    // Handle reject with reason
    if ($_POST['action'] == 'reject') {
        $request_id = (int)$_POST['request_id'];
        $csrf_token = $_POST['csrf_token'];
        $reject_reason = trim($_POST['reject_reason']);
        
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            $_SESSION['message'] = "Security token invalid. Please try again.";
            $_SESSION['message_type'] = 'danger';
            header("Location: manage_requests.php");
            exit();
        }
        
        // Validate reject reason
        if (empty($reject_reason)) {
            $_SESSION['message'] = "Please provide a reason for rejection.";
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
        
        // Update request status to Rejected with reason
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Rejected', admin_notes = ?, updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("si", $reject_reason, $request_id);
        
        if ($stmt->execute()) {
            // Log the rejection
            $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
            $admin_id = $_SESSION['admin_id'];
            $old_status = $request_data['status'];
            $new_status = 'Rejected';
            $notes = "Rejected with reason: " . $reject_reason;
            $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Notify customer
            notifyCustomer($conn, $request_id, 'Rejected', '', $reject_reason);
            
            $_SESSION['message'] = "Request rejected! Customer has been notified with your reason.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error rejecting request";
            $_SESSION['message_type'] = 'danger';
        }
        
        header("Location: manage_requests.php");
        exit();
    }
    
    // ========== ADDED: Handle review action with POST ==========
    if ($_POST['action'] == 'review') {
        $request_id = (int)$_POST['request_id'];
        $csrf_token = $_POST['csrf_token'];
        
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            echo json_encode(['success' => false, 'message' => "Security token invalid. Please try again."]);
            exit();
        }
        
        // Check if request exists
        $check_stmt = $conn->prepare("SELECT status FROM product_requests WHERE request_id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Request not found"]);
            exit();
        }
        
        $request_data = $check_result->fetch_assoc();
        
        // Update request status to Reviewed
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Reviewed', updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        
        if ($stmt->execute()) {
            // Log the review
            $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
            $admin_id = $_SESSION['admin_id'];
            $old_status = $request_data['status'];
            $new_status = 'Reviewed';
            $notes = "Marked as reviewed by admin";
            $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
            echo json_encode(['success' => true, 'message' => "Request marked as reviewed!"]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error updating request"]);
        }
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
    $allowed_actions = ['view', 'approve', 'delete', 'complete'];
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
                // Log the assignment
                $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
                $admin_id = $_SESSION['admin_id'];
                $old_status = $current_status;
                $new_status = 'Approved';
                $notes = "Assigned to farmer: " . $farmer_name . " (ID: " . $assigned_farmer_id . ")";
                $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
                $log_stmt->execute();
                $log_stmt->close();
                
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
                // Log the approval
                $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
                $admin_id = $_SESSION['admin_id'];
                $old_status = $current_status;
                $new_status = 'Approved';
                $notes = "Approved without farmer assignment";
                $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
                $log_stmt->execute();
                $log_stmt->close();
                
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
    elseif ($action == 'complete') {
        $stmt = $conn->prepare("UPDATE product_requests SET status = 'Completed', updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            // Log the completion
            $log_stmt = $conn->prepare("INSERT INTO request_history (request_id, changed_by_admin, old_status, new_status, notes) VALUES (?, ?, ?, ?, ?)");
            $admin_id = $_SESSION['admin_id'];
            $old_status = $current_status;
            $new_status = 'Completed';
            $notes = "Marked as completed by admin";
            $log_stmt->bind_param("iisss", $request_id, $admin_id, $old_status, $new_status, $notes);
            $log_stmt->execute();
            $log_stmt->close();
            
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
        // Check if admin has permission to delete (only super_admin)
        if ($admin_role !== 'super_admin') {
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

// ========== GET CURRENT TAB ==========
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'new';

// ========== FILTER PARAMETERS ==========
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ========== BUILD QUERY BASED ON TAB ==========
$query = "SELECT pr.*, 
                 u.name as customer_name, 
                 u.email as customer_email,
                 f.name as farmer_name,
                 f.email as farmer_email
          FROM product_requests pr 
          JOIN users u ON pr.customer_id = u.user_id 
          LEFT JOIN users f ON pr.assigned_farmer_id = f.user_id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM product_requests pr WHERE 1=1";

$params = [];
$count_params = [];
$param_types = "";
$count_param_types = "";

// ========== ADD TAB FILTERS ==========
if ($current_tab == 'new') {
    // New requests: Pending and not assigned
    $query .= " AND pr.status IN ('Pending', 'Reviewed') AND (pr.assigned_farmer_id IS NULL OR pr.assigned_farmer_id = 0)";
    $count_query .= " AND pr.status IN ('Pending', 'Reviewed') AND (pr.assigned_farmer_id IS NULL OR pr.assigned_farmer_id = 0)";
} elseif ($current_tab == 'assigned') {
    // Assigned requests: Approved, Accepted, Completed, Rejected with farmer assigned
    $query .= " AND pr.status IN ('Approved', 'Accepted', 'Completed', 'Rejected') AND pr.assigned_farmer_id IS NOT NULL AND pr.assigned_farmer_id > 0";
    $count_query .= " AND pr.status IN ('Approved', 'Accepted', 'Completed', 'Rejected') AND pr.assigned_farmer_id IS NOT NULL AND pr.assigned_farmer_id > 0";
} elseif ($current_tab == 'all') {
    // All requests - no additional filter
}

// Add status filter if provided
if (!empty($status_filter) && in_array($status_filter, ['Pending', 'Reviewed', 'Approved', 'Rejected', 'Completed', 'Accepted'])) {
    $query .= " AND pr.status = ?";
    $count_query .= " AND pr.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
    $param_types .= "s";
    $count_param_types .= "s";
}

// Add search filter if provided
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
if (!empty($count_params)) {
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
if (!empty($params)) {
    $requests_stmt->bind_param($param_types, ...$params);
}
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Get counts for all statuses
function getRequestCount($conn, $status, $tab = null) {
    $query = "SELECT COUNT(*) as count FROM product_requests WHERE status = ?";
    $params = [$status];
    $types = "s";
    
    if ($tab == 'new') {
        $query .= " AND (assigned_farmer_id IS NULL OR assigned_farmer_id = 0)";
    } elseif ($tab == 'assigned') {
        $query .= " AND assigned_farmer_id IS NOT NULL AND assigned_farmer_id > 0";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

// Get counts for each tab
$new_count = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE status IN ('Pending', 'Reviewed') AND (assigned_farmer_id IS NULL OR assigned_farmer_id = 0)")->fetch_assoc()['count'];
$assigned_count = $conn->query("SELECT COUNT(*) as count FROM product_requests WHERE status IN ('Approved', 'Accepted', 'Completed', 'Rejected') AND assigned_farmer_id IS NOT NULL AND assigned_farmer_id > 0")->fetch_assoc()['count'];

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
            --accepted: #9b59b6;
            --rejected: #e74c3c;
            --completed: #2ecc71;
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
        .request-card.Accepted { border-left: 4px solid var(--accepted); }
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
        .badge-Accepted { background: rgba(155, 89, 182, 0.15); color: var(--accepted); }
        .badge-Rejected { background: rgba(231, 76, 60, 0.15); color: var(--rejected); }
        .badge-Completed { background: rgba(46, 204, 113, 0.15); color: var(--completed); }
        
        /* Tab Navigation */
        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #666;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            margin-right: 5px;
        }
        
        .nav-tabs-custom .nav-link:hover {
            color: var(--spice-red);
            background: rgba(184, 92, 56, 0.05);
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--spice-red);
            border-bottom: 3px solid var(--spice-red);
            background: transparent;
        }
        
        .nav-tabs-custom .nav-link i {
            margin-right: 8px;
        }
        
        .nav-tabs-custom .badge {
            margin-left: 8px;
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
        
        .farmer-info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            border-left: 3px solid var(--spice-green);
        }
        
        .farmer-update-badge {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #2980b9;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            margin-left: 5px;
        }
        
        .farmer-update-box {
            background: #f0f9ff;
            border-left: 3px solid var(--spice-blue);
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .farmer-update-box .update-header {
            color: var(--spice-blue);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .farmer-update-box .update-content {
            color: #2c3e50;
            white-space: pre-wrap;
        }
        
        .farmer-update-box .update-time {
            font-size: 0.7rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        /* Review Modal Styles */
        .review-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .review-modal .modal-header {
            background: linear-gradient(135deg, var(--spice-blue), #2980b9);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 20px;
        }
        
        .review-modal .modal-body {
            padding: 25px;
        }
        
        .review-detail-item {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .review-detail-label {
            width: 120px;
            font-weight: 600;
            color: var(--spice-dark);
        }
        
        .review-detail-value {
            flex: 1;
            color: #2c3e50;
        }
        
        .review-detail-value.highlight {
            color: var(--spice-red);
            font-weight: 600;
        }
        
        /* Confirmation Modal Styles */
        .confirm-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .confirm-modal .modal-header {
            background: linear-gradient(135deg, var(--spice-red), #d35400);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 20px;
        }
        
        .confirm-modal .modal-header.warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .confirm-modal .modal-header.danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .confirm-modal .modal-header.success {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .confirm-modal .modal-header.info {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .confirm-modal .modal-body {
            padding: 25px;
            text-align: center;
        }
        
        .confirm-modal .modal-body i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .confirm-modal .modal-body i.warning-icon {
            color: #f39c12;
        }
        
        .confirm-modal .modal-body i.danger-icon {
            color: #e74c3c;
        }
        
        .confirm-modal .modal-body i.success-icon {
            color: #27ae60;
        }
        
        .confirm-modal .modal-body h5 {
            color: var(--spice-dark);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .confirm-modal .modal-body p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .confirm-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 15px 25px;
            justify-content: center;
        }
        
        .confirm-modal .btn {
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            min-width: 120px;
        }
        
        .confirm-modal .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .confirm-modal .btn-cancel {
            background: #e9ecef;
            color: #7f8c8d;
        }
        
        .confirm-modal .btn-cancel:hover {
            background: #dee2e6;
        }
        
        .confirm-modal .btn-confirm {
            background: var(--spice-green);
            color: white;
        }
        
        .confirm-modal .btn-confirm:hover {
            background: #219653;
        }
        
        .confirm-modal .btn-confirm.warning {
            background: #f39c12;
        }
        
        .confirm-modal .btn-confirm.warning:hover {
            background: #e67e22;
        }
        
        .confirm-modal .btn-confirm.danger {
            background: #e74c3c;
        }
        
        .confirm-modal .btn-confirm.danger:hover {
            background: #c0392b;
        }
        
        .reject-reason-textarea {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            margin-top: 15px;
            resize: vertical;
        }
        
        .reject-reason-textarea:focus {
            border-color: var(--spice-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(184, 92, 56, 0.1);
        }
        
        .reject-reason-box {
            background: #fee9e7;
            border-left: 3px solid var(--rejected);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .reject-reason-box .reason-header {
            color: var(--rejected);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .reject-reason-box .reason-content {
            color: #2c3e50;
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
                            <i class="fas fa-inbox me-1"></i> Total: <?php echo number_format($new_count + $assigned_count); ?>
                            <?php if($new_count > 0): ?>
                            <span class="notification-badge" title="New requests"><?php echo $new_count; ?></span>
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
                    <?php if(!empty($last_notification['reason'])): ?>
                        <br><strong>Reason:</strong> <?php echo htmlspecialchars($last_notification['reason']); ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- ========== TAB NAVIGATION ========== -->
                <ul class="nav nav-tabs-custom" id="requestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $current_tab == 'new' ? 'active' : ''; ?>" href="?tab=new">
                            <i class="fas fa-inbox"></i> New Requests
                            <?php if($new_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $new_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $current_tab == 'assigned' ? 'active' : ''; ?>" href="?tab=assigned">
                            <i class="fas fa-user-tie"></i> Assigned & Updates
                            <?php if($assigned_count > 0): ?>
                                <span class="badge bg-info"><?php echo $assigned_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $current_tab == 'all' ? 'active' : ''; ?>" href="?tab=all">
                            <i class="fas fa-list"></i> All Requests
                        </a>
                    </li>
                </ul>

                <!-- ========== FILTERS ========== -->
                <div class="filter-card">
                    <div class="row">
                        <div class="col-md-12">
                            <form method="GET" action="manage_requests.php" class="row g-3" id="filterForm">
                                <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
                                <div class="col-md-5">
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
                                        <option value="">All Statuses</option>
                                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Reviewed" <?php echo $status_filter == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Accepted" <?php echo $status_filter == 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="submit" class="btn btn-primary flex-grow-1">
                                            <i class="fas fa-filter me-1"></i> Apply
                                        </button>
                                        <a href="manage_requests.php?tab=<?php echo $current_tab; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ========== REQUESTS LIST ========== -->
                <div class="analytics-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2" style="color: var(--spice-red);"></i>
                            <?php 
                            if($current_tab == 'new') echo 'New Requests';
                            elseif($current_tab == 'assigned') echo 'Assigned & Updated Requests';
                            else echo 'All Requests';
                            ?> 
                            (<?php echo $total_requests; ?>)
                        </h5>
                        <div class="text-muted small">
                            <i class="fas fa-user-tie me-1"></i>
                            <?php echo $farmers_result->num_rows; ?> active farmers available
                        </div>
                    </div>
                    
                    <?php if($requests_result->num_rows > 0): ?>
                        <?php while($request = $requests_result->fetch_assoc()): 
                            // Parse admin_notes to extract latest farmer update
                            $latest_farmer_update = '';
                            if (!empty($request['admin_notes']) && strpos($request['admin_notes'], '[Farmer Update:') !== false) {
                                $notes = $request['admin_notes'];
                                $lines = explode("\n", $notes);
                                foreach ($lines as $line) {
                                    if (strpos($line, '[Farmer Update:') !== false) {
                                        $latest_farmer_update = $line;
                                    }
                                }
                            }
                            
                            $reject_reason = '';
                            if ($request['status'] == 'Rejected' && !empty($request['admin_notes']) && strpos($request['admin_notes'], '[Farmer Update:') === false) {
                                $reject_reason = $request['admin_notes'];
                            }
                        ?>
                        <div class="request-card <?php echo $request['status']; ?>" id="request-<?php echo $request['request_id']; ?>">
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
                                            <?php if($request['status'] == 'Accepted'): ?>
                                            <span class="farmer-update-badge">
                                                <i class="fas fa-check-circle"></i> Accepted
                                            </span>
                                            <?php endif; ?>
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
                                        
                                        <?php if(!empty($latest_farmer_update)): ?>
                                        <div class="farmer-update-box mt-2">
                                            <div class="update-header">
                                                <i class="fas fa-user-edit me-1"></i> Latest Farmer Update
                                            </div>
                                            <div class="update-content">
                                                <?php echo htmlspecialchars($latest_farmer_update); ?>
                                            </div>
                                            <div class="update-time">
                                                <i class="far fa-clock me-1"></i> Latest update
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($reject_reason)): ?>
                                        <div class="reject-reason-box mt-2">
                                            <div class="reason-header">
                                                <i class="fas fa-times-circle me-1"></i> Rejection Reason
                                            </div>
                                            <div class="reason-content">
                                                <?php echo htmlspecialchars($reject_reason); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($request['farmer_name'])): ?>
                                        <div class="farmer-info-box mt-2" id="farmerInfo-<?php echo $request['request_id']; ?>">
                                            <strong><i class="fas fa-user-tie me-1"></i> Assigned Farmer:</strong><br>
                                            <?php echo htmlspecialchars($request['farmer_name']); ?>
                                            <?php if(!empty($request['farmer_email'])): ?>
                                                <br><small><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($request['farmer_email']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-2">
                                    <span class="status-badge badge-<?php echo $request['status']; ?>" id="statusBadge-<?php echo $request['request_id']; ?>">
                                        <?php if($request['status'] == 'Pending'): ?>
                                            <i class="fas fa-clock me-1"></i> Pending
                                        <?php elseif($request['status'] == 'Reviewed'): ?>
                                            <i class="fas fa-eye me-1"></i> Reviewed
                                        <?php elseif($request['status'] == 'Approved'): ?>
                                            <i class="fas fa-check me-1"></i> Approved
                                        <?php elseif($request['status'] == 'Accepted'): ?>
                                            <i class="fas fa-handshake me-1"></i> Accepted
                                        <?php elseif($request['status'] == 'Completed'): ?>
                                            <i class="fas fa-check-double me-1"></i> Completed
                                        <?php else: ?>
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        <?php endif; ?>
                                    </span>
                                    <?php if(!empty($latest_farmer_update)): ?>
                                    <div class="mt-1">
                                        <small class="text-primary">
                                            <i class="fas fa-sync-alt fa-spin me-1"></i> Farmer updated
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-3">
                                    <div class="action-buttons d-flex flex-wrap justify-content-end" id="actions-<?php echo $request['request_id']; ?>">
                                        <?php if($request['status'] == 'Pending'): ?>
                                            <!-- ========== UPDATED: Review button opens modal ========== -->
                                            <button type="button" class="btn btn-info btn-sm me-1 btn-review-modal" 
                                                    data-request-id="<?php echo $request['request_id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($request['product_name']); ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($request['customer_name']); ?>"
                                                    data-quantity="<?php echo $request['quantity_requested']; ?>"
                                                    data-urgency="<?php echo $request['urgency']; ?>"
                                                    data-description="<?php echo htmlspecialchars($request['description']); ?>"
                                                    data-created="<?php echo date('M d, Y', strtotime($request['created_at'])); ?>">
                                                <i class="fas fa-eye"></i> Review
                                            </button>
                                            
                                            <!-- Assign & Approve button -->
                                            <button class="btn btn-success btn-sm me-1 btn-assign-farmer" 
                                                    data-request-id="<?php echo $request['request_id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($request['product_name']); ?>">
                                                <i class="fas fa-user-tie me-1"></i> Assign & Approve
                                            </button>
                                            
                                            <!-- Direct approve without assignment -->
                                            <button type="button" class="btn btn-outline-success btn-sm me-1 btn-approve" 
                                                    onclick="showConfirmModal('approve', <?php echo $request['request_id']; ?>, 'Approve this request without assigning to a farmer? Customer will be notified.', 'warning')">
                                                <i class="fas fa-check-circle"></i> Direct Approve
                                            </button>
                                            
                                            <!-- Reject button with reason modal -->
                                            <button type="button" class="btn btn-danger btn-sm btn-reject" 
                                                    onclick="showRejectModal(<?php echo $request['request_id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            
                                        <?php elseif($request['status'] == 'Reviewed'): ?>
                                            <!-- Assign & Approve button -->
                                            <button class="btn btn-success btn-sm me-1 btn-assign-farmer" 
                                                    data-request-id="<?php echo $request['request_id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($request['product_name']); ?>">
                                                <i class="fas fa-user-tie me-1"></i> Assign & Approve
                                            </button>
                                            
                                            <!-- Direct approve without assignment -->
                                            <button type="button" class="btn btn-outline-success btn-sm me-1 btn-approve" 
                                                    onclick="showConfirmModal('approve', <?php echo $request['request_id']; ?>, 'Approve this request without assigning to a farmer? Customer will be notified.', 'warning')">
                                                <i class="fas fa-check-circle"></i> Direct Approve
                                            </button>
                                            
                                            <!-- Reject button with reason modal -->
                                            <button type="button" class="btn btn-danger btn-sm btn-reject" 
                                                    onclick="showRejectModal(<?php echo $request['request_id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            
                                        <?php elseif($request['status'] == 'Approved' || $request['status'] == 'Accepted'): ?>
                                            <?php if($request['status'] == 'Accepted'): ?>
                                            <span class="badge bg-success me-1 p-2">
                                                <i class="fas fa-check-circle"></i> Farmer Accepted
                                            </span>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-secondary btn-sm me-1 btn-complete" 
                                                    onclick="showConfirmModal('complete', <?php echo $request['request_id']; ?>, 'Mark this request as completed? Customer will be notified.', 'success')">
                                                <i class="fas fa-check-double"></i> Complete
                                            </button>
                                            
                                            <!-- Reassign button if farmer hasn't accepted yet -->
                                            <?php if(empty($request['farmer_name']) && $request['status'] == 'Approved'): ?>
                                                <button class="btn btn-warning btn-sm me-1 btn-assign-farmer" 
                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($request['product_name']); ?>">
                                                    <i class="fas fa-sync-alt me-1"></i> Reassign
                                                </button>
                                            <?php endif; ?>
                                            
                                        <?php endif; ?>
                                        
                                        <?php if($admin_role === 'super_admin'): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-delete" 
                                                    onclick="showConfirmModal('delete', <?php echo $request['request_id']; ?>, 'Delete this request permanently? This cannot be undone.', 'danger')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if(!empty($request['assigned_farmer_id'])): ?>
                                        <div class="small text-muted mt-1 text-end">
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
                                    echo "No requests in this category.";
                                }
                                ?>
                            </p>
                            <a href="manage_requests.php?tab=<?php echo $current_tab; ?>" class="btn btn-primary">
                                <i class="fas fa-redo me-1"></i> Refresh
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="manage_requests.php?page=<?php echo $page - 1; ?>&tab=<?php echo $current_tab; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left me-1"></i> Previous
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_requests.php?page=<?php echo $i; ?>&tab=<?php echo $current_tab; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
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
                            <a class="page-link" href="manage_requests.php?page=<?php echo $page + 1; ?>&tab=<?php echo $current_tab; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========== REVIEW MODAL ========== -->
    <div class="modal fade review-modal" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i> Review Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="review-detail-item">
                        <span class="review-detail-label">Product:</span>
                        <span class="review-detail-value highlight" id="reviewProductName"></span>
                    </div>
                    <div class="review-detail-item">
                        <span class="review-detail-label">Customer:</span>
                        <span class="review-detail-value" id="reviewCustomerName"></span>
                    </div>
                    <div class="review-detail-item">
                        <span class="review-detail-label">Requested On:</span>
                        <span class="review-detail-value" id="reviewCreatedDate"></span>
                    </div>
                    <div class="review-detail-item">
                        <span class="review-detail-label">Quantity:</span>
                        <span class="review-detail-value" id="reviewQuantity"></span>
                    </div>
                    <div class="review-detail-item">
                        <span class="review-detail-label">Urgency:</span>
                        <span class="review-detail-value" id="reviewUrgency"></span>
                    </div>
                    <div class="review-detail-item">
                        <span class="review-detail-label">Description:</span>
                        <span class="review-detail-value" id="reviewDescription"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-info" id="confirmReviewBtn">
                        <i class="fas fa-check me-1"></i> Mark as Reviewed
                    </button>
                </div>
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
                <form id="assignFarmerForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="request_id" id="assignRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Farmer</label>
                            <select class="form-select" name="farmer_id" id="farmerSelect" required>
                                <option value="">-- Select a farmer --</option>
                                <?php
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

    <!-- Confirmation Modal -->
    <div class="modal fade confirm-modal" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="confirmModalHeader">
                    <h5 class="modal-title" id="confirmModalTitle">
                        <i class="fas fa-question-circle me-2"></i> Confirm Action
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="" id="confirmModalIcon"></i>
                    <h5 id="confirmModalMessage"></h5>
                    <p id="confirmModalDetail"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-confirm" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div class="modal fade confirm-modal" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header danger">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> Reject Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_requests.php" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" id="rejectRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <i class="fas fa-times-circle danger-icon"></i>
                        <h5>Please provide a reason for rejection</h5>
                        <p>This reason will be sent to the customer</p>
                        
                        <textarea name="reject_reason" class="reject-reason-textarea" rows="4" placeholder="Enter rejection reason..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-confirm danger">Submit Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store current action data
        var currentAction = '';
        var currentRequestId = 0;
        
        // Auto-dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // ========== REVIEW MODAL HANDLER ==========
            $('.btn-review-modal').click(function() {
                var requestId = $(this).data('request-id');
                var productName = $(this).data('product-name');
                var customerName = $(this).data('customer-name');
                var quantity = $(this).data('quantity');
                var urgency = $(this).data('urgency');
                var description = $(this).data('description');
                var createdDate = $(this).data('created');
                
                $('#reviewProductName').text(productName);
                $('#reviewCustomerName').text(customerName);
                $('#reviewQuantity').text(quantity + ' kg');
                $('#reviewUrgency').text(urgency);
                $('#reviewDescription').text(description || 'No description provided');
                $('#reviewCreatedDate').text(createdDate);
                
                // Set urgency color
                var urgencySpan = $('#reviewUrgency');
                urgencySpan.removeClass('text-danger text-warning text-success');
                if(urgency == 'High') urgencySpan.addClass('text-danger fw-bold');
                else if(urgency == 'Medium') urgencySpan.addClass('text-warning fw-bold');
                else urgencySpan.addClass('text-success');
                
                // Set confirm button action
                $('#confirmReviewBtn').off('click').on('click', function() {
                    submitReview(requestId);
                });
                
                $('#reviewModal').modal('show');
            });
            
            // Submit review via AJAX
            function submitReview(requestId) {
                var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'review',
                        request_id: requestId,
                        csrf_token: csrfToken
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#reviewModal').modal('hide');
                        if (response.success) {
                            showNotification('success', response.message);
                            // Reload page after short delay to show updated status
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotification('danger', response.message);
                        }
                    },
                    error: function() {
                        $('#reviewModal').modal('hide');
                        showNotification('danger', 'Error processing request');
                    }
                });
            }
            
            // Farmer assignment modal
            $('.btn-assign-farmer').click(function() {
                var requestId = $(this).data('request-id');
                $('#assignRequestId').val(requestId);
                $('#assignFarmerModal').modal('show');
            });
            
            // Handle form submission with AJAX
            $('#assignFarmerForm').submit(function(e) {
                e.preventDefault();
                
                var farmerSelect = $('#farmerSelect').val();
                if (!farmerSelect) {
                    alert('Please select a farmer to assign this request to.');
                    return false;
                }
                
                // Show loading state
                var submitBtn = $(this).find('button[type="submit"]');
                var originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Assigning...');
                submitBtn.prop('disabled', true);
                
                // Get form data
                var formData = $(this).serialize();
                var requestId = $('#assignRequestId').val();
                
                // Send AJAX request
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateRequestCard(requestId, response.farmer_name, response.farmer_email);
                            showNotification('success', response.message);
                            $('#assignFarmerModal').modal('hide');
                            $('#farmerSelect').val('');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotification('danger', response.message);
                        }
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        showNotification('danger', 'Error: ' + error);
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                    }
                });
            });
        });
        
        // Show confirmation modal
        function showConfirmModal(action, requestId, message, type = 'warning') {
            currentAction = action;
            currentRequestId = requestId;
            
            var modal = $('#confirmModal');
            var header = $('#confirmModalHeader');
            var icon = $('#confirmModalIcon');
            var title = $('#confirmModalTitle');
            var msg = $('#confirmModalMessage');
            var detail = $('#confirmModalDetail');
            var confirmBtn = $('#confirmActionBtn');
            
            // Set icon and colors based on type
            if (type === 'danger') {
                header.removeClass().addClass('modal-header danger');
                icon.removeClass().addClass('fas fa-exclamation-triangle danger-icon');
                confirmBtn.removeClass().addClass('btn btn-confirm danger');
            } else if (type === 'success') {
                header.removeClass().addClass('modal-header success');
                icon.removeClass().addClass('fas fa-check-circle success-icon');
                confirmBtn.removeClass().addClass('btn btn-confirm');
            } else if (type === 'info') {
                header.removeClass().addClass('modal-header info');
                icon.removeClass().addClass('fas fa-info-circle');
                confirmBtn.removeClass().addClass('btn btn-confirm info');
            } else {
                header.removeClass().addClass('modal-header warning');
                icon.removeClass().addClass('fas fa-exclamation-triangle warning-icon');
                confirmBtn.removeClass().addClass('btn btn-confirm warning');
            }
            
            title.html('<i class="fas fa-question-circle me-2"></i> Confirm ' + action.charAt(0).toUpperCase() + action.slice(1));
            msg.html(message);
            
            if (action === 'delete') {
                detail.html('This action cannot be undone.');
            } else {
                detail.html('');
            }
            
            confirmBtn.off('click').on('click', function() {
                executeAction(action, requestId);
            });
            
            modal.modal('show');
        }
        
        // Show reject modal
        function showRejectModal(requestId) {
            $('#rejectRequestId').val(requestId);
            $('#rejectModal').modal('show');
        }
        
        // Execute action
        function executeAction(action, requestId) {
            var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
            var currentTab = '<?php echo $current_tab; ?>';
            var url = 'manage_requests.php?action=' + action + '&id=' + requestId + '&csrf_token=' + csrfToken + '&tab=' + currentTab;
            
            if (action === 'approve' || action === 'complete' || action === 'delete') {
                window.location.href = url;
            }
        }
        
        function updateRequestCard(requestId, farmerName, farmerEmail) {
            var requestCard = $('#request-' + requestId);
            
            // Update status to Approved
            $('#statusBadge-' + requestId).html('<i class="fas fa-check me-1"></i> Approved');
            $('#statusBadge-' + requestId).removeClass('badge-Pending badge-Reviewed').addClass('badge-Approved');
            
            // Update card class
            requestCard.removeClass('Pending Reviewed').addClass('Approved');
            
            // Add farmer badge if not already there
            if (farmerName && !$('#request-' + requestId + ' .farmer-badge').length) {
                $('#request-' + requestId + ' .col-md-3 .small').append(
                    '<div class="mt-1"><span class="farmer-badge"><i class="fas fa-user-tie me-1"></i>' + farmerName + '</span></div>'
                );
            }
            
            // Add farmer info box
            if (farmerName && farmerEmail) {
                $('#request-' + requestId + ' .col-md-3 .small').append(
                    '<div class="farmer-info-box mt-2" id="farmerInfo-' + requestId + '">' +
                    '<strong><i class="fas fa-user-tie me-1"></i> Assigned Farmer:</strong><br>' +
                    farmerName + '<br><small><i class="fas fa-envelope me-1"></i> ' + farmerEmail + '</small>' +
                    '</div>'
                );
            }
        }
        
        function showNotification(type, message) {
            $('.alert-dismissible').remove();
            
            var alertClass = 'alert-' + type;
            var icon = type == 'success' ? 'check-circle' : 
                      type == 'danger' ? 'exclamation-circle' : 
                      type == 'info' ? 'info-circle' : 'exclamation-circle';
            
            var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show mb-4" role="alert">' +
                '<i class="fas fa-' + icon + ' me-2"></i> ' + message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>';
            
            $('.dashboard-header').after(alertHtml);
            
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>
</html>