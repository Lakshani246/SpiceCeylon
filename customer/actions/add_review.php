<?php
// customer/actions/add_review.php
session_start();
header('Content-Type: application/json');

// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
$log_data = "=== Review Submission ===\n";
$log_data .= "Time: " . date('Y-m-d H:i:s') . "\n";
$log_data .= "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
$log_data .= "Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET') . "\n";
$log_data .= "POST data: " . print_r($_POST, true) . "\n";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    $log_data .= "ERROR: User not logged in as customer\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Get POST data
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? $_POST['comment'] : '';
$customer_id = $_SESSION['user_id'];

$log_data .= "Product ID: $product_id, Rating: $rating, Comment length: " . strlen($comment) . "\n";

// Validate data
if (!$product_id || $rating < 1 || $rating > 5 || empty(trim($comment))) {
    $log_data .= "ERROR: Invalid review data\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid review data. Please provide a rating (1-5) and review text.']);
    exit();
}

// Include database
try {
    require_once __DIR__ . '/../../config/db.php';
    $log_data .= "Database connected successfully\n";
} catch (Exception $e) {
    $log_data .= "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// Escape comment for database
$comment_escaped = $conn->real_escape_string(trim($comment));

// Check if product exists and is approved
$product_check = $conn->query("SELECT product_id FROM products WHERE product_id = '$product_id' AND admin_approved = 'approved'");
if (!$product_check) {
    $log_data .= "ERROR: Product check query failed: " . $conn->error . "\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database query error']);
    exit();
}

if ($product_check->num_rows === 0) {
    $log_data .= "ERROR: Product not found or not approved\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Product not available']);
    exit();
}

// Check if user has already reviewed this product
$review_check = $conn->query("SELECT review_id FROM reviews WHERE customer_id = '$customer_id' AND product_id = '$product_id'");
if (!$review_check) {
    $log_data .= "ERROR: Review check query failed: " . $conn->error . "\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database query error']);
    exit();
}

if ($review_check->num_rows > 0) {
    $log_data .= "ERROR: User has already reviewed this product\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
    exit();
}

// Insert review
$insert = $conn->query("INSERT INTO reviews (customer_id, product_id, rating, comment) 
              VALUES ('$customer_id', '$product_id', '$rating', '$comment_escaped')");

if (!$insert) {
    $log_data .= "ERROR: Insert query failed: " . $conn->error . "\n";
    file_put_contents('review_debug.log', $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $conn->error]);
    exit();
}

$log_data .= "SUCCESS: Review submitted successfully. Review ID: " . $conn->insert_id . "\n";
file_put_contents('review_debug.log', $log_data, FILE_APPEND);
echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
?>