<?php
// customer/actions/add_to_wishlist.php
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$customer_id = $_SESSION['user_id'];

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Include database
try {
    require_once __DIR__ . '/../../config/db.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// Check if product exists and is approved
$product_query = $conn->query("SELECT product_id FROM products WHERE product_id = '$product_id' AND admin_approved = 'approved'");
if ($product_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not available']);
    exit();
}

// Check if already in wishlist
$check_query = $conn->query("SELECT wishlist_id FROM wishlist WHERE customer_id = '$customer_id' AND product_id = '$product_id'");
if ($check_query->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
    exit();
}

// Add to wishlist
$insert = $conn->query("INSERT INTO wishlist (customer_id, product_id) VALUES ('$customer_id', '$product_id')");
if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Added to wishlist successfully']);
?>