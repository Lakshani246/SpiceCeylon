<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Get cart items with product details
$cart_query = "
    SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.image, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.product_id 
    WHERE c.customer_id = '$user_id' AND p.status = 'Approved'
";
$cart_result = $conn->query($cart_query);

$total_amount = 0;
$cart_items = [];

if ($cart_result->num_rows > 0) {
    while($item = $cart_result->fetch_assoc()) {
        $item_total = $item['price'] * $item['quantity'];
        $total_amount += $item_total;
        $cart_items[] = $item;
    }
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $cart_id = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    
    if ($quantity <= 0) {
        // Remove item if quantity is 0 or less
        $conn->query("DELETE FROM cart WHERE cart_id = '$cart_id' AND customer_id = '$user_id'");
    } else {
        // Update quantity
        $conn->query("UPDATE cart SET quantity = '$quantity' WHERE cart_id = '$cart_id' AND customer_id = '$user_id'");
    }
    
    header("Location: cart.php");
    exit;
}

// Handle remove item
if (isset($_GET['remove'])) {
    $cart_id = $_GET['remove'];
    $conn->query("DELETE FROM cart WHERE cart_id = '$cart_id' AND customer_id = '$user_id'");
    header("Location: cart.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/cart.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="cart-title">Your Shopping Cart</h2>
                
                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart text-center">
                        <i class="fas fa-shopping-cart fa-4x mb-3"></i>
                        <h4>Your cart is empty</h4>
                        <p>Browse our spices and add some items to your cart!</p>
                        <a href="home.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <!-- Cart Items -->
                        <div class="col-lg-8">
                            <div class="cart-items">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="cart-item card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <img src="../assets/images/products/<?php echo $item['image'] ?? 'default-spice.jpg'; ?>" 
                                                         alt="<?php echo $item['name']; ?>" 
                                                         class="product-image">
                                                </div>
                                                <div class="col-md-4">
                                                    <h5 class="product-name"><?php echo $item['name']; ?></h5>
                                                    <p class="product-price">Rs. <?php echo number_format($item['price'], 2); ?></p>
                                                </div>
                                                <div class="col-md-3">
                                                    <form method="POST" class="quantity-form">
                                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                        <div class="input-group">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn minus" 
                                                                    data-cart-id="<?php echo $item['cart_id']; ?>">-</button>
                                                            <input type="number" name="quantity" 
                                                                   value="<?php echo $item['quantity']; ?>" 
                                                                   min="1" max="<?php echo $item['stock']; ?>"
                                                                   class="form-control quantity-input text-center">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn plus" 
                                                                    data-cart-id="<?php echo $item['cart_id']; ?>">+</button>
                                                        </div>
                                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-primary mt-2 update-btn">
                                                            Update
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="col-md-2">
                                                    <p class="item-total">Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                                </div>
                                                <div class="col-md-1">
                                                    <a href="cart.php?remove=<?php echo $item['cart_id']; ?>" 
                                                       class="btn btn-danger btn-sm remove-btn" 
                                                       onclick="return confirm('Remove this item from cart?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="col-lg-4">
                            <div class="card summary-card">
                                <div class="card-header">
                                    <h5>Order Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="summary-item">
                                        <span>Subtotal:</span>
                                        <span>Rs. <?php echo number_format($total_amount, 2); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span>Shipping:</span>
                                        <span>Rs. 200.00</span>
                                    </div>
                                    <div class="summary-item total">
                                        <span><strong>Total:</strong></span>
                                        <span><strong>Rs. <?php echo number_format($total_amount + 200, 2); ?></strong></span>
                                    </div>
                                    <div class="d-grid gap-2 mt-3">
                                        <a href="checkout.php" class="btn btn-primary btn-checkout">
                                            <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                        </a>
                                        <a href="home.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/cart.js"></script>
</body>
</html>