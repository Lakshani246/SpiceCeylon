<?php
require_once '../config/db.php';
require_once '../config/auth_check.php';

// Redirect if not customer
if ($_SESSION['role'] !== 'customer') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$customer_id = $_SESSION['user_id'];

// Get customer data
$customer_query = "SELECT * FROM users WHERE user_id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
$customer = $customer_result->fetch_assoc();

// Get recent orders
$orders_query = "SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 5";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Get cart count
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $customer_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SpiceCeylon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="../assets/css/customer_dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Dashboard Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">
                                <i class="fas fa-store me-2"></i>
                                Shop Spices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart me-2"></i>
                                My Cart
                                <?php if($cart_count > 0): ?>
                                    <span class="badge bg-primary ms-1"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-clipboard-list me-2"></i>
                                My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="request_product.php">
                                <i class="fas fa-plus-circle me-2"></i>
                                Request Product
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope me-2"></i>
                                Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php">
                                <i class="fas fa-info-circle me-2"></i>
                                About Us
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Welcome Section -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Welcome, <?php echo htmlspecialchars($customer['name']); ?>!</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="home.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-shopping-bag me-1"></i>
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Orders</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $total_orders_query = "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?";
                                                $stmt = $conn->prepare($total_orders_query);
                                                $stmt->bind_param("i", $customer_id);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $total_orders = $result->fetch_assoc()['total'];
                                                echo $total_orders;
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Cart Items</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $cart_count; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pending Orders</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $pending_orders_query = "SELECT COUNT(*) as pending FROM orders WHERE customer_id = ? AND status = 'Pending'";
                                                $stmt = $conn->prepare($pending_orders_query);
                                                $stmt->bind_param("i", $customer_id);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $pending_orders = $result->fetch_assoc()['pending'];
                                                echo $pending_orders;
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Product Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $requests_query = "SELECT COUNT(*) as requests FROM requests WHERE customer_id = ?";
                                                $stmt = $conn->prepare($requests_query);
                                                $stmt->bind_param("i", $customer_id);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $total_requests = $result->fetch_assoc()['requests'];
                                                echo $total_requests;
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Profile -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                            </div>
                            <div class="card-body">
                                <?php if($orders_result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($order = $orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>#<?php echo $order['order_id']; ?></td>
                                                    <td>Rs. <?php echo number_format($order['total'], 2); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                                switch($order['status']) {
                                                                    case 'Completed': echo 'bg-success'; break;
                                                                    case 'Processing': echo 'bg-primary'; break;
                                                                    case 'Pending': echo 'bg-warning'; break;
                                                                    default: echo 'bg-danger';
                                                                }
                                                            ?>
                                                        ">
                                                            <?php echo $order['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                                    <td>
                                                        <a href="orders.php?view=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="orders.php" class="btn btn-primary btn-sm">View All Orders</a>
                                <?php else: ?>
                                    <p class="text-muted">No orders yet. <a href="home.php">Start shopping!</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Summary -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Profile Summary</h6>
                            </div>
                            <div class="card-body">
                                <!-- In your dashboard.php profile section -->
                                <div class="text-center">
                                    <img class="img-profile rounded-circle mb-3" 
                                        src="../assets/images/profile-image.jpg?php echo !empty($user['profile_image']) ? $user['profile_image'] : 'default-profile-image.jpg'; ?>" 
                                          alt="Profile" 
                                        style="width: 100px; height: 100px; object-fit: cover; border: 2px solid #b85c38;">
                                    <h5 class="font-weight-bold"><?php echo htmlspecialchars($user['name']); ?></h5>
                                    </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong class="d-block">Phone</strong>
                                        <small class="text-muted"><?php echo htmlspecialchars($customer['phone']); ?></small>
                                    </div>
                                    <div class="col-6">
                                        <strong class="d-block">Member Since</strong>
                                        <small class="text-muted"><?php echo date('M Y', strtotime($customer['created_at'])); ?></small>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <strong>Address:</strong>
                                    <p class="text-muted small"><?php echo htmlspecialchars($customer['address']); ?></p>
                                </div>
                                <a href="edit_profile.php" class="btn btn-primary btn-block">
                                    <i class="fas fa-edit me-1"></i>
                                    Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/customer_dashboard.js"></script>
</body>
</html>

<?php
// Close connections
$customer_stmt->close();
$orders_stmt->close();
$cart_stmt->close();
$conn->close();
?>