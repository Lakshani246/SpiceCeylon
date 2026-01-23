<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['count' => 0]);
    exit();
}

$customer_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'both';
$response = [];

if ($type === 'messages' || $type === 'both') {
    $messages_query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND receiver_role = 'customer' AND is_read = FALSE";
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bind_param("i", $customer_id);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    $response['messages'] = $messages_result->fetch_assoc()['count'];
}

if ($type === 'notifications' || $type === 'both') {
    $notif_query = "SELECT COUNT(DISTINCT n.notification_id) as count 
                    FROM notifications n
                    LEFT JOIN user_notification_status uns ON n.notification_id = uns.notification_id AND uns.user_id = ?
                    WHERE (n.target_roles = 'all' OR n.target_roles = 'customers')
                    AND (uns.is_read IS NULL OR uns.is_read = FALSE)
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $customer_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    $response['notifications'] = $notif_result->fetch_assoc()['count'];
}

if ($type === 'both') {
    $response['count'] = $response['messages'] + $response['notifications'];
} else {
    $response['count'] = $response[$type] ?? 0;
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>