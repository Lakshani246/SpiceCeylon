<?php
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
include '../config/settings.php';

$admin_id = $_SESSION['admin_id'];

// Get admin data
$admin_query = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Save general settings
    if ($_POST['ajax_action'] == 'save_general') {
        $settings = [
            'site_name' => $_POST['site_name'] ?? 'SpiceCeylon',
            'site_email' => $_POST['site_email'] ?? '',
            'site_phone' => $_POST['site_phone'] ?? '',
            'site_address' => $_POST['site_address'] ?? '',
            'currency' => $_POST['currency'] ?? 'LKR',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'meta_description' => $_POST['meta_description'] ?? 'Authentic Sri Lankan spices',
            'meta_keywords' => $_POST['meta_keywords'] ?? 'ceylon spices, organic spices',
            'facebook' => $_POST['facebook'] ?? '',
            'instagram' => $_POST['instagram'] ?? '',
            'twitter' => $_POST['twitter'] ?? '',
            'youtube' => $_POST['youtube'] ?? '',
            'linkedin' => $_POST['linkedin'] ?? ''
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("ss", $key, $value);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'General settings saved successfully!' : 'Error saving general settings'
        ]);
        exit;
    }
    
    // Save shipping settings
    if ($_POST['ajax_action'] == 'save_shipping') {
        $settings = [
            'free_shipping_min' => $_POST['free_shipping_min'] ?? '0',
            'local_shipping_fee' => $_POST['local_shipping_fee'] ?? '0',
            'international_shipping_fee' => $_POST['international_shipping_fee'] ?? '0'
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("ss", $key, $value);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Shipping settings saved successfully!' : 'Error saving shipping settings'
        ]);
        exit;
    }
    
    // Save payment settings
    if ($_POST['ajax_action'] == 'save_payment') {
        $methods = ['stripe', 'paypal', 'bank', 'cod'];
        $success = true;
        
        foreach ($methods as $method) {
            $enabled = isset($_POST[$method . '_enabled']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE payment_methods SET is_enabled = ? WHERE method_type = ?");
            $stmt->bind_param("is", $enabled, $method);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Payment settings saved successfully!' : 'Error saving payment settings'
        ]);
        exit;
    }
    
    // Save shipping zone
    if ($_POST['ajax_action'] == 'save_shipping_zone') {
        $zone_id = intval($_POST['zone_id'] ?? 0);
        $zone_name = $_POST['zone_name'] ?? '';
        $countries = $_POST['countries'] ?? '';
        $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);
        $delivery_time = $_POST['delivery_time'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($zone_id > 0) {
            $stmt = $conn->prepare("UPDATE shipping_zones SET zone_name = ?, countries = ?, shipping_fee = ?, delivery_time = ?, is_active = ? WHERE zone_id = ?");
            $stmt->bind_param("ssdsii", $zone_name, $countries, $shipping_fee, $delivery_time, $is_active, $zone_id);
            $action = "updated";
        } else {
            $stmt = $conn->prepare("INSERT INTO shipping_zones (zone_name, countries, shipping_fee, delivery_time, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsi", $zone_name, $countries, $shipping_fee, $delivery_time, $is_active);
            $action = "added";
        }
        
        if ($stmt->execute()) {
            if ($zone_id > 0) {
                // Get updated data
                $result = $conn->query("SELECT * FROM shipping_zones WHERE zone_id = $zone_id");
                $data = $result->fetch_assoc();
            } else {
                $zone_id = $stmt->insert_id;
                $data = [
                    'zone_id' => $zone_id,
                    'zone_name' => $zone_name,
                    'countries' => $countries,
                    'shipping_fee' => $shipping_fee,
                    'delivery_time' => $delivery_time,
                    'is_active' => $is_active
                ];
            }
            echo json_encode([
                'success' => true,
                'message' => "Shipping zone $action successfully!",
                'data' => $data
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving shipping zone']);
        }
        exit;
    }
    
    // Delete shipping zone
    if ($_POST['ajax_action'] == 'delete_shipping_zone') {
        $zone_id = intval($_POST['zone_id']);
        $stmt = $conn->prepare("DELETE FROM shipping_zones WHERE zone_id = ?");
        $stmt->bind_param("i", $zone_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Shipping zone deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting shipping zone']);
        }
        exit;
    }
    
    // Get shipping zone
    if ($_POST['ajax_action'] == 'get_shipping_zone') {
        $zone_id = intval($_POST['zone_id']);
        $stmt = $conn->prepare("SELECT * FROM shipping_zones WHERE zone_id = ?");
        $stmt->bind_param("i", $zone_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Zone not found']);
        }
        exit;
    }
}

// Load settings for display
$site_name = Settings::get('site_name', 'SpiceCeylon');
$site_email = Settings::get('site_email', 'info@spiceceylon.com');
$site_phone = Settings::get('site_phone', '+94 11 234 5678');
$site_address = Settings::get('site_address', 'Colombo, Sri Lanka');
$currency = Settings::get('currency', 'LKR');
$maintenance_mode = Settings::get('maintenance_mode', '0');
$meta_description = Settings::get('meta_description', 'Authentic Sri Lankan spices');
$meta_keywords = Settings::get('meta_keywords', 'ceylon spices, organic spices');
$facebook = Settings::get('facebook', '');
$instagram = Settings::get('instagram', '');
$twitter = Settings::get('twitter', '');
$youtube = Settings::get('youtube', '');
$linkedin = Settings::get('linkedin', '');

$free_shipping_min = Settings::get('free_shipping_min', '5000');
$local_shipping_fee = Settings::get('local_shipping_fee', '500');
$international_shipping_fee = Settings::get('international_shipping_fee', '2500');

// Get shipping zones
$shipping_zones = Settings::getShippingZones();
$all_zones = $conn->query("SELECT * FROM shipping_zones ORDER BY zone_name");

// Get payment methods
$payment_methods = Settings::getPaymentMethods();
$all_methods = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order");
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
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 25px;
            transition: all 0.3s;
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
        
        .settings-section {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 25px;
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
        
        .section-icon.red { background: linear-gradient(45deg, var(--spice-red), #d35400); }
        .section-icon.green { background: linear-gradient(45deg, var(--spice-green), #219653); }
        .section-icon.orange { background: linear-gradient(45deg, var(--spice-gold), #e67e22); }
        
        .zone-card {
            background: #f8f9fa;
            border-left: 4px solid var(--spice-green);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .zone-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        
        /* Popup Modal Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            display: none;
        }
        
        .popup-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            padding: 30px 40px;
            text-align: center;
            z-index: 9999;
            display: none;
            max-width: 400px;
            width: 90%;
        }
        
        .popup-modal.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .popup-overlay.show {
            display: block;
        }
        
        .popup-modal.success { border-top: 5px solid var(--spice-green); }
        .popup-modal.error { border-top: 5px solid var(--spice-red); }
        
        .popup-modal i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .popup-modal.success i { color: var(--spice-green); }
        .popup-modal.error i { color: var(--spice-red); }
        
        .popup-modal h5 {
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: var(--spice-dark);
        }
        
        .popup-modal p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .popup-modal .btn {
            padding: 8px 30px;
            border-radius: 20px;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        /* Confirmation Modal */
        .confirm-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .confirm-modal .modal-header {
            background: linear-gradient(135deg, var(--spice-red), #c0392b);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        
        .confirm-modal .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirm-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 15px 30px;
        }
        
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(155, 89, 182, 0.05));
            border-left: 4px solid var(--spice-blue);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
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
                                <i class="fas fa-globe me-1"></i> 
                                Changes update automatically on customer and farmer websites
                            </p>
                        </div>
                        <div class="text-white p-2 px-4 rounded" style="background: var(--spice-blue);">
                            <i class="fas fa-sync-alt me-2"></i> Auto-Sync Enabled
                        </div>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <div class="analytics-card">
                    <ul class="nav nav-tabs" id="settingsTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general">
                                <i class="fas fa-globe me-2"></i> General
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#shipping">
                                <i class="fas fa-truck me-2"></i> Shipping
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payment">
                                <i class="fas fa-credit-card me-2"></i> Payment
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#social">
                                <i class="fas fa-share-alt me-2"></i> Social Media
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general">
                            <form id="generalForm">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                                        <div>
                                            <h4 class="mb-1">Basic Information</h4>
                                            <p class="text-muted mb-0">These appear throughout the website</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">Website Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">Contact Email</label>
                                            <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($site_email); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">Contact Phone</label>
                                            <input type="text" class="form-control" name="site_phone" value="<?php echo htmlspecialchars($site_phone); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">Currency</label>
                                            <select class="form-select" name="currency">
                                                <option value="LKR" <?php echo $currency == 'LKR' ? 'selected' : ''; ?>>Sri Lankan Rupee (LKR)</option>
                                                <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                <option value="EUR" <?php echo $currency == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                <option value="GBP" <?php echo $currency == 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                            </select>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">Business Address</label>
                                            <textarea class="form-control" name="site_address" rows="2"><?php echo htmlspecialchars($site_address); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon red"><i class="fas fa-tools"></i></div>
                                        <div>
                                            <h4 class="mb-1">Maintenance Mode</h4>
                                            <p class="text-muted mb-0">Temporarily take site offline</p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">Enable Maintenance Mode</h6>
                                                <p class="text-muted small mb-0">Only admins can access the site</p>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="maintenance_mode" <?php echo $maintenance_mode == '1' ? 'checked' : ''; ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon green"><i class="fas fa-search"></i></div>
                                        <div>
                                            <h4 class="mb-1">SEO Settings</h4>
                                            <p class="text-muted mb-0">For search engines</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">Meta Description</label>
                                            <textarea class="form-control" name="meta_description" rows="2"><?php echo htmlspecialchars($meta_description); ?></textarea>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">Meta Keywords</label>
                                            <input type="text" class="form-control" name="meta_keywords" value="<?php echo htmlspecialchars($meta_keywords); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary px-4" onclick="saveGeneral()">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Shipping Settings Tab -->
                        <div class="tab-pane fade" id="shipping">
                            <form id="shippingForm">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon orange"><i class="fas fa-truck"></i></div>
                                        <div>
                                            <h4 class="mb-1">Shipping Configuration</h4>
                                            <p class="text-muted mb-0">Global shipping settings</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-card">
                                                <h6 class="mb-3">Free Shipping Threshold</h6>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rs.</span>
                                                    <input type="number" class="form-control" name="free_shipping_min" value="<?php echo $free_shipping_min; ?>">
                                                </div>
                                                <small class="text-muted">Set 0 to disable free shipping</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-card">
                                                <h6 class="mb-3">Standard Shipping Fees</h6>
                                                <div class="mb-2">
                                                    <label class="form-label">Local (Sri Lanka)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="local_shipping_fee" value="<?php echo $local_shipping_fee; ?>">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="form-label">International</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">Rs.</span>
                                                        <input type="number" class="form-control" name="international_shipping_fee" value="<?php echo $international_shipping_fee; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end mb-4">
                                        <button type="button" class="btn btn-primary px-4" onclick="saveShipping()">
                                            <i class="fas fa-save me-1"></i> Save Shipping Settings
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon purple"><i class="fas fa-map-marked-alt"></i></div>
                                        <div>
                                            <h4 class="mb-1">Shipping Zones</h4>
                                            <p class="text-muted mb-0">Add/edit shipping zones</p>
                                        </div>
                                    </div>
                                    
                                    <div id="shippingZonesList">
                                        <?php if($all_zones->num_rows == 0): ?>
                                            <div class="text-center text-muted py-4">
                                                <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                                                <p>No shipping zones yet. Click "Add Shipping Zone" to create one.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php while($zone = $all_zones->fetch_assoc()): ?>
                                            <div class="zone-card <?php echo $zone['is_active'] ? '' : 'inactive'; ?>" id="zone-<?php echo $zone['zone_id']; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-2">
                                                            <?php echo htmlspecialchars($zone['zone_name']); ?>
                                                            <?php if(!$zone['is_active']): ?>
                                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <p class="small text-muted mb-2">
                                                            <i class="fas fa-globe-asia me-1"></i>
                                                            <?php echo htmlspecialchars($zone['countries']); ?>
                                                        </p>
                                                        <div class="d-flex gap-3">
                                                            <span class="text-primary">
                                                                <i class="fas fa-money-bill-wave me-1"></i>
                                                                Rs. <?php echo number_format($zone['shipping_fee'], 0); ?>
                                                            </span>
                                                            <span class="text-success">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo htmlspecialchars($zone['delivery_time']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-icon" onclick="editZone(<?php echo $zone['zone_id']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-icon" onclick="deleteZone(<?php echo $zone['zone_id']; ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <button type="button" class="btn btn-success" onclick="addZone()">
                                            <i class="fas fa-plus me-1"></i> Add Shipping Zone
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Payment Settings Tab -->
                        <div class="tab-pane fade" id="payment">
                            <form id="paymentForm">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon green"><i class="fas fa-credit-card"></i></div>
                                        <div>
                                            <h4 class="mb-1">Payment Methods</h4>
                                            <p class="text-muted mb-0">Enable/disable payment options</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <?php 
                                        $methods_result = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order");
                                        while($method = $methods_result->fetch_assoc()): 
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="payment-method-card">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <?php if($method['method_type'] == 'stripe'): ?>
                                                            <i class="fab fa-cc-stripe fa-2x me-3" style="color: #6772e5;"></i>
                                                        <?php elseif($method['method_type'] == 'paypal'): ?>
                                                            <i class="fab fa-cc-paypal fa-2x me-3" style="color: #003087;"></i>
                                                        <?php elseif($method['method_type'] == 'bank'): ?>
                                                            <i class="fas fa-university fa-2x me-3" style="color: var(--spice-blue);"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-money-bill-wave fa-2x me-3" style="color: var(--spice-green);"></i>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($method['method_name']); ?></h6>
                                                            <span class="status-badge <?php echo $method['is_enabled'] ? 'status-active' : 'status-inactive'; ?>">
                                                                <?php echo $method['is_enabled'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="<?php echo $method['method_type']; ?>_enabled" 
                                                               <?php echo $method['is_enabled'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="button" class="btn btn-primary px-4" onclick="savePayment()">
                                            <i class="fas fa-save me-1"></i> Save Payment Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Social Media Tab -->
                        <div class="tab-pane fade" id="social">
                            <form id="socialForm">
                                <div class="settings-section">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="section-icon orange"><i class="fas fa-share-alt"></i></div>
                                        <div>
                                            <h4 class="mb-1">Social Media Links</h4>
                                            <p class="text-muted mb-0">These appear in the website footer</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="fab fa-facebook text-primary me-2"></i> Facebook
                                            </label>
                                            <input type="url" class="form-control" name="facebook" value="<?php echo htmlspecialchars($facebook); ?>" placeholder="https://facebook.com/...">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="fab fa-instagram text-danger me-2"></i> Instagram
                                            </label>
                                            <input type="url" class="form-control" name="instagram" value="<?php echo htmlspecialchars($instagram); ?>" placeholder="https://instagram.com/...">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="fab fa-twitter text-info me-2"></i> Twitter
                                            </label>
                                            <input type="url" class="form-control" name="twitter" value="<?php echo htmlspecialchars($twitter); ?>" placeholder="https://twitter.com/...">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="fab fa-youtube text-danger me-2"></i> YouTube
                                            </label>
                                            <input type="url" class="form-control" name="youtube" value="<?php echo htmlspecialchars($youtube); ?>" placeholder="https://youtube.com/...">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn
                                            </label>
                                            <input type="url" class="form-control" name="linkedin" value="<?php echo htmlspecialchars($linkedin); ?>" placeholder="https://linkedin.com/...">
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Social media links will appear in the website footer automatically.
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="button" class="btn btn-primary px-4" onclick="saveSocial()">
                                            <i class="fas fa-save me-1"></i> Save Social Links
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

    <!-- Shipping Zone Modal -->
    <div class="modal fade" id="zoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-map-marked-alt me-2"></i> <span id="zoneModalTitle">Add Shipping Zone</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="zoneForm">
                    <div class="modal-body">
                        <input type="hidden" name="zone_id" id="zone_id" value="0">
                        <input type="hidden" name="ajax_action" value="save_shipping_zone">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Zone Name</label>
                            <input type="text" class="form-control" name="zone_name" id="zone_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Countries</label>
                            <textarea class="form-control" name="countries" id="countries" rows="2" required></textarea>
                            <small class="text-muted">Separate with commas (e.g., UK, Germany, France)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Shipping Fee (Rs.)</label>
                            <input type="number" class="form-control" name="shipping_fee" id="shipping_fee" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Delivery Time</label>
                            <input type="text" class="form-control" name="delivery_time" id="delivery_time" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="saveZone()">Save Zone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade confirm-modal" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="fas fa-trash-alt fa-4x text-danger mb-3"></i>
                    <h5 id="confirmMessage">Delete this shipping zone?</h5>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Popup -->
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="popup-modal" id="popupModal">
        <i class="fas fa-check-circle" id="popupIcon"></i>
        <h5 id="popupTitle">Success!</h5>
        <p id="popupMessage">Settings saved successfully.</p>
        <button class="btn btn-success" onclick="closePopup()">OK</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDeleteId = null;
        
        // Show popup message
        function showPopup(type, message) {
            const popup = document.getElementById('popupModal');
            const overlay = document.getElementById('popupOverlay');
            const icon = document.getElementById('popupIcon');
            const title = document.getElementById('popupTitle');
            const msg = document.getElementById('popupMessage');
            
            popup.className = 'popup-modal ' + type;
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            title.textContent = type === 'success' ? 'Success!' : 'Error!';
            msg.textContent = message;
            
            popup.classList.add('show');
            overlay.classList.add('show');
            
            setTimeout(closePopup, 3000);
        }
        
        function closePopup() {
            document.getElementById('popupModal').classList.remove('show');
            document.getElementById('popupOverlay').classList.remove('show');
        }
        
        // Save general settings
        function saveGeneral() {
            const formData = new FormData(document.getElementById('generalForm'));
            formData.append('ajax_action', 'save_general');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving settings');
            });
        }
        
        // Save shipping settings
        function saveShipping() {
            const formData = new FormData(document.getElementById('shippingForm'));
            formData.append('ajax_action', 'save_shipping');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving settings');
            });
        }
        
        // Save payment settings
        function savePayment() {
            const formData = new FormData(document.getElementById('paymentForm'));
            formData.append('ajax_action', 'save_payment');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving settings');
            });
        }
        
        // Save social settings
        function saveSocial() {
            const formData = new FormData(document.getElementById('socialForm'));
            formData.append('ajax_action', 'save_general');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving settings');
            });
        }
        
        // Add new zone
        function addZone() {
            document.getElementById('zoneModalTitle').textContent = 'Add Shipping Zone';
            document.getElementById('zone_id').value = '0';
            document.getElementById('zone_name').value = '';
            document.getElementById('countries').value = '';
            document.getElementById('shipping_fee').value = '';
            document.getElementById('delivery_time').value = '';
            document.getElementById('is_active').checked = true;
            
            new bootstrap.Modal(document.getElementById('zoneModal')).show();
        }
        
        // Edit zone
        function editZone(id) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax_action=get_shipping_zone&zone_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('zoneModalTitle').textContent = 'Edit Shipping Zone';
                    document.getElementById('zone_id').value = data.data.zone_id;
                    document.getElementById('zone_name').value = data.data.zone_name;
                    document.getElementById('countries').value = data.data.countries;
                    document.getElementById('shipping_fee').value = data.data.shipping_fee;
                    document.getElementById('delivery_time').value = data.data.delivery_time;
                    document.getElementById('is_active').checked = data.data.is_active == 1;
                    
                    new bootstrap.Modal(document.getElementById('zoneModal')).show();
                } else {
                    showPopup('error', 'Error loading zone data');
                }
            })
            .catch(error => {
                showPopup('error', 'Error loading zone data');
            });
        }
        
        // Save zone
        function saveZone() {
            const formData = new FormData(document.getElementById('zoneForm'));
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('zoneModal')).hide();
                    
                    // Update or add the zone in the list
                    if (data.data.zone_id) {
                        const zone = data.data;
                        const isActive = zone.is_active ? '' : 'inactive';
                        const inactiveBadge = !zone.is_active ? '<span class="badge bg-secondary ms-2">Inactive</span>' : '';
                        
                        const zoneHtml = `
                            <div class="zone-card ${isActive}" id="zone-${zone.zone_id}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-2">
                                            ${escapeHtml(zone.zone_name)}${inactiveBadge}
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            <i class="fas fa-globe-asia me-1"></i>
                                            ${escapeHtml(zone.countries)}
                                        </p>
                                        <div class="d-flex gap-3">
                                            <span class="text-primary">
                                                <i class="fas fa-money-bill-wave me-1"></i>
                                                Rs. ${Number(zone.shipping_fee).toLocaleString()}
                                            </span>
                                            <span class="text-success">
                                                <i class="fas fa-clock me-1"></i>
                                                ${escapeHtml(zone.delivery_time)}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-icon" onclick="editZone(${zone.zone_id})" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-icon" onclick="deleteZone(${zone.zone_id})" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        if (document.getElementById('zone-' + zone.zone_id)) {
                            // Update existing
                            document.getElementById('zone-' + zone.zone_id).outerHTML = zoneHtml;
                        } else {
                            // Add new
                            document.getElementById('shippingZonesList').insertAdjacentHTML('beforeend', zoneHtml);
                        }
                    }
                    
                    showPopup('success', data.message);
                } else {
                    showPopup('error', data.message);
                }
            })
            .catch(error => {
                showPopup('error', 'Error saving zone');
            });
        }
        
        // Escape HTML helper
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Delete zone
        function deleteZone(id) {
            currentDeleteId = id;
            
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmActionBtn').onclick = function() {
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'ajax_action=delete_shipping_zone&zone_id=' + currentDeleteId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const element = document.getElementById('zone-' + currentDeleteId);
                        if (element) {
                            element.remove();
                        }
                        showPopup('success', data.message);
                        
                        // Check if no zones left
                        if (document.getElementById('shippingZonesList').children.length === 0) {
                            document.getElementById('shippingZonesList').innerHTML = `
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                                    <p>No shipping zones yet. Click "Add Shipping Zone" to create one.</p>
                                </div>
                            `;
                        }
                    } else {
                        showPopup('error', data.message);
                    }
                    modal.hide();
                })
                .catch(error => {
                    showPopup('error', 'Error deleting zone');
                    modal.hide();
                });
            };
            modal.show();
        }
    </script>
</body>
</html>