<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../config/db.php';

$pending_count = getRequestCount($conn, 'Pending');
$reviewed_count = getRequestCount($conn, 'Reviewed');
$approved_count = getRequestCount($conn, 'Approved');
$rejected_count = getRequestCount($conn, 'Rejected');
$completed_count = getRequestCount($conn, 'Completed');
$total_all = $pending_count + $reviewed_count + $approved_count + $rejected_count + $completed_count;

function getRequestCount($conn, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_requests WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

echo json_encode([
    'success' => true,
    'pending' => $pending_count,
    'reviewed' => $reviewed_count,
    'approved' => $approved_count,
    'rejected' => $rejected_count,
    'completed' => $completed_count,
    'total' => $total_all
]);