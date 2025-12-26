<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $product_name = $conn->real_escape_string(trim($_POST['product_name']));
    $category = $conn->real_escape_string(trim($_POST['category']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $quantity = intval($_POST['quantity']);
    $urgency = $conn->real_escape_string($_POST['urgency']);
    
    // Validate
    if (empty($product_name)) {
        $error_message = "Product name is required!";
    } elseif (strlen($product_name) < 3) {
        $error_message = "Product name must be at least 3 characters!";
    } else {
        // Check if similar request already exists
        $check_query = "SELECT request_id FROM product_requests 
                       WHERE customer_id = ? AND product_name LIKE ? AND status = 'Pending'";
        $check_stmt = $conn->prepare($check_query);
        $search_term = "%" . $product_name . "%";
        $check_stmt->bind_param("is", $user_id, $search_term);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "You already have a pending request for a similar product!";
        } else {
            // Insert new request
            $insert_query = "INSERT INTO product_requests 
                            (customer_id, product_name, category, description, quantity_requested, urgency, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("isssis", $user_id, $product_name, $category, $description, $quantity, $urgency);
            
            if ($insert_stmt->execute()) {
                $success_message = "Your product request has been submitted successfully! We'll review it soon.";
                
                // Reset form
                $_POST = array();
            } else {
                $error_message = "Failed to submit request. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get user's previous requests
$requests_query = "SELECT * FROM product_requests WHERE customer_id = ? ORDER BY created_at DESC";
$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->bind_param("i", $user_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Get cart count
$cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_count = $cart_result->fetch_assoc()['cart_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Product - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-light: #f8f9fa;
            --spice-purple: #9b59b6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--spice-light);
            color: #333;
            padding-bottom: 50px;
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
        
        .nav-link:hover, .nav-link.active {
            color: var(--spice-red) !important;
        }
        
        /* Request Container */
        .request-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Header */
        .request-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .request-header h1 {
            color: var(--spice-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .request-header p {
            color: #666;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Request Form Card */
        .request-form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 40px;
            border: 1px solid #e9ecef;
            margin-bottom: 40px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h4 {
            color: var(--spice-dark);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--spice-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--spice-red);
            box-shadow: 0 0 0 3px rgba(184, 92, 56, 0.1);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--spice-green);
            border-left: 4px solid var(--spice-green);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        /* Urgency Badges */
        .urgency-option {
            display: inline-flex;
            align-items: center;
            margin-right: 15px;
            cursor: pointer;
        }
        
        .urgency-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .urgency-low .urgency-dot { background: var(--spice-green); }
        .urgency-medium .urgency-dot { background: var(--spice-gold); }
        .urgency-high .urgency-dot { background: #e74c3c; }
        
        /* Request Card */
        .request-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .request-card-header {
            background: rgba(184, 92, 56, 0.1);
            padding: 20px;
            border-bottom: 2px solid rgba(184, 92, 56, 0.2);
            border-radius: 12px 12px 0 0;
        }
        
        .request-card-body {
            padding: 25px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .status-pending { background: rgba(149, 165, 166, 0.2); color: #636e72; }
        .status-reviewed { background: rgba(52, 152, 219, 0.2); color: var(--spice-blue); }
        .status-approved { background: rgba(39, 174, 96, 0.2); color: var(--spice-green); }
        .status-rejected { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .status-completed { background: rgba(155, 89, 182, 0.2); color: var(--spice-purple); }
        
        /* Buttons */
        .btn-submit {
            background: var(--spice-red);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            background: #a04a2c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 92, 56, 0.3);
        }
        
        .btn-submit:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        /* Feature Cards */
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5rem;
        }
        
        .feature-1 .feature-icon { background: rgba(184, 92, 56, 0.1); color: var(--spice-red); }
        .feature-2 .feature-icon { background: rgba(39, 174, 96, 0.1); color: var(--spice-green); }
        .feature-3 .feature-icon { background: rgba(52, 152, 219, 0.1); color: var(--spice-blue); }
        
        /* Empty State */
        .empty-requests {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
            margin: 40px 0;
        }
        
        .empty-requests-icon {
            font-size: 5rem;
            color: #e9ecef;
            margin-bottom: 20px;
        }
        
        .empty-requests h3 {
            color: var(--spice-dark);
            margin-bottom: 10px;
        }
        
        .empty-requests p {
            color: #666;
            margin-bottom: 30px;
        }
        
        /* Tips Section */
        .tips-section {
            background: rgba(184, 92, 56, 0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--spice-red);
        }
        
        .tips-section h5 {
            color: var(--spice-dark);
            margin-bottom: 15px;
        }
        
        .tips-section ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .tips-section li {
            margin-bottom: 8px;
            color: #666;
        }
        
        /* Footer */
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }
        
        @media (max-width: 768px) {
            .request-form-card {
                padding: 25px;
            }
            
            .feature-card {
                margin-bottom: 15px;
            }
            
            .urgency-options {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="home.php">
                <i class="fas fa-pepper-hot me-2"></i>SpiceCeylon
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i> Cart 
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="wishlist.php">Wishlist</a></li>
                    <li class="nav-item"><a class="nav-link active" href="request.php">Request</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Request Container -->
    <div class="request-container">
        <!-- Header -->
        <div class="request-header">
            <h1><i class="fas fa-plus-circle me-2" style="color: var(--spice-red);"></i>Request a Spice</h1>
            <p>Can't find what you're looking for? Request any Sri Lankan spice and we'll try to source it for you!</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Feature Cards -->
        <div class="row mb-5">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card feature-1">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4>Can't Find It?</h4>
                    <p class="text-muted">Request any Sri Lankan spice that's not in our catalog.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card feature-2">
                    <div class="feature-icon">
                        <i class="fas fa-tractor"></i>
                    </div>
                    <h4>Direct From Farmers</h4>
                    <p class="text-muted">We source directly from authentic Sri Lankan farmers.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="feature-card feature-3">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4>Quick Response</h4>
                    <p class="text-muted">We review requests within 24-48 hours.</p>
                </div>
            </div>
        </div>

        <!-- Request Form -->
        <div class="request-form-card">
            <form method="POST" action="request.php">
                <div class="form-section">
                    <h4><i class="fas fa-info-circle me-2"></i>Product Details</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="product_name" class="form-label">Spice Name *</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" 
                                   value="<?php echo isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : ''; ?>"
                                   placeholder="e.g., Ceylon Cinnamon, Cardamom, Vanilla" required>
                            <small class="text-muted">Enter the specific spice name you're looking for</small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Select Category</option>
                                <option value="Whole Spices" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Whole Spices') ? 'selected' : ''; ?>>Whole Spices</option>
                                <option value="Powders & Pastes" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Powders & Pastes') ? 'selected' : ''; ?>>Powders & Pastes</option>
                                <option value="Leaves & Herbs" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Leaves & Herbs') ? 'selected' : ''; ?>>Leaves & Herbs</option>
                                <option value="Roots & Bulbs" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Roots & Bulbs') ? 'selected' : ''; ?>>Roots & Bulbs</option>
                                <option value="Fruits & Pods" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Fruits & Pods') ? 'selected' : ''; ?>>Fruits & Pods</option>
                                <option value="Chilies & Peppers" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Chilies & Peppers') ? 'selected' : ''; ?>>Chilies & Peppers</option>
                                <option value="Specialty Spices" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Specialty Spices') ? 'selected' : ''; ?>>Specialty Spices</option>
                                <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Tell us more about the spice you're looking for. Include details like quality, form (whole/ground), origin preferences, etc." 
                                  required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="quantity" class="form-label">Quantity Needed</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                       value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : '1'; ?>"
                                       min="1" max="100">
                                <span class="input-group-text">kg</span>
                            </div>
                            <small class="text-muted">Approximate quantity you need</small>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label d-block">Urgency Level</label>
                            <div class="urgency-options">
                                <label class="urgency-option urgency-low">
                                    <input type="radio" name="urgency" value="Low" class="me-2" 
                                           <?php echo (!isset($_POST['urgency']) || $_POST['urgency'] == 'Low') ? 'checked' : ''; ?>>
                                    <span class="urgency-dot"></span>
                                    <span>Low Priority</span>
                                </label>
                                <label class="urgency-option urgency-medium">
                                    <input type="radio" name="urgency" value="Medium" class="me-2" 
                                           <?php echo (isset($_POST['urgency']) && $_POST['urgency'] == 'Medium') ? 'checked' : ''; ?>>
                                    <span class="urgency-dot"></span>
                                    <span>Medium Priority</span>
                                </label>
                                <label class="urgency-option urgency-high">
                                    <input type="radio" name="urgency" value="High" class="me-2" 
                                           <?php echo (isset($_POST['urgency']) && $_POST['urgency'] == 'High') ? 'checked' : ''; ?>>
                                    <span class="urgency-dot"></span>
                                    <span>High Priority</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Section -->
                <div class="tips-section">
                    <h5><i class="fas fa-lightbulb me-2"></i>Tips for Better Requests</h5>
                    <ul>
                        <li>Be specific about the spice name and quality requirements</li>
                        <li>Mention if you need whole or ground form</li>
                        <li>Specify any special requirements (organic, specific region, etc.)</li>
                        <li>High urgency requests get priority attention</li>
                    </ul>
                </div>
                
                <button type="submit" name="submit_request" class="btn-submit mt-4">
                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                </button>
            </form>
        </div>

        <!-- My Previous Requests -->
        <div class="form-section">
            <h4><i class="fas fa-history me-2"></i>My Previous Requests</h4>
            
            <?php if ($requests_result->num_rows > 0): ?>
                <div class="requests-list">
                    <?php while ($request = $requests_result->fetch_assoc()): 
                        $statusClass = 'status-' . strtolower($request['status']);
                    ?>
                    <div class="request-card">
                        <div class="request-card-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($request['product_name']); ?></h5>
                                    <p class="text-muted mb-0 small">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                        <?php if ($request['category']): ?>
                                            • <i class="fas fa-tag ms-2 me-1"></i><?php echo htmlspecialchars($request['category']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="status-badge <?php echo $statusClass; ?> mt-2 mt-md-0">
                                    <?php echo $request['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="request-card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="mb-3"><?php echo htmlspecialchars($request['description']); ?></p>
                                    
                                    <div class="d-flex flex-wrap gap-3">
                                        <span class="text-muted">
                                            <i class="fas fa-weight me-1"></i>
                                            Quantity: <?php echo $request['quantity_requested']; ?> kg
                                        </span>
                                        <span class="text-muted">
                                            <i class="fas fa-bolt me-1"></i>
                                            Urgency: <?php echo $request['urgency']; ?>
                                        </span>
                                        <?php if ($request['updated_at']): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-sync-alt me-1"></i>
                                                Updated: <?php echo date('M j, Y', strtotime($request['updated_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($request['admin_notes']): ?>
                                        <div class="mt-3 p-3 rounded" style="background: rgba(52, 152, 219, 0.1); border-left: 3px solid var(--spice-blue);">
                                            <strong><i class="fas fa-comment-dots me-2"></i>Admin Notes:</strong>
                                            <p class="mb-0 mt-1"><?php echo htmlspecialchars($request['admin_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <?php if ($request['status'] == 'Completed'): ?>
                                        <a href="home.php" class="btn btn-sm" 
                                           style="background: var(--spice-green); color: white; padding: 8px 20px;">
                                            <i class="fas fa-shopping-cart me-1"></i>Shop Now
                                        </a>
                                    <?php elseif ($request['status'] == 'Approved'): ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Available for purchase
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty Requests State -->
                <div class="empty-requests">
                    <div class="empty-requests-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>No Requests Yet</h3>
                    <p>You haven't submitted any product requests. Fill out the form above to request your first spice!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Request Support</h4>
                    <p><i class="fas fa-question-circle me-2"></i> Need help with requests?</p>
                    <p><i class="fas fa-envelope me-2"></i> requests@spiceceylon.com</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Quick Links</h4>
                    <p><i class="fas fa-store me-2"></i> <a href="home.php" class="text-light text-decoration-none">Browse Spices</a></p>
                    <p><i class="fas fa-shopping-cart me-2"></i> <a href="cart.php" class="text-light text-decoration-none">View Cart</a></p>
                </div>
            </div>
            <hr class="mt-4 mb-3">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SpiceCeylon. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        // Form validation
        const form = document.querySelector('form');
        const productName = document.getElementById('product_name');
        const description = document.getElementById('description');
        
        form.addEventListener('submit', function(e) {
            let valid = true;
            
            // Clear previous error styles
            productName.style.borderColor = '#e9ecef';
            description.style.borderColor = '#e9ecef';
            
            if (productName.value.trim().length < 3) {
                productName.style.borderColor = '#e74c3c';
                productName.focus();
                valid = false;
            }
            
            if (description.value.trim().length < 10) {
                description.style.borderColor = '#e74c3c';
                if (valid) description.focus();
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
    </script>
</body>
</html>

<?php
// Close connections
if (isset($requests_stmt)) $requests_stmt->close();
if (isset($cart_stmt)) $cart_stmt->close();
$conn->close();
?>