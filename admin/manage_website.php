<?php
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$admin_id = $_SESSION['admin_id'];

// Get admin data
$admin_query = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // General Settings
    if (isset($_POST['save_general'])) {
        $site_name = $_POST['site_name'] ?? '';
        $site_email = $_POST['site_email'] ?? '';
        $site_phone = $_POST['site_phone'] ?? '';
        $site_address = $_POST['site_address'] ?? '';
        $currency = $_POST['currency'] ?? '';
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // Save to database (you'll need to create a settings table)
        $message = "General settings saved successfully!";
        $message_type = "success";
    }
    
    // Shipping Settings
    if (isset($_POST['save_shipping'])) {
        $free_shipping_min = $_POST['free_shipping_min'] ?? 0;
        $local_shipping_fee = $_POST['local_shipping_fee'] ?? 0;
        $international_shipping_fee = $_POST['international_shipping_fee'] ?? 0;
        
        $message = "Shipping settings saved successfully!";
        $message_type = "success";
    }
    
    // Payment Settings
    if (isset($_POST['save_payment'])) {
        $paypal_enabled = isset($_POST['paypal_enabled']) ? 1 : 0;
        $stripe_enabled = isset($_POST['stripe_enabled']) ? 1 : 0;
        $bank_transfer_enabled = isset($_POST['bank_transfer_enabled']) ? 1 : 0;
        $cod_enabled = isset($_POST['cod_enabled']) ? 1 : 0;
        
        $message = "Payment settings saved successfully!";
        $message_type = "success";
    }
    
    // Add Shipping Zone
    if (isset($_POST['add_shipping_zone'])) {
        $zone_name = $_POST['zone_name'] ?? '';
        $countries = $_POST['countries'] ?? '';
        $shipping_fee = $_POST['shipping_fee'] ?? 0;
        $delivery_time = $_POST['delivery_time'] ?? '';
        
        $message = "Shipping zone added successfully!";
        $message_type = "success";
    }
    
    // Add Payment Method
    if (isset($_POST['add_payment_method'])) {
        $method_name = $_POST['method_name'] ?? '';
        $method_type = $_POST['method_type'] ?? '';
        $credentials = $_POST['credentials'] ?? '';
        
        $message = "Payment method added successfully!";
        $message_type = "success";
    }
    
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: website_management.php");
    exit;
}

// Load settings from database (simulated data for now)
$site_name = "SpiceCeylon";
$site_email = "info@spiceceylon.com";
$site_phone = "+94 11 234 5678";
$site_address = "Colombo, Sri Lanka";
$currency = "LKR";
$maintenance_mode = 0;
$free_shipping_min = 5000;
$local_shipping_fee = 500;
$international_shipping_fee = 2500;

// Sample shipping zones
$shipping_zones = [
    ['id' => 1, 'name' => 'Colombo District', 'countries' => 'Sri Lanka (Colombo)', 'fee' => 300, 'time' => '1-2 days'],
    ['id' => 2, 'name' => 'Other Districts', 'countries' => 'Sri Lanka (Other)', 'fee' => 600, 'time' => '2-4 days'],
    ['id' => 3, 'name' => 'Asia', 'countries' => 'India, Singapore, Malaysia', 'fee' => 2000, 'time' => '5-7 days'],
    ['id' => 4, 'name' => 'Europe', 'countries' => 'UK, Germany, France', 'fee' => 3500, 'time' => '7-14 days'],
];

// Sample payment methods
$payment_methods = [
    ['id' => 1, 'name' => 'Credit/Debit Card', 'type' => 'stripe', 'enabled' => true],
    ['id' => 2, 'name' => 'PayPal', 'type' => 'paypal', 'enabled' => true],
    ['id' => 3, 'name' => 'Bank Transfer', 'type' => 'bank', 'enabled' => true],
    ['id' => 4, 'name' => 'Cash on Delivery', 'type' => 'cod', 'enabled' => true],
];

