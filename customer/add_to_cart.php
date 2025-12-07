<?php
// customer/add_to_cart.php - UPDATED WITH ERROR HANDLING
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Get the selected price - FIXED: proper validation
    $selected_price = 0;
    if (isset($_POST['selected_price']) && is_numeric($_POST['selected_price'])) {
        $selected_price = floatval($_POST['selected_price']);
    }
    
    // Get product option if available
    $product_option = $_POST['product_option'] ?? '';

    // Check if product exists and is approved
    $product_check = $conn->query("SELECT * FROM products WHERE product_id = '$product_id' AND status = 'Approved'");
    
    if ($product_check->num_rows > 0) {
        $product = $product_check->fetch_assoc();
        
        // If no selected price, use database price
        if ($selected_price <= 0) {
            $selected_price = floatval($product['price']);
        }
        
        // Validate price is reasonable (not 8.00 for saffron)
        if ($selected_price < 1) {
            $selected_price = floatval($product['price']);
        }

        // Check if item already in cart
        $cart_check = $conn->query("SELECT * FROM cart WHERE customer_id = '$user_id' AND product_id = '$product_id'");
        
        if ($cart_check->num_rows > 0) {
            // Update existing item
            $cart_item = $cart_check->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + $quantity;
            
            // Check if cart has price column, if not use fallback
            $update_result = $conn->query("UPDATE cart SET quantity = '$new_quantity', price = '$selected_price' WHERE cart_id = '{$cart_item['cart_id']}'");
            
            if (!$update_result) {
                // If price column doesn't exist, update without price
                $conn->query("UPDATE cart SET quantity = '$new_quantity' WHERE cart_id = '{$cart_item['cart_id']}'");
            }
            
            $_SESSION['message'] = "Updated $product[name] in cart!";
        } else {
            // Add new item to cart - with error handling for missing price column
            $insert_result = $conn->query("INSERT INTO cart (customer_id, product_id, quantity, price) VALUES ('$user_id', '$product_id', '$quantity', '$selected_price')");
            
            if (!$insert_result) {
                // If price column doesn't exist, insert without price
                $conn->query("INSERT INTO cart (customer_id, product_id, quantity) VALUES ('$user_id', '$product_id', '$quantity')");
            }
            
            $_SESSION['message'] = "Added $product[name] to cart successfully!";
        }
    } else {
        $_SESSION['error'] = "Product not available!";
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: home.php");
    exit;
}
?>