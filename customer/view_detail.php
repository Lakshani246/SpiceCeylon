<?php
// customer/products/view_spice_template.php
session_start();
include "../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get product ID from URL or parameter
$product_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch product details
$query = "SELECT p.*, u.name as farmer_name, u.farm_location, u.phone as farmer_phone, u.email as farmer_email
          FROM products p 
          JOIN users u ON p.farmer_id = u.user_id 
          WHERE p.product_id = '$product_id' 
          AND p.admin_approved = 'approved' 
          AND p.status = 'Approved'";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    header("Location: ../home.php");
    exit();
}

$product = $result->fetch_assoc();

// Get image path
$image_path = '';
if ($product['image']) {
    $image_name = basename($product['image']);
    $locations = [
        '../../assets/images/' . $image_name,
        '../../' . $product['image'],
        '../../uploads/products/' . $image_name,
        $product['image']
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            $image_path = $location;
            break;
        }
    }
}

// Stock status
$stock_class = 'stock-in';
$stock_text = 'In Stock';
if ($product['stock'] <= 0) {
    $stock_class = 'stock-out';
    $stock_text = 'Out of Stock';
} elseif ($product['stock'] < 10) {
    $stock_class = 'stock-low';
    $stock_text = 'Low Stock';
}

// Get cart count
$cart_count = 0;
$cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = '{$_SESSION['user_id']}'");
$cart_count = $cart_query->fetch_assoc()['count'];

