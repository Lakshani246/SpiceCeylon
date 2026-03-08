<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../config/db.php';
$farmer_id = $_SESSION['user_id'];

// Get farmer name
$farmer_query = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer_result = $farmer_query->get_result();
$farmer = $farmer_result->fetch_assoc();
$farmer_name = $farmer['name'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_order_status') {
    header('Content-Type: application/json');
    
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Verify this farmer has products in this order
    $check_query = $conn->prepare("
        SELECT COUNT(*) as count FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ? AND p.farmer_id = ?
    ");
    $check_query->bind_param("ii", $order_id, $farmer_id);
    $check_query->execute();
    $check_result = $check_query->get_result();
    $check_data = $check_result->fetch_assoc();
    $has_products = $check_data['count'] > 0;
    
    if (!$has_products) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this order']);
        exit;
    }
    
    // Get current status and customer info
    $status_query = $conn->prepare("SELECT status, customer_id FROM orders WHERE order_id = ?");
    $status_query->bind_param("i", $order_id);
    $status_query->execute();
    $status_result = $status_query->get_result();
    $order_data = $status_result->fetch_assoc();
    $current_status = $order_data['status'];
    $customer_id = $order_data['customer_id'];
    
    // Update order status
    $update_query = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
    $update_query->bind_param("si", $new_status, $order_id);
    
    if ($update_query->execute()) {
        // Log status change in history
        $history_query = $conn->prepare("
            INSERT INTO order_status_history (order_id, changed_by_admin, status, notes, changed_at) 
            VALUES (?, NULL, ?, ?, NOW())
        ");
        $notes_log = "Updated by farmer: " . $farmer_name . " - " . $notes;
        $history_query->bind_param("iss", $order_id, $new_status, $notes_log);
        $history_query->execute();
        
        // Create notification for customer
        $notify_title = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " Status Updated";
        $notify_message = "Your order status has been updated to: " . $new_status . ". " . ($notes ? "Notes: " . $notes : "");
        
        $notify_query = $conn->prepare("
            INSERT INTO notifications (title, message, target_roles, target_user_id, sender_id, sender_role) 
            VALUES (?, ?, 'specific', ?, ?, 'farmer')
        ");
        $notify_query->bind_param("ssii", $notify_title, $notify_message, $customer_id, $farmer_id);
        
        if ($notify_query->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Order status updated to ' . $new_status . '! Customer notified.'
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'Order status updated but notification failed.'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}
?>