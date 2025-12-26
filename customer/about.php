<?php
session_start();
include "../config/db.php";

// Ensure user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_query = $conn->query("SELECT COUNT(*) as count FROM cart WHERE customer_id = '{$_SESSION['user_id']}'");
    $cart_count = $cart_query->fetch_assoc()['count'];
}

// Get about page content from database
$hero_content = [];
$story_content = [];
$mission_content = [];
$values_content = [];
$timeline_content = [];
$stats_content = [];

// Get all about page content from database
$content_query = $conn->query("SELECT * FROM page_content WHERE page_name = 'about'");
if ($content_query) {
    while($row = $content_query->fetch_assoc()) {
        if ($row['section'] == 'hero') {
            $hero_content = $row;
        } elseif ($row['section'] == 'story') {
            $story_content = $row;
        } elseif ($row['section'] == 'mission') {
            $mission_content = $row;
        } elseif ($row['section'] == 'values') {
            $values_content = $row;
        } elseif ($row['section'] == 'timeline') {
            $timeline_content = $row;
        } elseif ($row['section'] == 'stats') {
            $stats_content = $row;
        }
    }
}

// Get team members from database
$team_members = [];
$team_query = $conn->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY display_order");
if ($team_query) {
    while($team = $team_query->fetch_assoc()) {
        $team_members[] = $team;
    }
}

// Set defaults if no database content
if (empty($hero_content)) {
    $hero_content = [
        'title' => 'About SpiceCeylon', 
        'content' => 'Connecting farmers with spice lovers worldwide'
    ];
}

if (empty($story_content)) {
    $story_content = [
        'title' => 'Our Story', 
        'content' => 'Founded in 2020, SpiceCeylon began with a simple vision...',
        'image' => '../assets/images/about/story-default.jpg'
    ];
}

if (empty($mission_content)) {
    $mission_content = [
        'title' => 'Our Mission',
        'content' => 'To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.',
        'image' => '../assets/images/about/mission-default.jpg'
    ];
}

if (empty($values_content)) {
    $values_content = [
        'title' => 'Our Values',
        'content' => json_encode([
            ['icon' => 'fa-leaf', 'title' => 'Authenticity', 'desc' => '100% pure Sri Lankan spices'],
            ['icon' => 'fa-handshake', 'title' => 'Fair Trade', 'desc' => 'Direct from farmers, fair prices'],
            ['icon' => 'fa-seedling', 'title' => 'Sustainability', 'desc' => 'Eco-friendly farming practices'],
            ['icon' => 'fa-heart', 'title' => 'Quality', 'desc' => 'Rigorous quality checks']
        ])
    ];
}

if (empty($timeline_content)) {
    $timeline_content = [
        'title' => 'Our Journey',
        'content' => json_encode([
            ['year' => '2020', 'title' => 'The Beginning', 'desc' => 'SpiceCeylon founded with 5 partner farmers in Kandy', 'image' => '../assets/images/about/timeline-2020.jpg'],
            ['year' => '2021', 'title' => 'Expansion', 'desc' => 'Expanded to 50 farmers across 3 regions. Launched e-commerce platform', 'image' => '../assets/images/about/timeline-2021.jpg'],
            ['year' => '2022', 'title' => 'International', 'desc' => 'Started exporting to 10 countries. Received organic certification', 'image' => '../assets/images/about/timeline-2022.jpg'],
            ['year' => '2023', 'title' => 'Today', 'desc' => '200+ farmers, 50+ spice varieties, serving customers worldwide', 'image' => '../assets/images/about/timeline-2023.jpg']
        ])
    ];
}

if (empty($stats_content)) {
    $stats_content = [
        'title' => 'Our Stats',
        'content' => json_encode([
            ['number' => '200+', 'label' => 'Partner Farmers'],
            ['number' => '50+', 'label' => 'Spice Varieties'],
            ['number' => '10,000+', 'label' => 'Happy Customers'],
            ['number' => '25+', 'label' => 'Countries Served']
        ])
    ];
}

if (empty($team_members)) {
    $team_members = [
        ['name' => 'Kumar Perera', 'role' => 'Founder & CEO', 'bio' => 'Former agricultural officer with 15+ years experience', 'image' => '../assets/images/team/team1.jpg'],
        ['name' => 'Anjali Fernando', 'role' => 'Head of Operations', 'bio' => 'Expert in supply chain management', 'image' => '../assets/images/team/team2.jpg'],
        ['name' => 'Rajith Silva', 'role' => 'Farmer Relations', 'bio' => 'Working directly with 200+ farmers', 'image' => '../assets/images/team/team3.jpg']
    ];
}

