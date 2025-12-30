<?php
// Set page title
$page_title = "Edit Product";

// Start output buffering at the VERY beginning
ob_start();

// Include header first to get $conn and $farmer_id
include 'header.php';

// Define image directory path (same as in manage_products.php)
$image_base_url = '/SpiceCeylon/assets/images/products/';
$image_base_path = $_SERVER['DOCUMENT_ROOT'] . $image_base_url;

// Check if product ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "Product ID is required";
    $_SESSION['msg_type'] = "danger";
    header("Location: manage_products.php");
    exit();
}

$product_id = intval($_GET['id']);

// Verify product belongs to this farmer and get product data
$product_query = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND farmer_id = ?");
$product_query->bind_param("ii", $product_id, $farmer_id);
$product_query->execute();
$product_result = $product_query->get_result();

if ($product_result->num_rows == 0) {
    $_SESSION['message'] = "Product not found or you don't have permission to edit it";
    $_SESSION['msg_type'] = "danger";
    header("Location: manage_products.php");
    exit();
}

$product = $product_result->fetch_assoc();

// Define categories (same as in add_product.php)
$categories = ['Whole Spices', 'Spices', 'Leaves & Herbs', 'Roots & Bulbs', 'Fruits & Pods', 'Chilies & Peppers', 'Specialty Spices', 'Powders & Pastes'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // Handle image upload
    $image = $product['image']; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = $image_base_path; // Use the defined path
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Delete old image if exists
            if ($image && file_exists($target_dir . $image)) {
                unlink($target_dir . $image);
            }
            
            // Generate unique filename
            $unique_name = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $unique_name;
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = $unique_name;
            }
        }
    }
    
    // Update product
    $update_stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category = ?, price = ?, stock = ?, image = ?, status = 'Pending', admin_approved = 'pending' WHERE product_id = ? AND farmer_id = ?");
    $update_stmt->bind_param("sssdisii", $name, $description, $category, $price, $stock, $image, $product_id, $farmer_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['message'] = "Product updated successfully! Waiting for admin approval.";
        $_SESSION['msg_type'] = "success";
        // Flush output buffer before redirect
        ob_end_clean();
        header("Location: manage_products.php");
        exit();
    } else {
        $error = "Error updating product: " . $conn->error;
    }
    
    $update_stmt->close();
}

// Get current image URL - with better debugging
$current_image = '';
$image_exists = false;
$image_debug_info = '';

if (!empty($product['image'])) {
    $image_filename = $product['image'];
    $full_image_path = $image_base_path . $image_filename;
    $image_url = $image_base_url . $image_filename;
    
    // Check if file actually exists
    $image_exists = file_exists($full_image_path);
    
    if ($image_exists) {
        $current_image = $image_url;
        $image_debug_info = "Image found: $full_image_path";
    } else {
        $image_debug_info = "Image NOT found: $full_image_path";
        // Try alternative paths
        $alternative_paths = [
            $_SERVER['DOCUMENT_ROOT'] . '/SpiceCeylon/assets/images/' . $image_filename,
            dirname(__FILE__) . '/../assets/images/products/' . $image_filename,
            dirname(__FILE__) . '/../assets/images/' . $image_filename,
        ];
        
        foreach ($alternative_paths as $alt_path) {
            if (file_exists($alt_path)) {
                // Convert to URL
                $current_image = str_replace($_SERVER['DOCUMENT_ROOT'], '', $alt_path);
                $current_image = str_replace('\\', '/', $current_image);
                $image_exists = true;
                $image_debug_info = "Image found at alternative: $alt_path";
                break;
            }
        }
    }
} else {
    $image_debug_info = "No image filename in database";
}

