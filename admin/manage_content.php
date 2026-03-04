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

// Create tables if they don't exist
$tables = [
    'page_content' => "CREATE TABLE IF NOT EXISTS page_content (
        content_id INT PRIMARY KEY AUTO_INCREMENT,
        page_name VARCHAR(50) NOT NULL,
        section VARCHAR(100) NOT NULL,
        title VARCHAR(255),
        content TEXT,
        image VARCHAR(255),
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    'team_members' => "CREATE TABLE IF NOT EXISTS team_members (
        member_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(100),
        bio TEXT,
        image VARCHAR(255),
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'faq_items' => "CREATE TABLE IF NOT EXISTS faq_items (
        faq_id INT PRIMARY KEY AUTO_INCREMENT,
        question VARCHAR(255) NOT NULL,
        answer TEXT NOT NULL,
        category VARCHAR(100),
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'contact_info' => "CREATE TABLE IF NOT EXISTS contact_info (
        contact_id INT PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(100),
        value TEXT NOT NULL,
        icon VARCHAR(50),
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach($tables as $table_name => $create_query) {
    $check = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($check->num_rows == 0) {
        $conn->query($create_query);
    }
}

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Save homepage
    if ($_POST['ajax_action'] == 'save_homepage') {
        $success = true;
        
        // Save hero section
        $hero_title = $_POST['hero_title'] ?? 'Welcome to SpiceCeylon';
        $hero_subtitle = $_POST['hero_subtitle'] ?? 'Discover authentic Sri Lankan spices';
        $hero_button = $_POST['hero_button'] ?? 'Shop Now';
        $hero_link = $_POST['hero_link'] ?? '#products';
        
        $stmt = $conn->prepare("INSERT INTO page_content (page_name, section, title, content) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW()");
        
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        
        $page = 'home'; $section = 'hero_title'; $title = 'Hero Title'; $content = $hero_title;
        $success = $success && $stmt->execute();
        
        $page = 'home'; $section = 'hero_subtitle'; $title = 'Hero Subtitle'; $content = $hero_subtitle;
        $success = $success && $stmt->execute();
        
        $page = 'home'; $section = 'hero_button'; $title = 'Hero Button'; $content = $hero_button;
        $success = $success && $stmt->execute();
        
        $page = 'home'; $section = 'hero_link'; $title = 'Hero Link'; $content = $hero_link;
        $success = $success && $stmt->execute();
        
        // Save features
        $conn->query("DELETE FROM page_content WHERE page_name = 'home' AND section LIKE 'feature_%'");
        
        for($i = 1; $i <= 4; $i++) {
            $feature_title = $_POST['feature_title_' . $i] ?? '';
            $feature_content = $_POST['feature_content_' . $i] ?? '';
            
            if (!empty($feature_title)) {
                $stmt = $conn->prepare("INSERT INTO page_content (page_name, section, title, content, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $page, $section, $title, $content, $order);
                $page = 'home'; $section = 'feature_' . $i; $title = $feature_title; $content = $feature_content; $order = $i;
                $success = $success && $stmt->execute();
            }
        }
        
        echo json_encode(['success' => $success, 'message' => $success ? 'Homepage saved successfully!' : 'Error saving homepage']);
        exit;
    }
    
    // Save about page
    if ($_POST['ajax_action'] == 'save_about') {
        $success = true;
        
        // Save hero
        $stmt = $conn->prepare("INSERT INTO page_content (page_name, section, title, content) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), updated_at = NOW()");
        
        $page = 'about'; $section = 'hero'; $title = $_POST['about_hero_title'] ?? 'About SpiceCeylon'; 
        $content = $_POST['about_hero_content'] ?? '';
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        // Save story
        $section = 'story'; $title = $_POST['about_story_title'] ?? 'Our Story';
        $content = $_POST['about_story_content'] ?? '';
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        // Save mission
        $section = 'mission'; $title = $_POST['about_mission_title'] ?? 'Our Mission';
        $content = $_POST['about_mission_content'] ?? '';
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        // Save values as JSON
        $values = [];
        for($i = 1; $i <= 4; $i++) {
            $values[] = [
                'icon' => $_POST['value_icon_' . $i] ?? 'fa-leaf',
                'title' => $_POST['value_title_' . $i] ?? '',
                'desc' => $_POST['value_desc_' . $i] ?? ''
            ];
        }
        $section = 'values'; $title = 'Our Values'; $content = json_encode($values);
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        // Save timeline as JSON
        $timeline = [];
        for($i = 1; $i <= 4; $i++) {
            if (!empty($_POST['timeline_year_' . $i])) {
                $timeline[] = [
                    'year' => $_POST['timeline_year_' . $i],
                    'title' => $_POST['timeline_title_' . $i],
                    'desc' => $_POST['timeline_desc_' . $i]
                ];
            }
        }
        $section = 'timeline'; $title = 'Our Journey'; $content = json_encode($timeline);
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        // Save stats as JSON
        $stats = [];
        for($i = 1; $i <= 4; $i++) {
            if (!empty($_POST['stat_number_' . $i])) {
                $stats[] = [
                    'number' => $_POST['stat_number_' . $i],
                    'label' => $_POST['stat_label_' . $i]
                ];
            }
        }
        $section = 'stats'; $title = 'Our Stats'; $content = json_encode($stats);
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        echo json_encode(['success' => $success, 'message' => $success ? 'About page saved successfully!' : 'Error saving about page']);
        exit;
    }
    
    // Save FAQ
    if ($_POST['ajax_action'] == 'save_faq') {
        $conn->query("DELETE FROM faq_items");
        $success = true;
        
        $count = intval($_POST['faq_count'] ?? 0);
        
        for($i = 1; $i <= $count; $i++) {
            $question = $_POST['faq_question_' . $i] ?? '';
            $answer = $_POST['faq_answer_' . $i] ?? '';
            $category = $_POST['faq_category_' . $i] ?? 'General';
            
            if (!empty($question) && !empty($answer)) {
                $stmt = $conn->prepare("INSERT INTO faq_items (question, answer, category, display_order) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $question, $answer, $category, $i);
                $success = $success && $stmt->execute();
            }
        }
        
        echo json_encode(['success' => $success, 'message' => $success ? 'FAQ saved successfully!' : 'Error saving FAQ']);
        exit;
    }
    
    // Save contact
    if ($_POST['ajax_action'] == 'save_contact') {
        $conn->query("DELETE FROM contact_info");
        $success = true;
        
        $contact_items = [
            ['type' => 'address', 'title' => 'Address', 'value' => $_POST['contact_address'] ?? '', 'icon' => 'fas fa-map-marker-alt', 'order' => 1],
            ['type' => 'phone', 'title' => 'Phone', 'value' => $_POST['contact_phone'] ?? '', 'icon' => 'fas fa-phone', 'order' => 2],
            ['type' => 'email', 'title' => 'Email', 'value' => $_POST['contact_email'] ?? '', 'icon' => 'fas fa-envelope', 'order' => 3],
            ['type' => 'hours', 'title' => 'Business Hours', 'value' => $_POST['contact_hours'] ?? '', 'icon' => 'fas fa-clock', 'order' => 4]
        ];
        
        // Add social media if provided
        if (!empty($_POST['contact_facebook'])) {
            $contact_items[] = ['type' => 'social', 'title' => 'Facebook', 'value' => $_POST['contact_facebook'], 'icon' => 'fab fa-facebook', 'order' => 5];
        }
        if (!empty($_POST['contact_instagram'])) {
            $contact_items[] = ['type' => 'social', 'title' => 'Instagram', 'value' => $_POST['contact_instagram'], 'icon' => 'fab fa-instagram', 'order' => 6];
        }
        if (!empty($_POST['contact_twitter'])) {
            $contact_items[] = ['type' => 'social', 'title' => 'Twitter', 'value' => $_POST['contact_twitter'], 'icon' => 'fab fa-twitter', 'order' => 7];
        }
        if (!empty($_POST['contact_youtube'])) {
            $contact_items[] = ['type' => 'social', 'title' => 'YouTube', 'value' => $_POST['contact_youtube'], 'icon' => 'fab fa-youtube', 'order' => 8];
        }
        
        foreach($contact_items as $item) {
            $stmt = $conn->prepare("INSERT INTO contact_info (type, title, value, icon, display_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $item['type'], $item['title'], $item['value'], $item['icon'], $item['order']);
            $success = $success && $stmt->execute();
        }
        
        // Save contact form titles
        $stmt = $conn->prepare("INSERT INTO page_content (page_name, section, title, content) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
        
        $page = 'contact'; $section = 'form_title'; $title = 'Contact Form Title'; $content = $_POST['contact_form_title'] ?? 'Get In Touch';
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        $section = 'form_subtitle'; $title = 'Contact Form Subtitle'; $content = $_POST['contact_form_subtitle'] ?? 'Have questions? Send us a message.';
        $stmt->bind_param("ssss", $page, $section, $title, $content);
        $success = $success && $stmt->execute();
        
        echo json_encode(['success' => $success, 'message' => $success ? 'Contact page saved successfully!' : 'Error saving contact page']);
        exit;
    }
}

// Function to get content
function get_content($conn, $page_name, $section = null) {
    if ($section) {
        $query = $conn->prepare("SELECT * FROM page_content WHERE page_name = ? AND section = ?");
        $query->bind_param("ss", $page_name, $section);
    } else {
        $query = $conn->prepare("SELECT * FROM page_content WHERE page_name = ? ORDER BY display_order");
        $query->bind_param("s", $page_name);
    }
    $query->execute();
    $result = $query->get_result();
    
    if ($section) {
        return $result->fetch_assoc();
    } else {
        $items = [];
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    }
}

// Function to get FAQ items
function get_faq_items($conn) {
    $result = $conn->query("SELECT * FROM faq_items WHERE status = 'active' ORDER BY display_order");
    $items = [];
    while($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}

// Function to get contact info
function get_contact_info($conn) {
    $result = $conn->query("SELECT * FROM contact_info WHERE status = 'active' ORDER BY display_order");
    $items = [];
    while($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}

// Get current content
$home_hero_title = get_content($conn, 'home', 'hero_title');
$home_hero_subtitle = get_content($conn, 'home', 'hero_subtitle');
$home_hero_button = get_content($conn, 'home', 'hero_button');
$home_hero_link = get_content($conn, 'home', 'hero_link');
$home_features = get_content($conn, 'home');

$about_hero = get_content($conn, 'about', 'hero');
$about_story = get_content($conn, 'about', 'story');
$about_mission = get_content($conn, 'about', 'mission');
$about_values = get_content($conn, 'about', 'values');
$about_timeline = get_content($conn, 'about', 'timeline');
$about_stats = get_content($conn, 'about', 'stats');

$faq_items = get_faq_items($conn);
$contact_info = get_contact_info($conn);
$contact_form_title = get_content($conn, 'contact', 'form_title');
$contact_form_subtitle = get_content($conn, 'contact', 'form_subtitle');

// Decode JSON data
$values_data = $about_values ? json_decode($about_values['content'], true) : [];
$timeline_data = $about_timeline ? json_decode($about_timeline['content'], true) : [];
$stats_data = $about_stats ? json_decode($about_stats['content'], true) : [];

// Extract contact info
$contact_address = '';
$contact_phone = '';
$contact_email = '';
$contact_hours = '';
$contact_facebook = '';
$contact_instagram = '';
$contact_twitter = '';
$contact_youtube = '';

foreach($contact_info as $item) {
    switch($item['type']) {
        case 'address': $contact_address = $item['value']; break;
        case 'phone': $contact_phone = $item['value']; break;
        case 'email': $contact_email = $item['value']; break;
        case 'hours': $contact_hours = $item['value']; break;
        case 'social':
            if ($item['title'] == 'Facebook') $contact_facebook = $item['value'];
            if ($item['title'] == 'Instagram') $contact_instagram = $item['value'];
            if ($item['title'] == 'Twitter') $contact_twitter = $item['value'];
            if ($item['title'] == 'YouTube') $contact_youtube = $item['value'];
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Editor - SpiceCeylon Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
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
        
        .content-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .content-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(45deg, var(--spice-red), #d35400);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--spice-dark);
            border-bottom-color: var(--spice-red);
            background: transparent;
        }
        
        .tab-content {
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
            padding: 25px;
            margin-top: -1px;
        }
        
        .icon-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .icon-option {
            width: 40px;
            height: 40px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .icon-option:hover, .icon-option.selected {
            border-color: var(--spice-red);
            background: rgba(184, 92, 56, 0.1);
            color: var(--spice-red);
        }
        
        .content-block {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .faq-item, .timeline-item, .feature-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--spice-blue);
        }
        
        .stat-input-group {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .preview-area {
            background: white;
            border: 2px dashed #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            min-height: 100px;
        }
        
        .btn-add-item {
            background: var(--spice-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .btn-add-item:hover {
            background: #219653;
            color: white;
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
        
        .auto-sync-badge {
            background: var(--spice-blue);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
        }
        
        .auto-sync-badge i {
            margin-right: 5px;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
                                <i class="fas fa-edit me-2" style="color: var(--spice-blue);"></i>
                                Content Editor
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Edit website content - Changes automatically update the customer website
                            </p>
                        </div>
                        <div class="auto-sync-badge">
                            <i class="fas fa-sync-alt"></i> Auto-Sync Enabled
                        </div>
                    </div>
                </div>

                <!-- Page Content Tabs -->
                <div class="analytics-card">
                    <div class="content-header d-flex align-items-center">
                        <div class="content-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Page Content Management</h4>
                            <p class="text-muted mb-0">Edit content for different pages on the website</p>
                            <small class="text-info">
                                <i class="fas fa-sync me-1"></i> Changes automatically update the customer website in real-time
                            </small>
                        </div>
                    </div>
                    
                    <ul class="nav nav-tabs" id="pageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button">
                                <i class="fas fa-home me-1"></i> Homepage
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button">
                                <i class="fas fa-info-circle me-1"></i> About Us
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button">
                                <i class="fas fa-question-circle me-1"></i> FAQ
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                                <i class="fas fa-address-book me-1"></i> Contact
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="pageTabsContent">
                        <!-- Homepage Tab -->
                        <div class="tab-pane fade show active" id="home">
                            <form id="homeForm">
                                <h5 class="mb-4" style="color: var(--spice-red);">
                                    <i class="fas fa-star me-2"></i>Hero Section
                                </h5>
                                
                                <div class="content-block">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Hero Title</label>
                                                <input type="text" class="form-control" name="hero_title" 
                                                       value="<?php echo $home_hero_title ? htmlspecialchars($home_hero_title['content']) : 'Welcome to SpiceCeylon'; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Hero Button Text</label>
                                                <input type="text" class="form-control" name="hero_button" 
                                                       value="<?php echo $home_hero_button ? htmlspecialchars($home_hero_button['content']) : 'Shop Now'; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Hero Subtitle</label>
                                                <textarea class="form-control" name="hero_subtitle" rows="2" required><?php echo $home_hero_subtitle ? htmlspecialchars($home_hero_subtitle['content']) : 'Discover authentic Sri Lankan spices directly from farmers. Fresh, organic, and fair trade.'; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Hero Button Link</label>
                                                <input type="text" class="form-control" name="hero_link" 
                                                       value="<?php echo $home_hero_link ? htmlspecialchars($home_hero_link['content']) : '#products'; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mb-4 mt-5" style="color: var(--spice-red);">
                                    <i class="fas fa-chart-line me-2"></i>Features/Stats
                                </h5>
                                
                                <div class="content-block">
                                    <?php 
                                    $feature_titles = ['Spice Varieties', 'Organic', 'From Farmers', 'Support'];
                                    $feature_contents = ['Fresh spices from Sri Lanka', '100% organic products', 'Direct from farmers', '24/7 customer support'];
                                    
                                    for($i = 1; $i <= 4; $i++): 
                                        $feature = array_filter($home_features, function($item) use ($i) {
                                            return $item['section'] == 'feature_' . $i;
                                        });
                                        $feature = $feature ? reset($feature) : null;
                                    ?>
                                    <div class="feature-item">
                                        <h6>Feature <?php echo $i; ?></h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label small">Feature Title</label>
                                                    <input type="text" class="form-control" name="feature_title_<?php echo $i; ?>" 
                                                           value="<?php echo $feature ? htmlspecialchars($feature['title']) : $feature_titles[$i-1]; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label small">Feature Content</label>
                                                    <input type="text" class="form-control" name="feature_content_<?php echo $i; ?>" 
                                                           value="<?php echo $feature ? htmlspecialchars($feature['content']) : $feature_contents[$i-1]; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-primary px-4" onclick="saveHomepage()">
                                        <i class="fas fa-save me-1"></i> Save Homepage
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- About Us Tab -->
                        <div class="tab-pane fade" id="about">
                            <form id="aboutForm">
                                <!-- Hero Section -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-star me-2"></i>Hero Section
                                    </h5>
                                    <div class="content-block">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Hero Title</label>
                                            <input type="text" class="form-control" name="about_hero_title" 
                                                   value="<?php echo $about_hero ? htmlspecialchars($about_hero['title']) : 'About SpiceCeylon'; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Hero Description</label>
                                            <textarea class="form-control" name="about_hero_content" rows="3" required><?php echo $about_hero ? htmlspecialchars($about_hero['content']) : 'Connecting farmers with spice lovers worldwide'; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Our Story -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-book me-2"></i>Our Story
                                    </h5>
                                    <div class="content-block">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Section Title</label>
                                            <input type="text" class="form-control" name="about_story_title" 
                                                   value="<?php echo $about_story ? htmlspecialchars($about_story['title']) : 'Our Story'; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Story Content</label>
                                            <textarea id="summernote-story" name="about_story_content" class="form-control" rows="8"><?php echo $about_story ? $about_story['content'] : 'Founded in 2020, SpiceCeylon began with a simple vision...'; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mission -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-bullseye me-2"></i>Our Mission
                                    </h5>
                                    <div class="content-block">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Mission Title</label>
                                            <input type="text" class="form-control" name="about_mission_title" 
                                                   value="<?php echo $about_mission ? htmlspecialchars($about_mission['title']) : 'Our Mission'; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Mission Statement</label>
                                            <textarea class="form-control" name="about_mission_content" rows="4" required><?php echo $about_mission ? htmlspecialchars($about_mission['content']) : 'To bridge the gap between Sri Lankan farmers and global consumers...'; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Values -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-heart me-2"></i>Our Values
                                    </h5>
                                    <div class="content-block">
                                        <div class="row">
                                            <?php 
                                            $default_values = [
                                                ['icon' => 'fa-leaf', 'title' => 'Authenticity', 'desc' => '100% pure Sri Lankan spices'],
                                                ['icon' => 'fa-handshake', 'title' => 'Fair Trade', 'desc' => 'Direct from farmers, fair prices'],
                                                ['icon' => 'fa-seedling', 'title' => 'Sustainability', 'desc' => 'Eco-friendly farming practices'],
                                                ['icon' => 'fa-heart', 'title' => 'Quality', 'desc' => 'Rigorous quality checks']
                                            ];
                                            
                                            for($i = 1; $i <= 4; $i++): 
                                                $value = isset($values_data[$i-1]) ? $values_data[$i-1] : $default_values[$i-1];
                                            ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-body">
                                                        <h6 class="card-title">Value <?php echo $i; ?></h6>
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small">Icon</label>
                                                            <div class="icon-picker">
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-leaf' ? 'selected' : ''; ?>" data-icon="fa-leaf" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-leaf"></i>
                                                                </div>
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-handshake' ? 'selected' : ''; ?>" data-icon="fa-handshake" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-handshake"></i>
                                                                </div>
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-seedling' ? 'selected' : ''; ?>" data-icon="fa-seedling" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-seedling"></i>
                                                                </div>
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-heart' ? 'selected' : ''; ?>" data-icon="fa-heart" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-heart"></i>
                                                                </div>
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-trophy' ? 'selected' : ''; ?>" data-icon="fa-trophy" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-trophy"></i>
                                                                </div>
                                                                <div class="icon-option <?php echo $value['icon'] == 'fa-users' ? 'selected' : ''; ?>" data-icon="fa-users" data-target="value_icon_<?php echo $i; ?>">
                                                                    <i class="fas fa-users"></i>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" id="value_icon_<?php echo $i; ?>" name="value_icon_<?php echo $i; ?>" value="<?php echo htmlspecialchars($value['icon']); ?>">
                                                        </div>
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small">Title</label>
                                                            <input type="text" class="form-control" name="value_title_<?php echo $i; ?>" 
                                                                   value="<?php echo htmlspecialchars($value['title']); ?>">
                                                        </div>
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small">Description</label>
                                                            <input type="text" class="form-control" name="value_desc_<?php echo $i; ?>" 
                                                                   value="<?php echo htmlspecialchars($value['desc']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Stats -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-chart-bar me-2"></i>Statistics
                                    </h5>
                                    <div class="content-block">
                                        <?php 
                                        $default_stats = [
                                            ['number' => '200+', 'label' => 'Partner Farmers'],
                                            ['number' => '50+', 'label' => 'Spice Varieties'],
                                            ['number' => '10,000+', 'label' => 'Happy Customers'],
                                            ['number' => '25+', 'label' => 'Countries Served']
                                        ];
                                        
                                        for($i = 1; $i <= 4; $i++): 
                                            $stat = isset($stats_data[$i-1]) ? $stats_data[$i-1] : $default_stats[$i-1];
                                        ?>
                                        <div class="stat-input-group">
                                            <h6>Stat <?php echo $i; ?></h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Number/Value</label>
                                                        <input type="text" class="form-control" name="stat_number_<?php echo $i; ?>" 
                                                               value="<?php echo htmlspecialchars($stat['number']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Label</label>
                                                        <input type="text" class="form-control" name="stat_label_<?php echo $i; ?>" 
                                                               value="<?php echo htmlspecialchars($stat['label']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <!-- Timeline -->
                                <div class="mb-5">
                                    <h5 class="mb-3" style="color: var(--spice-red);">
                                        <i class="fas fa-history me-2"></i>Timeline
                                    </h5>
                                    <div class="content-block">
                                        <?php 
                                        $default_timeline = [
                                            ['year' => '2020', 'title' => 'The Beginning', 'desc' => 'SpiceCeylon founded with 5 partner farmers in Kandy'],
                                            ['year' => '2021', 'title' => 'Expansion', 'desc' => 'Expanded to 50 farmers across 3 regions. Launched e-commerce platform'],
                                            ['year' => '2022', 'title' => 'International', 'desc' => 'Started exporting to 10 countries. Received organic certification'],
                                            ['year' => '2023', 'title' => 'Today', 'desc' => '200+ farmers, 50+ spice varieties, serving customers worldwide']
                                        ];
                                        
                                        for($i = 1; $i <= 4; $i++): 
                                            $timeline = isset($timeline_data[$i-1]) ? $timeline_data[$i-1] : $default_timeline[$i-1];
                                        ?>
                                        <div class="timeline-item">
                                            <h6>Timeline Item <?php echo $i; ?></h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Year</label>
                                                        <input type="text" class="form-control" name="timeline_year_<?php echo $i; ?>" 
                                                               value="<?php echo htmlspecialchars($timeline['year']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Title</label>
                                                        <input type="text" class="form-control" name="timeline_title_<?php echo $i; ?>" 
                                                               value="<?php echo htmlspecialchars($timeline['title']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Description</label>
                                                        <textarea class="form-control" name="timeline_desc_<?php echo $i; ?>" rows="2"><?php echo htmlspecialchars($timeline['desc']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-primary px-4" onclick="saveAbout()">
                                        <i class="fas fa-save me-1"></i> Save About Page
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- FAQ Tab -->
                        <div class="tab-pane fade" id="faq">
                            <form id="faqForm">
                                <h5 class="mb-4" style="color: var(--spice-red);">
                                    <i class="fas fa-question-circle me-2"></i>Frequently Asked Questions
                                </h5>
                                
                                <div id="faq-items">
                                    <?php 
                                    $faq_count = count($faq_items);
                                    if ($faq_count == 0) {
                                        $faq_items = [
                                            ['question' => 'What is SpiceCeylon?', 'answer' => 'SpiceCeylon is a platform connecting Sri Lankan farmers with spice lovers worldwide.', 'category' => 'General'],
                                            ['question' => 'Are your spices organic?', 'answer' => 'Yes, all our spices are 100% organic and grown without harmful chemicals.', 'category' => 'Quality'],
                                            ['question' => 'How do you ensure quality?', 'answer' => 'We work directly with farmers and have strict quality control measures.', 'category' => 'Quality'],
                                            ['question' => 'What shipping methods do you use?', 'answer' => 'We use reliable shipping partners to ensure timely delivery worldwide.', 'category' => 'Shipping']
                                        ];
                                        $faq_count = 4;
                                    }
                                    
                                    for($i = 0; $i < $faq_count; $i++): 
                                        $item = $faq_items[$i];
                                        $num = $i + 1;
                                    ?>
                                    <div class="faq-item" id="faq-<?php echo $num; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6>FAQ Item <?php echo $num; ?></h6>
                                            <?php if($num > 4): ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="removeFaqItem(<?php echo $num; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label small">Question</label>
                                                    <input type="text" class="form-control" name="faq_question_<?php echo $num; ?>" 
                                                           value="<?php echo htmlspecialchars($item['question']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label small">Answer</label>
                                                    <textarea class="form-control" name="faq_answer_<?php echo $num; ?>" rows="3" required><?php echo htmlspecialchars($item['answer']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label small">Category</label>
                                                    <select class="form-control" name="faq_category_<?php echo $num; ?>">
                                                        <option value="General" <?php echo $item['category'] == 'General' ? 'selected' : ''; ?>>General</option>
                                                        <option value="Quality" <?php echo $item['category'] == 'Quality' ? 'selected' : ''; ?>>Quality</option>
                                                        <option value="Shipping" <?php echo $item['category'] == 'Shipping' ? 'selected' : ''; ?>>Shipping</option>
                                                        <option value="Payment" <?php echo $item['category'] == 'Payment' ? 'selected' : ''; ?>>Payment</option>
                                                        <option value="Returns" <?php echo $item['category'] == 'Returns' ? 'selected' : ''; ?>>Returns</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                
                                <input type="hidden" id="faq-count" name="faq_count" value="<?php echo $faq_count; ?>">
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-success" onclick="addFaqItem()">
                                        <i class="fas fa-plus me-1"></i> Add FAQ Item
                                    </button>
                                    <button type="button" class="btn btn-primary px-4" onclick="saveFaq()">
                                        <i class="fas fa-save me-1"></i> Save FAQ
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Contact Tab -->
                        <div class="tab-pane fade" id="contact">
                            <form id="contactForm">
                                <h5 class="mb-4" style="color: var(--spice-red);">
                                    <i class="fas fa-address-book me-2"></i>Contact Information
                                </h5>
                                
                                <!-- Contact Details -->
                                <div class="content-block mb-4">
                                    <h6 class="mb-3">Contact Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="contact_address" rows="2" required><?php echo htmlspecialchars($contact_address); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Business Hours</label>
                                                <textarea class="form-control" name="contact_hours" rows="2" required><?php echo htmlspecialchars($contact_hours); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="contact_phone" 
                                                       value="<?php echo htmlspecialchars($contact_phone); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="contact_email" 
                                                       value="<?php echo htmlspecialchars($contact_email); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Social Media -->
                                <div class="content-block mb-4">
                                    <h6 class="mb-3">Social Media Links</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Facebook URL</label>
                                                <input type="url" class="form-control" name="contact_facebook" 
                                                       value="<?php echo htmlspecialchars($contact_facebook); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Instagram URL</label>
                                                <input type="url" class="form-control" name="contact_instagram" 
                                                       value="<?php echo htmlspecialchars($contact_instagram); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Twitter/X URL</label>
                                                <input type="url" class="form-control" name="contact_twitter" 
                                                       value="<?php echo htmlspecialchars($contact_twitter); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">YouTube URL</label>
                                                <input type="url" class="form-control" name="contact_youtube" 
                                                       value="<?php echo htmlspecialchars($contact_youtube); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Form -->
                                <div class="content-block mb-4">
                                    <h6 class="mb-3">Contact Form</h6>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Contact Form Title</label>
                                                <input type="text" class="form-control" name="contact_form_title" 
                                                       value="<?php echo $contact_form_title ? htmlspecialchars($contact_form_title['content']) : 'Get In Touch'; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Contact Form Subtitle</label>
                                                <textarea class="form-control" name="contact_form_subtitle" rows="2" required><?php echo $contact_form_subtitle ? htmlspecialchars($contact_form_subtitle['content']) : 'Have questions? Send us a message and we\'ll get back to you soon.'; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Preview -->
                                <div class="content-block">
                                    <h6 class="mb-3">Contact Page Preview</h6>
                                    <div class="preview-area">
                                        <h5><?php echo $contact_form_title ? htmlspecialchars($contact_form_title['content']) : 'Get In Touch'; ?></h5>
                                        <p class="text-muted"><?php echo $contact_form_subtitle ? htmlspecialchars($contact_form_subtitle['content']) : 'Have questions? Send us a message and we\'ll get back to you soon.'; ?></p>
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($contact_address ?: 'Colombo, Sri Lanka'); ?></p>
                                                <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($contact_phone ?: '+94 11 234 5678'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($contact_email ?: 'info@spiceceylon.com'); ?></p>
                                                <p><i class="fas fa-clock me-2"></i> <?php echo htmlspecialchars($contact_hours ?: 'Mon-Sat: 8:00 AM - 6:00 PM'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-primary px-4" onclick="saveContact()">
                                        <i class="fas fa-save me-1"></i> Save Contact Page
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Popup -->
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="popup-modal" id="popupModal">
        <i class="fas fa-check-circle" id="popupIcon"></i>
        <h5 id="popupTitle">Success!</h5>
        <p id="popupMessage">Content saved successfully.</p>
        <button class="btn btn-success" onclick="closePopup()">OK</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Summernote
            $('#summernote-story').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview']]
                ],
                callbacks: {
                    onChange: function(contents) {
                        // Update the textarea value for AJAX submission
                        $('#summernote-story').val(contents);
                    }
                }
            });
            
            // Icon picker functionality
            $('.icon-option').click(function() {
                const icon = $(this).data('icon');
                const target = $(this).data('target');
                
                $(this).parent().find('.icon-option').removeClass('selected');
                $(this).addClass('selected');
                $('#' + target).val(icon);
            });
            
            // Initialize icon selection
            $('.icon-option').each(function() {
                const target = $(this).data('target');
                const currentIcon = $('#' + target).val();
                if ($(this).data('icon') === currentIcon) {
                    $(this).addClass('selected');
                }
            });
        });
        
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
        
        // Save homepage
        function saveHomepage() {
            const formData = new FormData(document.getElementById('homeForm'));
            formData.append('ajax_action', 'save_homepage');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving homepage');
            });
        }
        
        // Save about page
        function saveAbout() {
            // Get summernote content
            const storyContent = $('#summernote-story').summernote('code');
            
            const formData = new FormData(document.getElementById('aboutForm'));
            formData.set('about_story_content', storyContent);
            formData.append('ajax_action', 'save_about');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving about page');
            });
        }
        
        // Save FAQ
        function saveFaq() {
            const formData = new FormData(document.getElementById('faqForm'));
            formData.append('ajax_action', 'save_faq');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving FAQ');
            });
        }
        
        // Save contact
        function saveContact() {
            const formData = new FormData(document.getElementById('contactForm'));
            formData.append('ajax_action', 'save_contact');
            
            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                showPopup(data.success ? 'success' : 'error', data.message);
            })
            .catch(error => {
                showPopup('error', 'Error saving contact page');
            });
        }
        
        function addFaqItem() {
            const faqItems = $('#faq-items');
            const currentCount = parseInt($('#faq-count').val());
            const newCount = currentCount + 1;
            
            const newItem = `
                <div class="faq-item" id="faq-${newCount}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6>FAQ Item ${newCount}</h6>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFaqItem(${newCount})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label small">Question</label>
                                <input type="text" class="form-control" name="faq_question_${newCount}" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label small">Answer</label>
                                <textarea class="form-control" name="faq_answer_${newCount}" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label small">Category</label>
                                <select class="form-control" name="faq_category_${newCount}">
                                    <option value="General">General</option>
                                    <option value="Quality">Quality</option>
                                    <option value="Shipping">Shipping</option>
                                    <option value="Payment">Payment</option>
                                    <option value="Returns">Returns</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            faqItems.append(newItem);
            $('#faq-count').val(newCount);
        }
        
        function removeFaqItem(id) {
            if (confirm('Delete this FAQ item?')) {
                $(`#faq-${id}`).remove();
                
                // Update count and renumber remaining items
                let count = 0;
                $('.faq-item').each(function(index) {
                    count++;
                    $(this).find('h6').text(`FAQ Item ${count}`);
                    
                    // Update input names
                    $(this).find('input, textarea, select').each(function() {
                        let name = $(this).attr('name');
                        if (name) {
                            name = name.replace(/faq_(question|answer|category)_\d+/, `faq_$1_${count}`);
                            $(this).attr('name', name);
                        }
                    });
                    
                    // Update IDs and onclick
                    $(this).attr('id', `faq-${count}`);
                    $(this).find('.btn-danger').attr('onclick', `removeFaqItem(${count})`);
                });
                
                $('#faq-count').val(count);
            }
        }
    </script>
</body>
</html>