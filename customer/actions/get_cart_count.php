<?php
// customer/actions/get_cart_count.php
session_start();
header('Content-Type: application/json');

$count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../../config/db.php';
        $cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = '{$_SESSION['user_id']}'");
        if ($cart_query) {
            $count = $cart_query->fetch_assoc()['count'];
        }
    } catch (Exception $e) {
        // Silently fail for cart count
    }
}

echo json_encode(['count' => $count]);
?>