<?php
// customer/add_to_cart.php - WITH DEBUG
session_start();

// Debug output
error_log("=== ADD TO CART DEBUG ===");
error_log("Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("POST Data: " . print_r($_POST, true));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    error_log("User not logged in or not customer");
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    error_log("Processing: User $user_id, Product $product_id, Quantity $quantity");
    
    // Check if product exists and is approved
    $product_check = $conn->query("SELECT * FROM products WHERE product_id = '$product_id' AND status = 'Approved'");
    error_log("Product check result: " . $product_check->num_rows . " rows");
    
    if ($product_check->num_rows > 0) {
        $product = $product_check->fetch_assoc();
        error_log("Product found: " . $product['name']);
        
        // Check if item already in cart
        $cart_check = $conn->query("SELECT * FROM cart WHERE customer_id = '$user_id' AND product_id = '$product_id'");
        error_log("Cart check result: " . $cart_check->num_rows . " rows");
        
        if ($cart_check->num_rows > 0) {
            // Update quantity
            $cart_item = $cart_check->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + $quantity;
            $update_result = $conn->query("UPDATE cart SET quantity = '$new_quantity' WHERE cart_id = '{$cart_item['cart_id']}'");
            error_log("Update result: " . ($update_result ? "SUCCESS" : "FAILED"));
            $_SESSION['message'] = "Quantity updated in cart!";
        } else {
            // Add new item to cart
            $insert_result = $conn->query("INSERT INTO cart (customer_id, product_id, quantity) VALUES ('$user_id', '$product_id', '$quantity')");
            error_log("Insert result: " . ($insert_result ? "SUCCESS" : "FAILED - " . $conn->error));
            $_SESSION['message'] = "Product added to cart successfully!";
        }
    } else {
        error_log("Product not found or not approved");
        $_SESSION['error'] = "Product not available!";
    }
    
    error_log("Redirecting to: " . $_SERVER['HTTP_REFERER']);
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: home.php");
    exit;
}
?>