// Debug output (remove after testing)
// echo "<!-- DEBUG: $image_debug_info -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - SpiceCeylon Farmer</title>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-edit me-2"></i> Edit Product</h4>
                    <a href="manage_products.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
                <div class="card-body">
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Editing a product will change its status to "Pending" and require admin approval again.
                    </div>
                    
                    <!-- TEMPORARY DEBUG INFO - Remove after testing -->
                    <?php if(!empty($image_debug_info)): ?>
                    <div class="alert alert-warning alert-dismissible fade show small">
                        <strong>Image Debug:</strong> <?php echo $image_debug_info; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?php echo htmlspecialchars($product['name']); ?>"
                                           placeholder="e.g., Cinnamon Sticks">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description *</label>
                                    <textarea name="description" class="form-control" rows="4" required
                                              placeholder="Describe your product..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Category *</label>
                                            <select name="category" class="form-select" required>
                                                <option value="">Select Category</option>
                                                <?php foreach($categories as $cat): ?>
                                                    <option value="<?php echo $cat; ?>" 
                                                        <?php echo ($product['category'] == $cat) ? 'selected' : ''; ?>>
                                                        <?php echo $cat; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Price (Rs.) *</label>
                                            <input type="number" name="price" class="form-control" required 
                                                   min="0" step="0.01" 
                                                   value="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                                   placeholder="450.00">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Stock Quantity *</label>
                                            <input type="number" name="stock" class="form-control" required 
                                                   min="0" 
                                                   value="<?php echo $product['stock']; ?>"
                                                   placeholder="100">
                                            <small class="text-muted">Number of units available</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Current Approval Status</label>
                                            <div class="form-control bg-light">
                                                <?php 
                                                if($product['admin_approved'] == 'approved') {
                                                    echo '<span class="badge bg-success">Approved</span>';
                                                } elseif($product['admin_approved'] == 'rejected') {
                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                } else {
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Product Image</label>
                                    <input type="file" name="image" class="form-control" 
                                           accept="image/*" id="imageInput">
                                    <small class="text-muted">Leave empty to keep current image. Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-image me-2"></i> Current Image</h6>
                                        <div class="text-center mb-3">
                                            <div id="imagePreview">
                                                <?php if($image_exists && !empty($current_image)): ?>
                                                    <img src="<?php echo $current_image; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                         class="img-fluid rounded current-image-preview" 
                                                         style="max-height: 200px;"
                                                         onerror="handleImageError(this)">
                                                    <small class="text-muted d-block mt-2">Current product image</small>
                                                <?php else: ?>
                                                    <div class="bg-white rounded d-flex align-items-center justify-content-center" 
                                                         style="height: 150px; border: 2px dashed #dee2e6;">
                                                        <i class="fas fa-leaf fa-3x text-muted"></i>
                                                    </div>
                                                    <small class="text-muted d-block mt-2">
                                                        <?php echo empty($product['image']) ? 'No image uploaded' : 'Image file not found: ' . htmlspecialchars($product['image']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <h6><i class="fas fa-info-circle me-2"></i> Product Info</h6>
                                        <ul class="small mb-0">
                                            <li>Product ID: P<?php echo str_pad($product['product_id'], 4, '0', STR_PAD_LEFT); ?></li>
                                            <li>Created: <?php echo date('M d, Y', strtotime($product['created_at'])); ?></li>
                                            <?php if($product['approved_at']): ?>
                                                <li>Last Approved: <?php echo date('M d, Y', strtotime($product['approved_at'])); ?></li>
                                            <?php endif; ?>
                                            <?php if($product['rejection_reason']): ?>
                                                <li class="text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Rejection Reason: <?php echo htmlspecialchars($product['rejection_reason']); ?>
                                                </li>
                                            <?php endif; ?>
                                            <?php if($product['image']): ?>
                                                <li>Image File: <?php echo htmlspecialchars($product['image']); ?></li>
                                                <li>Image Status: <?php echo $image_exists ? '<span class="text-success">Found</span>' : '<span class="text-danger">Not Found</span>'; ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Update Product
                            </button>
                            <a href="manage_products.php" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <?php if($product['admin_approved'] != 'approved'): ?>
                            <a href="manage_products.php?delete=<?php echo $product['product_id']; ?>" 
                               class="btn btn-danger float-end"
                               onclick="return confirm('Are you sure you want to delete this product?\\nThis action cannot be undone.')">
                                <i class="fas fa-trash me-1"></i> Delete Product
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle image loading errors
    function handleImageError(img) {
        console.log('Image failed to load:', img.src);
        img.style.display = 'none';
        // Create a fallback display
        const container = img.parentElement;
        container.innerHTML = `
            <div class="bg-white rounded d-flex align-items-center justify-content-center" 
                 style="height: 150px; border: 2px dashed #dee2e6;">
                <i class="fas fa-leaf fa-3x text-muted"></i>
            </div>
            <small class="text-muted d-block mt-2">Image failed to load</small>
        `;
    }
    
    // Preview image before upload
    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `
                            <img src="${e.target.result}" 
                                 alt="New image preview" 
                                 class="img-fluid rounded" 
                                 style="max-height: 200px;">
                            <small class="text-muted d-block mt-2">New image preview</small>
                        `;
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Add confirmation for delete link
        const deleteLink = document.querySelector('a[href*="delete="]');
        if (deleteLink) {
            deleteLink.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this product?\nThis action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

<?php 
// Close database connection
if (isset($product_query)) {
    $product_query->close();
}

ob_end_flush();
// Include footer
include 'footer.php'; 
?>