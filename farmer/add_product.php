<?php
// Set page title
$page_title = "Add New Product";

// Start output buffering to prevent header errors
ob_start();

// Include header first to get $conn and $farmer_id
include 'header.php';

// Define image directory path (same as in manage_products.php)
$image_base_url = '/SpiceCeylon/assets/images/products/';
$image_base_path = $_SERVER['DOCUMENT_ROOT'] . $image_base_url;

// Define categories (same as in edit_product.php)
$categories = ['Whole Spices', 'Spices', 'Leaves & Herbs', 'Roots & Bulbs', 'Fruits & Pods', 'Chilies & Peppers', 'Specialty Spices', 'Powders & Pastes'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $image = ''; // Initialize empty
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative";
    }
    
    // Handle image upload
    $image_uploaded = false;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = $image_base_path;
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = $_FILES["image"]["name"];
        $file_size = $_FILES["image"]["size"];
        $file_tmp = $_FILES["image"]["tmp_name"];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        $max_file_size = 2 * 1024 * 1024; // 2MB
        
        // Validate file
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Only JPG, PNG, GIF, and WEBP files are allowed";
        } elseif ($file_size > $max_file_size) {
            $errors[] = "File size must be less than 2MB";
        } else {
            // Generate unique filename
            $unique_name = 'product_' . $farmer_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $unique_name;
            
            if (move_uploaded_file($file_tmp, $target_file)) {
                $image = $unique_name;
                $image_uploaded = true;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    } else {
        // No image uploaded - this is optional
        $image = NULL;
    }
    
    // If no errors, insert product
    if (empty($errors)) {
        // Prepare the SQL statement
        $insert_stmt = $conn->prepare("INSERT INTO products (farmer_id, name, description, category, price, stock, image, status, admin_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'pending', NOW())");
        
        if ($image_uploaded) {
            $insert_stmt->bind_param("isssdis", $farmer_id, $name, $description, $category, $price, $stock, $image);
        } else {
            // If no image, use NULL
            $insert_stmt->bind_param("isssdis", $farmer_id, $name, $description, $category, $price, $stock, $image);
        }
        
        if ($insert_stmt->execute()) {
            $new_product_id = $insert_stmt->insert_id;
            $_SESSION['message'] = "Product added successfully! Waiting for admin approval.";
            $_SESSION['msg_type'] = "success";
            
            // Redirect to manage products
            header("Location: manage_products.php");
            exit();
        } else {
            $errors[] = "Error adding product: " . $conn->error;
        }
        
        $insert_stmt->close();
    }
}

// If we get here, either form wasn't submitted or there were errors
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - SpiceCeylon Farmer</title>
    <style>
        .image-preview-container {
            width: 200px;
            height: 200px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        
        .preview-placeholder {
            text-align: center;
            color: #6c757d;
        }
        
        .preview-placeholder i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-plus-circle me-2"></i> Add New Product</h4>
                    <a href="manage_products.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
                <div class="card-body">
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h5>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> All new products require admin approval before being visible to customers.
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label required-field">Product Name</label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           placeholder="e.g., Cinnamon Sticks, Cardamom Pods">
                                    <small class="text-muted">Give your product a clear, descriptive name</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Description</label>
                                    <textarea name="description" class="form-control" rows="4" required
                                              placeholder="Describe your product in detail..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    <small class="text-muted">Include details about quality, aroma, uses, etc.</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Category</label>
                                            <select name="category" class="form-select" required>
                                                <option value="">Select Category</option>
                                                <?php foreach($categories as $cat): ?>
                                                    <option value="<?php echo $cat; ?>" 
                                                        <?php echo (isset($_POST['category']) && $_POST['category'] == $cat) ? 'selected' : ''; ?>>
                                                        <?php echo $cat; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Price (Rs.)</label>
                                            <input type="number" name="price" class="form-control" required 
                                                   min="0" step="0.01" 
                                                   value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>"
                                                   placeholder="450.00">
                                            <small class="text-muted">Price per unit in Sri Lankan Rupees</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Stock Quantity</label>
                                            <input type="number" name="stock" class="form-control" required 
                                                   min="0" 
                                                   value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>"
                                                   placeholder="100">
                                            <small class="text-muted">Number of units available for sale</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Product Image</label>
                                            <input type="file" name="image" class="form-control" 
                                                   accept="image/*" id="imageInput">
                                            <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP. Optional but recommended.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6><i class="fas fa-image me-2"></i> Image Preview</h6>
                                        <div class="image-preview-container mb-3">
                                            <div id="previewPlaceholder" class="preview-placeholder">
                                                <i class="fas fa-leaf"></i>
                                                <div>No image selected</div>
                                            </div>
                                            <img id="imagePreview" class="image-preview" alt="Image preview">
                                        </div>
                                        
                                        <hr>
                                        
                                        <h6><i class="fas fa-lightbulb me-2"></i> Tips for Success</h6>
                                        <ul class="small text-start mb-0">
                                            <li>Use high-quality, clear photos</li>
                                            <li>Write detailed, accurate descriptions</li>
                                            <li>Price competitively based on quality</li>
                                            <li>Keep stock quantities updated</li>
                                            <li>Images help sell products faster</li>
                                        </ul>
                                        
                                        <hr>
                                        
                                        <div class="text-muted small">
                                            <i class="fas fa-clock me-1"></i>
                                            Approval typically takes 24-48 hours
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-plus-circle me-2"></i> Add Product
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i> Clear Form
                            </button>
                            <a href="manage_products.php" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview image before upload
    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const previewPlaceholder = document.getElementById('previewPlaceholder');
        
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Show preview, hide placeholder
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                        previewPlaceholder.style.display = 'none';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                } else {
                    // No file selected, show placeholder
                    imagePreview.style.display = 'none';
                    previewPlaceholder.style.display = 'flex';
                }
            });
        }
        
        // Reset form preview when form is reset
        const form = document.getElementById('productForm');
        if (form) {
            form.addEventListener('reset', function() {
                imagePreview.style.display = 'none';
                previewPlaceholder.style.display = 'flex';
                imagePreview.src = '';
            });
        }
        
        // Form validation
        form.addEventListener('submit', function(e) {
            const priceInput = document.querySelector('input[name="price"]');
            const stockInput = document.querySelector('input[name="stock"]');
            
            // Validate price
            if (priceInput.value <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                priceInput.focus();
                return false;
            }
            
            // Validate stock
            if (stockInput.value < 0) {
                e.preventDefault();
                alert('Stock quantity cannot be negative');
                stockInput.focus();
                return false;
            }
            
            // Validate image size (client-side check)
            if (imageInput.files && imageInput.files[0]) {
                const fileSize = imageInput.files[0].size;
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (fileSize > maxSize) {
                    e.preventDefault();
                    alert('Image size must be less than 2MB');
                    imageInput.focus();
                    return false;
                }
            }
            
            return true;
        });
    });
</script>

<?php 
ob_end_flush();
// Include footer
include 'footer.php'; 
?>