// Check for messages from session
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Management - SpiceCeylon Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
            --spice-purple: #9b59b6;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--spice-dark) 0%, #1a252f 100%);
            min-height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 14px 20px;
            margin: 4px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(184, 92, 56, 0.2);
            border-left-color: var(--spice-red);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(184, 92, 56, 0.2), rgba(39, 174, 96, 0.1));
        }
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--spice-blue);
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .settings-section {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(45deg, var(--spice-blue), var(--spice-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .section-icon.red {
            background: linear-gradient(45deg, var(--spice-red), #d35400);
        }
        
        .section-icon.green {
            background: linear-gradient(45deg, var(--spice-green), #219653);
        }
        
        .section-icon.orange {
            background: linear-gradient(45deg, var(--spice-gold), #e67e22);
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--spice-dark);
            border-bottom-color: var(--spice-blue);
            background: transparent;
        }
        
        .tab-content {
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
            padding: 30px;
            margin-top: -1px;
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .zone-card {
            background: #f8f9fa;
            border-left: 4px solid var(--spice-green);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .zone-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.8;
        }
        
        .payment-method-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .payment-method-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--spice-green);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .currency-badge {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(39, 174, 96, 0.15);
            color: var(--spice-green);
        }
        
        .status-inactive {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(155, 89, 182, 0.05));
            border-left: 4px solid var(--spice-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--spice-dark);">
                                <i class="fas fa-cogs me-2" style="color: var(--spice-blue);"></i>
                                Website Management
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-sliders-h me-1"></i> 
                                Configure website settings, shipping, and payment options
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'info-circle'; ?> me-2"></i> 
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Settings Tabs -->
                <div class="analytics-card">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                                <i class="fas fa-globe me-2"></i> General Settings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button">
                                <i class="fas fa-shipping-fast me-2"></i> Shipping Management
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button">
                                <i class="fas fa-credit-card me-2"></i> Payment Methods
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="settingsTabsContent">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <form method="POST">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Basic Configuration</h4>
                                            <p class="text-muted mb-0">Set up basic website information</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Website Name</label>
                                                <input type="text" class="form-control" name="site_name" 
                                                       value="<?php echo htmlspecialchars($site_name); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Contact Email</label>
                                                <input type="email" class="form-control" name="site_email" 
                                                       value="<?php echo htmlspecialchars($site_email); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Contact Phone</label>
                                                <input type="text" class="form-control" name="site_phone" 
                                                       value="<?php echo htmlspecialchars($site_phone); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Default Currency</label>
                                                <select class="form-select" name="currency">
                                                    <option value="LKR" <?php echo $currency == 'LKR' ? 'selected' : ''; ?>>Sri Lankan Rupee (LKR)</option>
                                                    <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                    <option value="EUR" <?php echo $currency == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                    <option value="GBP" <?php echo $currency == 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                                    <option value="INR" <?php echo $currency == 'INR' ? 'selected' : ''; ?>>Indian Rupee (INR)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Business Address</label>
                                                <textarea class="form-control" name="site_address" rows="3"><?php echo htmlspecialchars($site_address); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon red">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Maintenance Mode</h4>
                                            <p class="text-muted mb-0">Temporarily take your site offline for maintenance</p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">Enable Maintenance Mode</h6>
                                                <p class="text-muted small mb-0">
                                                    When enabled, only administrators can access the website
                                                </p>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <h6><i class="fas fa-info-circle me-2"></i> Maintenance Mode Info</h6>
                                        <p class="small text-muted mb-0">
                                            When maintenance mode is active, customers will see a "Temporarily Unavailable" message.
                                            Administrators can still access all admin features.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="save_general" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-1"></i> Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Shipping Management Tab -->
                        <div class="tab-pane fade" id="shipping" role="tabpanel">
                            <form method="POST">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon green">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Shipping Configuration</h4>
                                            <p class="text-muted mb-0">Set up shipping rates and policies</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-card">
                                                <h6 class="mb-3">
                                                    <i class="fas fa-truck me-2" style="color: var(--spice-green);"></i>
                                                    Free Shipping
                                                </h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Minimum Order for Free Shipping</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="free_shipping_min" 
                                                               value="<?php echo $free_shipping_min; ?>" min="0" step="100">
                                                    </div>
                                                    <small class="text-muted">Set to 0 to disable free shipping</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-card">
                                                <h6 class="mb-3">
                                                    <i class="fas fa-money-bill-wave me-2" style="color: var(--spice-blue);"></i>
                                                    Standard Shipping Fees
                                                </h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Local Shipping Fee (Within Sri Lanka)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="local_shipping_fee" 
                                                               value="<?php echo $local_shipping_fee; ?>" min="0" step="50">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">International Shipping Fee</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="international_shipping_fee" 
                                                               value="<?php echo $international_shipping_fee; ?>" min="0" step="100">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mt-4">
                                        <button type="submit" name="save_shipping" class="btn btn-primary px-4">
                                            <i class="fas fa-save me-1"></i> Save Shipping Settings
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon orange">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Shipping Zones</h4>
                                            <p class="text-muted mb-0">Configure shipping zones with different rates</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Existing Shipping Zones -->
                                    <h6 class="mb-3">
                                        <i class="fas fa-list me-2" style="color: var(--spice-purple);"></i>
                                        Existing Shipping Zones
                                    </h6>
                                    
                                    <?php foreach($shipping_zones as $zone): ?>
                                    <div class="zone-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-2"><?php echo htmlspecialchars($zone['name']); ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-globe-asia me-1"></i>
                                                    <?php echo htmlspecialchars($zone['countries']); ?>
                                                </p>
                                                <div class="d-flex gap-3">
                                                    <span class="text-primary">
                                                        <i class="fas fa-money-bill-wave me-1"></i>
                                                        Rs. <?php echo number_format($zone['fee'], 0); ?>
                                                    </span>
                                                    <span class="text-success">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo htmlspecialchars($zone['time']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editZone(<?php echo $zone['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteZone(<?php echo $zone['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Add New Shipping Zone -->
                                    <h6 class="mt-4 mb-3">
                                        <i class="fas fa-plus-circle me-2" style="color: var(--spice-green);"></i>
                                        Add New Shipping Zone
                                    </h6>
                                    
                                    <div class="form-card">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Zone Name</label>
                                                    <input type="text" class="form-control" name="zone_name" placeholder="e.g., Europe Zone" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Shipping Fee</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="shipping_fee" value="0" min="0" step="100" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Countries</label>
                                                    <textarea class="form-control" name="countries" rows="2" placeholder="e.g., UK, Germany, France, Italy" required></textarea>
                                                    <small class="text-muted">Enter countries separated by commas</small>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Estimated Delivery Time</label>
                                                    <input type="text" class="form-control" name="delivery_time" placeholder="e.g., 7-14 business days" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" name="add_shipping_zone" class="btn btn-success px-4">
                                            <i class="fas fa-plus me-1"></i> Add Shipping Zone
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Payment Methods Tab -->
                        <div class="tab-pane fade" id="payment" role="tabpanel">
                            <form method="POST">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon purple">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Payment Method Configuration</h4>
                                            <p class="text-muted mb-0">Enable/disable payment methods</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Method Cards -->
                                    <div class="row">
                                        <?php foreach($payment_methods as $method): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="payment-method-card">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <?php if($method['type'] == 'stripe'): ?>
                                                                <div class="me-3" style="color: #6772e5;">
                                                                    <i class="fab fa-cc-stripe fa-2x"></i>
                                                                </div>
                                                            <?php elseif($method['type'] == 'paypal'): ?>
                                                                <div class="me-3" style="color: #003087;">
                                                                    <i class="fab fa-cc-paypal fa-2x"></i>
                                                                </div>
                                                            <?php elseif($method['type'] == 'bank'): ?>
                                                                <div class="me-3" style="color: var(--spice-blue);">
                                                                    <i class="fas fa-university fa-2x"></i>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="me-3" style="color: var(--spice-green);">
                                                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($method['name']); ?></h6>
                                                                <span class="status-badge <?php echo $method['enabled'] ? 'status-active' : 'status-inactive'; ?>">
                                                                    <?php echo $method['enabled'] ? 'Active' : 'Inactive'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <p class="small text-muted mb-2">
                                                            <?php if($method['type'] == 'stripe'): ?>
                                                                Secure online payments via credit/debit cards
                                                            <?php elseif($method['type'] == 'paypal'): ?>
                                                                Pay securely with PayPal account or credit card
                                                            <?php elseif($method['type'] == 'bank'): ?>
                                                                Direct bank transfer to our account
                                                            <?php else: ?>
                                                                Pay cash when you receive your order
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" 
                                                               name="<?php echo $method['type']; ?>_enabled" 
                                                               <?php echo $method['enabled'] ? 'checked' : ''; ?>
                                                               style="width: 3em; height: 1.5em;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="text-end mt-4">
                                        <button type="submit" name="save_payment" class="btn btn-primary px-4">
                                            <i class="fas fa-save me-1"></i> Save Payment Settings
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon" style="background: linear-gradient(45deg, #27ae60, #219653);">
                                            <i class="fas fa-plus-circle"></i>
                                        </div>
                                        <div>
                                            <h4 class="mb-1">Add Custom Payment Method</h4>
                                            <p class="text-muted mb-0">Add additional payment options</p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-card">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Payment Method Name</label>
                                                    <input type="text" class="form-control" name="method_name" placeholder="e.g., Mobile Wallet" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Payment Type</label>
                                                    <select class="form-select" name="method_type" required>
                                                        <option value="">Select type</option>
                                                        <option value="online">Online Payment</option>
                                                        <option value="wallet">Mobile Wallet</option>
                                                        <option value="bank">Bank Transfer</option>
                                                        <option value="cod">Cash on Delivery</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Credentials/Configuration (Optional)</label>
                                                    <textarea class="form-control" name="credentials" rows="3" placeholder="API keys, account details, or special instructions..."></textarea>
                                                    <small class="text-muted">For internal use only. This information is encrypted.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <h6><i class="fas fa-shield-alt me-2"></i> Payment Security</h6>
                                        <p class="small text-muted mb-0">
                                            All payment credentials are encrypted and stored securely. 
                                            Regular security audits are performed to ensure PCI compliance.
                                        </p>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" name="add_payment_method" class="btn btn-success px-4">
                                            <i class="fas fa-plus me-1"></i> Add Payment Method
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tabs
        const triggerTabList = document.querySelectorAll('#settingsTabs button')
        triggerTabList.forEach(triggerEl => {
            const tabTrigger = new bootstrap.Tab(triggerEl)
            triggerEl.addEventListener('click', event => {
                event.preventDefault()
                tabTrigger.show()
            })
        });
        
        function editZone(id) {
            alert('Edit shipping zone ' + id + ' - Feature coming soon!');
        }
        
        function deleteZone(id) {
            if(confirm('Are you sure you want to delete this shipping zone?')) {
                // AJAX call to delete zone
                fetch('delete_shipping_zone.php?id=' + id, {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('Shipping zone deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting zone: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting zone: ' + error);
                });
            }
        }
        
        // Toggle switch styling
        document.querySelectorAll('.form-check-input').forEach(switchEl => {
            switchEl.addEventListener('change', function() {
                const card = this.closest('.payment-method-card');
                const statusBadge = card.querySelector('.status-badge');
                if(this.checked) {
                    statusBadge.classList.remove('status-inactive');
                    statusBadge.classList.add('status-active');
                    statusBadge.textContent = 'Active';
                } else {
                    statusBadge.classList.remove('status-active');
                    statusBadge.classList.add('status-inactive');
                    statusBadge.textContent = 'Inactive';
                }
            });
        });
    </script>
</body>
</html>