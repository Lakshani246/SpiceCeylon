<?php
// Set page title
$page_title = "My Products";

// Start output buffering to prevent header errors
ob_start();

// Include header first to get $conn and $farmer_id
include 'header.php';

// Define image directory path (relative to your farmer folder)
$image_base_url = '/SpiceCeylon/assets/images/products/';
$image_base_path = $_SERVER['DOCUMENT_ROOT'] . $image_base_url;

// Handle delete product
if (isset($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    
    // Verify product belongs to this farmer
    $verify = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND farmer_id = ?");
    $verify->bind_param("ii", $product_id, $farmer_id);
    $verify->execute();
    $verify_result = $verify->get_result();
    
    if ($verify_result->num_rows > 0) {
        $product = $verify_result->fetch_assoc();
        
        // Delete product image if exists
        if ($product['image']) {
            $image_path = $image_base_path . $product['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // Delete product
        $delete_stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $delete_stmt->bind_param("i", $product_id);
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "Product deleted successfully";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting product: " . $conn->error;
            $_SESSION['msg_type'] = "danger";
        }
        $delete_stmt->close();
    } else {
        $_SESSION['message'] = "Product not found or you don't have permission to delete it";
        $_SESSION['msg_type'] = "danger";
    }
    $verify->close();
    
    // Redirect
    header("Location: manage_products.php");
    exit();
}

// Get all products for this farmer with their approval status
$products_query = $conn->prepare("
    SELECT p.*, 
           a.username as approved_by_admin,
           CASE 
               WHEN p.admin_approved = 'approved' THEN 'Approved'
               WHEN p.admin_approved = 'rejected' THEN 'Rejected'
               WHEN p.admin_approved = 'pending' THEN 'Pending'
               ELSE 'Pending'
           END as approval_status
    FROM products p 
    LEFT JOIN admins a ON p.approved_by = a.admin_id
    WHERE p.farmer_id = ? 
    ORDER BY p.created_at DESC
");
$products_query->bind_param("i", $farmer_id);
$products_query->execute();
$products = $products_query->get_result();

// Count products by status
$count_query = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN admin_approved = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN admin_approved = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN admin_approved = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
    FROM products 
    WHERE farmer_id = ?
");
$count_query->bind_param("i", $farmer_id);
$count_query->execute();
$count_result = $count_query->get_result()->fetch_assoc();
$approved_count = $count_result['approved_count'];
$pending_count = $count_result['pending_count'];
$rejected_count = $count_result['rejected_count'];
$total_count = $count_result['total_count'];
$count_query->close();

// Simple function to get image URL
function getImageUrl($image_filename) {
    global $image_base_url;
    
    if (empty($image_filename)) {
        return false;
    }
    
    // Return the full URL path
    return $image_base_url . $image_filename;
}
?>

<style>
    .stock-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
    .stock-good { background: #27ae60; }
    .stock-low { background: #f39c12; }
    .stock-out { background: #e74c3c; }
    
    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: transform 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        text-decoration: none;
        color: inherit;
    }
    
    .product-image-container {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid #e9ecef;
        background: #f8f9fa;
    }
    
    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .category-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
    }
    
    .filter-badge {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-badge:hover {
        transform: scale(1.05);
    }
    
    .product-card {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .product-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-3px);
        border-color: var(--farmer-green);
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .badge-approved {
        background-color: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }
    
    .badge-pending {
        background-color: #fff3cd;
        color: #664d03;
        border: 1px solid #ffecb5;
    }
    
    .badge-rejected {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-leaf me-2"></i> My Products</h4>
                        <small class="text-muted">Manage your spice products</small>
                    </div>
                    <div>
                        <a href="add_product.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Add New Product
                        </a>
                    </div>
                </div>
                
                <!-- Statistics with clickable filters -->
                <div class="card-body border-bottom">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="manage_products.php" class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                        <i class="fas fa-boxes text-primary fa-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $total_count; ?></h3>
                                        <small class="text-muted">Total Products</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="manage_products.php?status=approved" class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                        <i class="fas fa-check-circle text-success fa-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $approved_count; ?></h3>
                                        <small class="text-muted">Approved</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="manage_products.php?status=pending" class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                        <i class="fas fa-clock text-warning fa-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $pending_count; ?></h3>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="manage_products.php?status=rejected" class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                                        <i class="fas fa-times-circle text-danger fa-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $rejected_count; ?></h3>
                                        <small class="text-muted">Rejected</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if(isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show">
                            <?php echo $_SESSION['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
                    <?php endif; ?>
                    
                    <!-- Filter buttons -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="manage_products.php" class="btn btn-sm <?php echo !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    <i class="fas fa-list me-1"></i> All Products
                                </a>
                                <a href="manage_products.php?status=approved" class="btn btn-sm <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'btn-success' : 'btn-outline-success'; ?>">
                                    <i class="fas fa-check-circle me-1"></i> Approved
                                </a>
                                <a href="manage_products.php?status=pending" class="btn btn-sm <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </a>
                                <a href="manage_products.php?status=rejected" class="btn btn-sm <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                    <i class="fas fa-times-circle me-1"></i> Rejected
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                    // Filter products based on status
                    $filtered_products = [];
                    $products->data_seek(0);
                    while($product = $products->fetch_assoc()) {
                        if (!isset($_GET['status']) || $_GET['status'] == 'all') {
                            $filtered_products[] = $product;
                        } elseif ($_GET['status'] == 'approved' && $product['approval_status'] == 'Approved') {
                            $filtered_products[] = $product;
                        } elseif ($_GET['status'] == 'pending' && $product['approval_status'] == 'Pending') {
                            $filtered_products[] = $product;
                        } elseif ($_GET['status'] == 'rejected' && $product['approval_status'] == 'Rejected') {
                            $filtered_products[] = $product;
                        }
                    }
                    
                    if(count($filtered_products) > 0): 
                    ?>
                    <div class="row">
                        <?php foreach($filtered_products as $product): 
                            $stock_class = $product['stock'] == 0 ? 'stock-out' : ($product['stock'] < 10 ? 'stock-low' : 'stock-good');
                            $approval_class = 'badge-' . strtolower($product['approval_status']);
                            
                            // Get image URL - SIMPLE VERSION
                            $image_url = getImageUrl($product['image']);
                            $image_exists = $image_url && file_exists($image_base_path . $product['image']);
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="product-card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="product-image-container me-3">
                                            <?php if($image_url && $image_exists): ?>
                                                <img src="<?php echo $image_url; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="product-image">
                                            <?php else: ?>
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light">
                                                    <i class="fas fa-leaf fa-2x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <span class="badge bg-info category-badge"><?php echo htmlspecialchars($product['category']); ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo $approval_class; ?>">
                                        <?php if($product['approval_status'] == 'Approved'): ?>
                                            <i class="fas fa-check-circle me-1"></i>
                                        <?php elseif($product['approval_status'] == 'Pending'): ?>
                                            <i class="fas fa-clock me-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle me-1"></i>
                                        <?php endif; ?>
                                        <?php echo $product['approval_status']; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Price</small>
                                            <div class="fw-bold text-success">Rs. <?php echo number_format($product['price'], 2); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Stock</small>
                                            <div class="fw-bold">
                                                <?php echo $product['stock']; ?>
                                                <small class="ms-1">
                                                    <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                                    <?php 
                                                    if($product['stock'] == 0) {
                                                        echo 'Out of Stock';
                                                    } elseif($product['stock'] < 10) {
                                                        echo 'Low Stock';
                                                    } else {
                                                        echo 'In Stock';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="small text-muted mb-3" style="max-height: 60px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>
                                    <?php if(strlen($product['description']) > 100): ?>...<?php endif; ?>
                                </p>
                                
                                <?php if($product['rejection_reason']): ?>
                                <div class="alert alert-danger p-2 mb-3">
                                    <small>
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($product['rejection_reason']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                        </small>
                                        <?php if($product['approved_at']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-check me-1"></i>
                                            Approved: <?php echo date('M d, Y', strtotime($product['approved_at'])); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if($product['admin_approved'] != 'approved'): ?>
                                        <a href="?delete=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this product?\nThis action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            <?php if(isset($_GET['status'])): ?>
                                No <?php echo htmlspecialchars($_GET['status']); ?> products found
                            <?php else: ?>
                                No products found
                            <?php endif; ?>
                        </h5>
                        <p class="text-muted mb-4">
                            <?php if(isset($_GET['status'])): ?>
                                You don't have any <?php echo htmlspecialchars($_GET['status']); ?> products.
                            <?php else: ?>
                                You haven't added any products yet.
                            <?php endif; ?>
                        </p>
                        <a href="add_product.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Add New Product
                        </a>
                        <?php if(isset($_GET['status'])): ?>
                        <a href="manage_products.php" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-list me-1"></i> View All Products
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-dismissible')) {
                    var closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }
            });
        }, 5000);
        
        // Add confirmation for delete buttons
        var deleteButtons = document.querySelectorAll('a[href*="delete="]');
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this product?\nThis action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>

<?php 
// Close database connection for queries
if (isset($products_query)) {
    $products_query->close();
}

// Include footer
include 'footer.php'; 
?>