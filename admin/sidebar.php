<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="col-md-2 sidebar p-0">
    <div class="brand">
        <div class="mb-3">
            <i class="fas fa-pepper-hot fa-2x" style="color: var(--spice-red);"></i>
        </div>
        <h4 class="text-white mb-1">SpiceCeylon</h4>
        <small class="text-light">Administration Panel</small>
    </div>
    <nav class="nav flex-column mt-4 px-2">
        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a class="nav-link <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php">
            <i class="fas fa-users me-2"></i>User Management
        </a>
        <a class="nav-link <?php echo $current_page == 'manage_orders.php' ? 'active' : ''; ?>" href="manage_orders.php">
            <i class="fas fa-shopping-cart me-2"></i>Order Management
        </a>
        <a class="nav-link <?php echo $current_page == 'manage_products.php' ? 'active' : ''; ?>" href="manage_products.php">
            <i class="fas fa-leaf me-2"></i>Product Management
        </a>
        <a class="nav-link <?php echo $current_page == 'approve_requests.php' ? 'active' : ''; ?>" href="approve_requests.php">
            <i class="fas fa-inbox me-2"></i>Requests
            <?php 
            $pending_requests = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='Pending'")->fetch_assoc()['count'] ?? 0;
            if($pending_requests > 0): ?>
            <span class="badge bg-danger float-end"><?php echo $pending_requests; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?php echo $current_page == 'manage_website.php' ? 'active' : ''; ?>" href="manage_website.php">
            <i class="fas fa-globe me-2"></i>Website Management
        </a>
        <a class="nav-link <?php echo $current_page == 'manage_content.php' ? 'active' : ''; ?>" href="manage_content.php">
            <i class="fas fa-edit me-2"></i>Content Editor
        </a>
        <a class="nav-link <?php echo $current_page == 'sales_analytics.php' ? 'active' : ''; ?>" href="sales_analytics.php">
            <i class="fas fa-chart-bar me-2"></i>Sales Analytics
        </a>
        <a class="nav-link <?php echo $current_page == 'forecast_sales.php' ? 'active' : ''; ?>" href="forecast_sales.php">
            <i class="fas fa-brain me-2"></i>Sales Forecasting
        </a>
        <a class="nav-link <?php echo $current_page == 'admin_profile.php' ? 'active' : ''; ?>" href="admin_profile.php">
            <i class="fas fa-user-cog me-2"></i>Admin Profile
        </a>
        <div class="mt-5 pt-4 border-top border-secondary">
            <a class="nav-link" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </nav>
</div>