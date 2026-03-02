<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../config/db.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';

if (!$user_id || !$user_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

if ($user_type == 'farmers' || $user_type == 'customers') {
    // For customers, calculate total spent correctly from completed orders
    if ($user_type == 'customers') {
        $query = "SELECT u.*, 
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id) as total_products,
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND admin_approved = 'approved') as approved_products,
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND admin_approved = 'pending') as pending_products,
                         (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id) as total_orders,
                         (SELECT COALESCE(SUM(final_total), 0) FROM orders WHERE customer_id = u.user_id AND status = 'completed') as total_spent
                  FROM users u 
                  WHERE u.user_id = ?";
    } else {
        // For farmers
        $query = "SELECT u.*, 
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id) as total_products,
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND admin_approved = 'approved') as approved_products,
                         (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND admin_approved = 'pending') as pending_products,
                         (SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE p.farmer_id = u.user_id) as total_orders
                  FROM users u 
                  WHERE u.user_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} else {
    $query = "SELECT * FROM admins WHERE admin_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Format the data for display
    if ($user_type == 'farmers' || $user_type == 'customers') {
        // Fix profile image path
        if (!empty($user['profile_image']) && $user['profile_image'] != 'default-avatar.jpg') {
            // Check if the image path already contains the full path
            if (strpos($user['profile_image'], 'profile_images/') !== false) {
                $user['profile_image_url'] = '../assets/images/' . $user['profile_image'];
            } else {
                $user['profile_image_url'] = '../assets/images/profile_images/' . $user['profile_image'];
            }
        } else {
            $user['profile_image_url'] = '../assets/images/default-avatar.jpg';
        }
        
        // Format dates
        $user['joined_date'] = date('F j, Y', strtotime($user['created_at']));
        
        // Format status for display
        $user['status_display'] = ucfirst($user['status']);
        
        // Ensure total_spent is set for customers
        if ($user_type == 'customers' && !isset($user['total_spent'])) {
            $user['total_spent'] = 0;
        }
    } else {
        // For admins
        if (!empty($user['avatar'])) {
            // Check if avatar path already contains the full path
            if (strpos($user['avatar'], 'profile_images/') !== false) {
                $user['profile_image_url'] = '../assets/images/' . $user['avatar'];
            } else {
                $user['profile_image_url'] = '../assets/images/profile_images/' . $user['avatar'];
            }
        } else {
            $user['profile_image_url'] = '../assets/images/default-avatar.jpg';
        }
        $user['joined_date'] = date('F j, Y', strtotime($user['created_at']));
        $user['status_display'] = ucfirst($user['status'] ?? 'active');
    }
    
    echo json_encode(['success' => true, 'data' => $user]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$conn->close();
?>