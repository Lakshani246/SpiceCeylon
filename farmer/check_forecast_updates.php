<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    exit(json_encode(['updated' => false]));
}

$farmer_id = $_SESSION['user_id'];

// Check if any forecast data was updated in the last hour
$check_query = "
    SELECT COUNT(*) as count 
    FROM forecast_data fd
    JOIN products p ON fd.product_id = p.product_id
    WHERE p.farmer_id = ?
    AND fd.generated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $farmer_id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();

echo json_encode(['updated' => $result['count'] > 0]);
?>