// Decode JSON content
$values_data = json_decode($values_content['content'], true);
$timeline_data = json_decode($timeline_content['content'], true);
$stats_data = json_decode($stats_content['content'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpiceCeylon - About Us</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --spice-red: #b85c38;
            --spice-dark: #2c3e50;
            --spice-green: #27ae60;
            --spice-gold: #f39c12;
            --spice-blue: #3498db;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
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
        
        .nav-link:hover {
            color: var(--spice-red) !important;
        }
        
        .about-hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('../assets/images/about-hero.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 60px;
        }
        
        .about-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .about-hero p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        .section-title {
            text-align: center;
            margin: 60px 0 40px;
            color: var(--spice-dark);
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--spice-red);
        }
        
        .about-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .value-card {
            text-align: center;
            padding: 30px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
        }
        
        .value-icon {
            width: 70px;
            height: 70px;
            background: rgba(184, 92, 56, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .value-icon i {
            font-size: 2rem;
            color: var(--spice-red);
        }
        
        .value-card h4 {
            color: var(--spice-dark);
            margin-bottom: 15px;
        }
        
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 40px auto;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--spice-red);
            transform: translateX(-50%);
        }
        
        .timeline-item {
            margin-bottom: 40px;
            position: relative;
            width: 45%;
        }
        
        .timeline-item:nth-child(odd) {
            margin-left: 0;
            margin-right: auto;
            padding-right: 40px;
        }
        
        .timeline-item:nth-child(even) {
            margin-left: auto;
            margin-right: 0;
            padding-left: 40px;
        }
        
        .timeline-dot {
            width: 20px;
            height: 20px;
            background: var(--spice-red);
            border-radius: 50%;
            position: absolute;
            top: 0;
        }
        
        .timeline-item:nth-child(odd) .timeline-dot {
            right: -10px;
        }
        
        .timeline-item:nth-child(even) .timeline-dot {
            left: -10px;
        }
        
        .team-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .team-card:hover {
            transform: translateY(-10px);
        }
        
        .team-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .team-info {
            padding: 25px;
        }
        
        .team-info h4 {
            color: var(--spice-dark);
            margin-bottom: 5px;
        }
        
        .team-role {
            color: var(--spice-red);
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .stat-card {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--spice-red);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--spice-dark);
            font-weight: 500;
        }
        
        footer {
            background: var(--spice-dark);
            color: white;
            padding: 50px 0 20px;
            margin-top: 80px;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .story-image, .mission-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .timeline-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .about-hero {
                padding: 60px 0;
            }
            
            .about-hero h1 {
                font-size: 2.5rem;
            }
            
            .timeline:before {
                left: 20px;
            }
            
            .timeline-item {
                width: calc(100% - 50px);
                margin-left: 50px !important;
                margin-right: 0 !important;
                padding-left: 20px !important;
                padding-right: 0 !important;
            }
            
            .timeline-item:nth-child(odd) .timeline-dot,
            .timeline-item:nth-child(even) .timeline-dot {
                left: -40px;
            }
            
            .story-image, .mission-image {
                height: 300px;
            }
            
            .timeline-image {
                height: 150px;
            }
        }
        
        .story-content {
            line-height: 1.8;
            font-size: 1.1rem;
        }
        
        .story-content p {
            margin-bottom: 1.5rem;
        }
        
        .mission-content {
            font-size: 1.1rem;
            line-height: 1.8;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
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
                    <li class="nav-item"><a class="nav-link" href="request.php">Request</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About Us</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="wishlist.php"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="about-hero" >
        <div class="container">
            <h1><?php echo htmlspecialchars($hero_content['title']); ?></h1>
            <p><?php echo htmlspecialchars($hero_content['content']); ?></p>
            <a href="#story" class="btn btn-lg" style="background: var(--spice-red); color: white; padding: 12px 30px; border-radius: 30px;">
                Our Story <i class="fas fa-arrow-down ms-2"></i>
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Our Story -->
        <section id="story" class="about-content">
            <h2 class="section-title"><?php echo htmlspecialchars($story_content['title']); ?></h2>
            <div class="row align-items-center">
                <div class="col-md-6">
                    <?php if(!empty($story_content['image'])): ?>
                        <img src="<?php echo htmlspecialchars($story_content['image']); ?>" 
                             alt="<?php echo htmlspecialchars($story_content['title']); ?>" 
                             class="story-image">
                    <?php else: ?>
                        <div class="story-image bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-image fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="story-content">
                        <?php echo $story_content['content']; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission -->
        <section class="about-content">
            <h2 class="section-title"><?php echo htmlspecialchars($mission_content['title']); ?></h2>
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="mission-content">
                        <?php echo htmlspecialchars($mission_content['content']); ?>
                    </div>
                    <div class="value-icon mb-4" style="margin-left: 0;">
                        <i class="fas fa-bullseye"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if(!empty($mission_content['image'])): ?>
                        <img src="<?php echo htmlspecialchars($mission_content['image']); ?>" 
                             alt="<?php echo htmlspecialchars($mission_content['title']); ?>" 
                             class="mission-image">
                    <?php else: ?>
                        <div class="mission-image bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-image fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <?php if($stats_data && is_array($stats_data)): ?>
        <div class="stats-grid">
            <?php foreach($stats_data as $stat): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo htmlspecialchars($stat['number']); ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Values -->
        <section class="about-content">
            <h2 class="section-title"><?php echo htmlspecialchars($values_content['title']); ?></h2>
            <div class="row">
                <?php 
                if($values_data && is_array($values_data)): 
                foreach($values_data as $value): 
                ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas <?php echo $value['icon']; ?>"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($value['title']); ?></h4>
                        <p><?php echo htmlspecialchars($value['desc']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Timeline -->
        <?php if($timeline_data && is_array($timeline_data)): ?>
        <section class="about-content" style="background-color: #f9f2e9; border-radius: 10px;"> <!-- Soft beige for a warm, natural feel -->
            <h2 class="section-title">Our Journey</h2>
            <div class="timeline">
                <?php foreach($timeline_data as $index => $item): ?>
                <div class="timeline-item <?php echo ($index % 2 == 0) ? '' : 'even'; ?>">
                    <div class="timeline-dot"></div>
                    <div class="card">
                        <div class="card-body">
                            <?php if(isset($item['image']) && !empty($item['image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['year'] . ' - ' . $item['title']); ?>" 
                                     class="timeline-image">
                            <?php endif; ?>
                            <h5 class="card-title" style="color: var(--spice-red);">
                                <?php echo htmlspecialchars($item['year']); ?> - <?php echo htmlspecialchars($item['title']); ?>
                            </h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['desc']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Team -->
        <?php if(!empty($team_members)): ?>
        <section class="about-content">
            <h2 class="section-title">Meet Our Team</h2>
            <div class="row">
                <?php foreach($team_members as $member): ?>
                <div class="col-md-4">
                    <div class="team-card">
                        <?php if(!empty($member['image']) && file_exists($member['image'])): ?>
                            <img src="<?php echo htmlspecialchars($member['image']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" class="team-image">
                        <?php else: ?>
                            <div class="team-image bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="team-info">
                            <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                            <div class="team-role"><?php echo htmlspecialchars($member['role']); ?></div>
                            <p><?php echo htmlspecialchars($member['bio']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Call to Action -->
        <section class="text-center my-5 py-5" style="background: linear-gradient(135deg, var(--spice-red), var(--spice-gold)); color: white; border-radius: 15px;">
            <h2 class="mb-4">Join Our Spice Journey</h2>
            <p class="mb-4" style="font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Experience authentic Sri Lankan spices and support sustainable farming practices.</p>
            <a href="home.php" class="btn btn-lg" style="background: white; color: var(--spice-red); padding: 12px 40px; border-radius: 30px;">
                Shop Now <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </section>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">SpiceCeylon</h4>
                    <p>Bringing authentic Sri Lankan spices directly from farmers to your kitchen since 2020.</p>
                    <div class="mt-3">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Quick Links</h4>
                    <div class="footer-links">
                        <a href="home.php">Home</a>
                        <a href="products.php">Products</a>
                        <a href="cart.php">Shopping Cart</a>
                        <a href="orders.php">My Orders</a>
                        <a href="about.php">About Us</a>
                        <a href="contact.php">Contact Us</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h4 class="mb-3">Contact Us</h4>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Colombo, Sri Lanka</p>
                    <p><i class="fas fa-phone me-2"></i> +94 11 234 5678</p>
                    <p><i class="fas fa-envelope me-2"></i> info@spiceceylon.com</p>
                    <p><i class="fas fa-clock me-2"></i> Mon-Sat: 8:00 AM - 6:00 PM</p>
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
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>