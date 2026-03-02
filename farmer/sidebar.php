<?php
// farmer/sidebar.php
?>
<!-- Sidebar -->
<nav class="col-md-2 d-md-block sidebar p-0">
    <div class="brand">
        <h4 class="text-white mb-1">
            <i class="fas fa-tractor me-2"></i>
            Farmer Panel
        </h4>
        <small class="text-light opacity-75">SpiceCeylon</small>
    </div>
    
    <ul class="nav flex-column mt-4">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="manage_products.php">
                <i class="fas fa-leaf me-2"></i>
                My Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="add_product.php">
                <i class="fas fa-plus-circle me-2"></i>
                Add New Product
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="customer_requests.php">
                <i class="fas fa-inbox me-2"></i>
                Customer Requests
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="messages.php">
                <i class="fas fa-envelope me-2"></i>
                Messages
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="notifications.php">
                <i class="fas fa-bell me-2"></i>
                Notifications
            </a>
        </li>
        <li class="nav-item">
    <a class="nav-link" href="earnings.php">
        <i class="fas fa-wallet me-2"></i>
        Earnings Monitor
    </a>
</li>
        <li class="nav-item">
            <a class="nav-link active" href="forecasting.php">
                <i class="fas fa-chart-line me-2"></i>
                Sales Forecasting
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="my_sales.php">
                <i class="fas fa-chart-bar me-2"></i>
                Sales Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user me-2"></i>
                My Profile
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link" href="../auth/logout.php" style="background: rgba(231, 76, 60, 0.1);">
                <i class="fas fa-sign-out-alt me-2"></i>
                Logout
            </a>
        </li>
    </ul>
    
    <div class="mt-auto p-3 text-center text-light opacity-75 small">
        <i class="fas fa-seedling me-1"></i>
        Farmer ID: F<?php echo str_pad($farmer_id ?? 0, 4, '0', STR_PAD_LEFT); ?>
    </div>
</nav>