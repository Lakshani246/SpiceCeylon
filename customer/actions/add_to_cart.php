<?php
// customer/actions/add_to_cart.php - UPDATED VERSION
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create debug log
$debug_log = "=== Add to Cart Debug ===\n";
$debug_log .= "Time: " . date('Y-m-d H:i:s') . "\n";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    $debug_log .= "ERROR: User not logged in as customer\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$debug_log .= "User ID: {$_SESSION['user_id']}, Role: {$_SESSION['role']}\n";

// Get POST data
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$package_size = isset($_POST['package_size']) ? $_POST['package_size'] : '1kg';

$debug_log .= "POST Data - Product ID: $product_id, Quantity: $quantity, Package: $package_size\n";

if (!$product_id) {
    $debug_log .= "ERROR: Invalid product ID\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Include database
try {
    require_once __DIR__ . '/../../config/db.php';
    $debug_log .= "Database connected successfully\n";
} catch (Exception $e) {
    $debug_log .= "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// Check if product exists and is approved
$product_query = $conn->query("SELECT * FROM products WHERE product_id = '$product_id' AND admin_approved = 'approved'");
if (!$product_query) {
    $debug_log .= "ERROR: Query failed: " . $conn->error . "\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database query error']);
    exit();
}

if ($product_query->num_rows === 0) {
    $debug_log .= "ERROR: Product not found or not approved\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Product not available']);
    exit();
}

$product = $product_query->fetch_assoc();
$base_price = floatval($product['price']); // Price per kg

// Calculate multiplier based on package size
$multiplier = 1; // Default for 1kg
switch($package_size) {
    case '25g': $multiplier = 0.025; break;
    case '50g': $multiplier = 0.05; break;
    case '100g': $multiplier = 0.1; break;
    case '250g': $multiplier = 0.25; break;
    case '500g': $multiplier = 0.5; break;
    case '1kg': $multiplier = 1; break;
}

// Calculate price for selected package
$unit_price = $base_price * $multiplier;
$total_price = $unit_price * $quantity;

$debug_log .= "Product: {$product['name']}, Base Price: {$base_price}, Multiplier: {$multiplier}\n";
$debug_log .= "Unit Price: {$unit_price}, Total Price: {$total_price}\n";

// Check stock (stock is in kg, so convert quantity to kg equivalent)
$stock_needed = $quantity * $multiplier;
if ($product['stock'] < $stock_needed) {
    $debug_log .= "ERROR: Insufficient stock. Available: {$product['stock']}kg, Needed: {$stock_needed}kg\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
    exit();
}

$customer_id = $_SESSION['user_id'];

// Check if cart table has package_size and total_price columns, add them if not
$check_columns = $conn->query("SHOW COLUMNS FROM cart LIKE 'package_size'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE cart ADD COLUMN package_size VARCHAR(20) DEFAULT '1kg'");
    $debug_log .= "Added package_size column to cart table\n";
}

$check_columns2 = $conn->query("SHOW COLUMNS FROM cart LIKE 'total_price'");
if ($check_columns2->num_rows == 0) {
    $conn->query("ALTER TABLE cart ADD COLUMN total_price DECIMAL(10,2)");
    $debug_log .= "Added total_price column to cart table\n";
}

// Check if item already exists in cart with same package size
$check_query = $conn->query("SELECT * FROM cart WHERE customer_id = '$customer_id' AND product_id = '$product_id' AND package_size = '$package_size'");
if (!$check_query) {
    $debug_log .= "ERROR: Cart check query failed: " . $conn->error . "\n";
    file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

if ($check_query->num_rows > 0) {
    // Update quantity for same package size
    $cart_item = $check_query->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    // Check stock again
    $new_stock_needed = $new_quantity * $multiplier;
    if ($product['stock'] < $new_stock_needed) {
        $debug_log .= "ERROR: Cannot add more than available stock\n";
        file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock']);
        exit();
    }
    
    // Recalculate total price
    $new_total_price = $unit_price * $new_quantity;
    
    // Update cart with new quantity and recalculated price
    $update = $conn->query("UPDATE cart SET quantity = '$new_quantity', price = '$unit_price', total_price = '$new_total_price', updated_at = NOW() WHERE cart_id = '{$cart_item['cart_id']}'");
    if (!$update) {
        $debug_log .= "ERROR updating cart: " . $conn->error . "\n";
        file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        exit();
    }
    $debug_log .= "Cart item updated successfully\n";
} else {
    // Insert new cart item with package size and calculated price
    $insert = $conn->query("INSERT INTO cart (customer_id, product_id, quantity, package_size, price, total_price, added_at) 
                  VALUES ('$customer_id', '$product_id', '$quantity', '$package_size', '$unit_price', '$total_price', NOW())");
    if (!$insert) {
        $debug_log .= "ERROR inserting into cart: " . $conn->error . "\n";
        file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to add to cart: ' . $conn->error]);
        exit();
    }
    $debug_log .= "New cart item added successfully\n";
}

$debug_log .= "SUCCESS: Item added to cart with package size: {$package_size}\n";
file_put_contents('cart_debug.log', $debug_log, FILE_APPEND);
echo json_encode(['success' => true, 'message' => 'Added to cart successfully', 'package_size' => $package_size]);
?>