// Get related products
$related_query = $conn->query("SELECT * FROM products 
                              WHERE category = '{$product['category']}' 
                              AND product_id != '{$product['product_id']}' 
                              AND admin_approved = 'approved'
                              LIMIT 4");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse your home page styles */
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--spice-red) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--spice-dark) !important;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .nav-link:hover {
            color: var(--spice-red) !important;
        }
        
        .product-detail-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 30px;
        }
        
        .product-image-main {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-info-section {
            padding: 20px;
        }
        
        .product-title-large {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--spice-dark);
            margin-bottom: 10px;
        }
        
        .product-price-large {
            font-size: 2rem;
            font-weight: 700;
            color: var(--spice-red);
            margin: 20px 0;
        }
        
        .product-description-full {
            line-height: 1.8;
            color: #555;
            margin: 30px 0;
            font-size: 1.1rem;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        
        .specs-table td {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .specs-table td:first-child {
            font-weight: 600;
            color: var(--spice-dark);
            width: 200px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 70px;
            height: 40px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-left: none;
            border-right: none;
            font-size: 1rem;
        }
        
        .btn-add-cart-large {
            background: var(--spice-green);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-add-cart-large:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .btn-add-cart-large:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .farmer-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid var(--spice-green);
        }
        
        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .related-product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .breadcrumb {
            background: none;
            padding: 15px 0;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: var(--spice-blue);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            margin: 15px 0;
        }
        
        .stock-in-badge {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
        }
        
        .stock-low-badge {
            background: rgba(243, 156, 18, 0.1);
            color: var(--spice-gold);
        }
        
        .stock-out-badge {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .back-to-home {
            color: var(--spice-blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .back-to-home:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../home.php">
                <i class="fas fa-pepper-hot me-2"></i>SpiceCeylon
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="../cart.php">
                            <i class="fas fa-shopping-cart me-1"></i> Cart 
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="../orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="../request.php">Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="../about.php">About Us</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../wishlist.php"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Product Detail Section -->
    <div class="container">
        <a href="../home.php" class="back-to-home">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
        
        <div class="row product-detail-container">
            <!-- Product Images -->
            <div class="col-md-6">
                <?php if($image_path): ?>
                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-main">
                <?php else: ?>
                    <div style="height: 400px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-leaf fa-5x text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div class="col-md-6 product-info-section">
                <div class="d-flex justify-content-between align-items-start">
                    <h1 class="product-title-large"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <span style="background: rgba(184, 92, 56, 0.1); color: var(--spice-red); padding: 5px 15px; border-radius: 20px;">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                </div>
                
                <div class="stock-badge <?php echo $stock_class; ?>-badge">
                    <i class="fas fa-<?php echo $stock_class == 'stock-out' ? 'times' : ($stock_class == 'stock-low' ? 'exclamation-triangle' : 'check'); ?> me-1"></i>
                    <?php echo $stock_text; ?> (<?php echo $product['stock']; ?> available)
                </div>
                
                <div class="product-price-large">
                    Rs. <?php echo number_format($product['price'], 2); ?> /kg
                </div>
                
                <div class="product-description-full">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                
                <table class="specs-table">
                    <tr>
                        <td>Origin:</td>
                        <td>Sri Lanka</td>
                    </tr>
                    <tr>
                        <td>Quality Grade:</td>
                        <td>Premium A Grade</td>
                    </tr>
                    <tr>
                        <td>Packing:</td>
                        <td>Vacuum Sealed</td>
                    </tr>
                    <tr>
                        <td>Shelf Life:</td>
                        <td>2 Years</td>
                    </tr>
                    <tr>
                        <td>Organic:</td>
                        <td><i class="fas fa-check text-success"></i> 100% Organic</td>
                    </tr>
                </table>
                
                <!-- Quantity and Add to Cart -->
                <div class="quantity-control">
                    <label class="me-3" style="font-weight: 600;">Quantity (kg):</label>
                    <button class="quantity-btn" onclick="updateQuantity(-0.5)">-</button>
                    <input type="number" id="quantity" class="quantity-input" value="1" min="0.5" step="0.5" max="<?php echo $product['stock']; ?>">
                    <button class="quantity-btn" onclick="updateQuantity(0.5)">+</button>
                </div>
                
                <div class="d-grid gap-2">
                    <?php if($product['stock'] > 0): ?>
                        <button class="btn-add-cart-large" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart-large" disabled>
                            <i class="fas fa-times"></i> Out of Stock
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Farmer Information -->
                <div class="farmer-card">
                    <h5><i class="fas fa-tractor me-2"></i> Farmer Information</h5>
                    <div class="mt-3">
                        <p><strong>Farmer:</strong> <?php echo htmlspecialchars($product['farmer_name']); ?></p>
                        <p><strong>Farm Location:</strong> <?php echo htmlspecialchars($product['farm_location']); ?></p>
                        <?php if($product['farmer_phone']): ?>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['farmer_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if($related_query->num_rows > 0): ?>
        <div class="mt-5 pt-5">
            <h3 class="mb-4" style="color: var(--spice-dark);">You May Also Like</h3>
            <div class="related-products-grid">
                <?php while($related = $related_query->fetch_assoc()): 
                    $related_image_path = '';
                    if ($related['image']) {
                        $related_image_name = basename($related['image']);
                        $locations = [
                            '../../assets/images/' . $related_image_name,
                            '../../' . $related['image'],
                            '../../uploads/products/' . $related_image_name,
                            $related['image']
                        ];
                        
                        foreach ($locations as $location) {
                            if (file_exists($location)) {
                                $related_image_path = $location;
                                break;
                            }
                        }
                    }
                ?>
                <div class="related-product-card">
                    <a href="?id=<?php echo $related['product_id']; ?>" style="text-decoration: none; color: inherit;">
                        <?php if($related_image_path): ?>
                            <img src="<?php echo $related_image_path; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>" style="width: 100%; height: 180px; object-fit: cover;">
                        <?php else: ?>
                            <div style="height: 180px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-leaf fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div style="padding: 15px;">
                            <h6 style="font-weight: 600; margin-bottom: 10px;"><?php echo htmlspecialchars($related['name']); ?></h6>
                            <div style="color: var(--spice-red); font-weight: 700; font-size: 1.1rem;">
                                Rs. <?php echo number_format($related['price'], 2); ?>
                            </div>
                            <small style="color: #666;"><?php echo htmlspecialchars($related['category']); ?></small>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quantity Control
        function updateQuantity(change) {
            const input = document.getElementById('quantity');
            let currentValue = parseFloat(input.value);
            const max = parseFloat(input.max);
            const min = parseFloat(input.min);
            const step = parseFloat(input.step);
            
            let newValue = currentValue + change;
            
            // Round to step precision
            newValue = Math.round(newValue * (1/step)) / (1/step);
            
            // Check bounds
            if (newValue < min) newValue = min;
            if (newValue > max) newValue = max;
            
            input.value = newValue;
        }
        
        // Add to Cart Function
        function addToCart(productId) {
            const quantity = document.getElementById('quantity').value;
            
            fetch('../../actions/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '11';
                    toast.innerHTML = `
                        <div class="toast show" role="alert">
                            <div class="toast-header" style="background: #27ae60; color: white;">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.remove()"></button>
                            </div>
                            <div class="toast-body">
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong> added to cart successfully!
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    // Remove toast after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                    
                    // Update cart count
                    updateCartCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        // Update cart count
        function updateCartCount() {
            fetch('../../actions/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartBadge = document.querySelector('.nav-link[href="../cart.php"] .badge');
                    if (data.count > 0) {
                        if (cartBadge) {
                            cartBadge.textContent = data.count;
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-danger';
                            badge.textContent = data.count;
                            document.querySelector('.nav-link[href="../cart.php"]').appendChild(badge);
                        }
                    } else if (cartBadge) {
                        cartBadge.remove();
                    }
                });
        }
        
        // Quantity input validation
        document.getElementById('quantity').addEventListener('change', function() {
            let value = parseFloat(this.value);
            const max = parseFloat(this.max);
            const min = parseFloat(this.min);
            const step = parseFloat(this.step);
            
            if (isNaN(value)) value = min;
            if (value < min) value = min;
            if (value > max) value = max;
            
            // Round to nearest step
            value = Math.round(value * (1/step)) / (1/step);
            
            this.value = value;
        });
    </script>
</body>
</html>