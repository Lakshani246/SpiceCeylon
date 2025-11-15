# Spice Ceylon 🌶️

A Smart Web-Based E-Commerce and Sales Forecasting System for Sri Lankan Spice Producers

## 📋 Project Overview

Spice Ceylon is a comprehensive web platform that connects Sri Lankan spice farmers directly with global customers. The system features role-based dashboards, sales forecasting, and real-time analytics to modernize the spice trade.

## 🚀 Features

### Customer Features
- **Landing Page** - Attractive homepage with promotional video and login/register
- **Product Browsing** - Browse spices by category and price
- **Shopping Cart & Checkout** - Secure shopping experience
- **Order Management** - Track orders and view history
- **Product Requests** - Request new spice varieties
- **Messaging System** - Communicate with admin

### Farmer Features
- **Farmer Dashboard** - Sales performance and earnings overview
- **Product Management** - Add and manage spice products
- **Request Approval** - Handle customer product requests
- **Inventory Tracking** - Monitor stock levels

### Admin Features
- **Admin Dashboard** - Comprehensive platform oversight
- **User Management** - Manage customers and farmers
- **Sales Analytics** - Real-time sales data visualization
- **Sales Forecasting** - AI-powered demand predictions
- **Message Broadcasting** - Send updates to customers

## 🛠️ Technology Stack

- **Frontend**: PHP, HTML5, CSS3, JavaScript, Bootstrap
- **Backend**: PHP (Server-side scripting)
- **Database**: MySQL
- **Authentication**: Custom PHP auth system
- **Analytics**: Chart.js for data visualization
- **Hosting**: Apache with XAMPP/WAMP

## 📁 Project Structure
SpiceCeylon/
│
├── index.php # Landing page (video + login/register)
│
├── config/
│ ├── db.php # Database configuration
│ ├── auth_check.php # Authentication middleware
│ └── functions.php # Utility functions
│
├── auth/
│ ├── login.php # User login system
│ ├── register.php # User registration
│ ├── logout.php # Session logout
│ └── auth.css # Authentication styles
│
├── customer/
│ ├── home.php # FIRST PAGE after login (spices grid)
│ ├── dashboard.php # Profile + orders + requests + messages
│ ├── browse.php # Product browsing
│ ├── cart.php # Shopping cart
│ ├── checkout.php # Order checkout
│ ├── orders.php # Order history
│ ├── request_product.php # Product requests
│ ├── messages.php # Customer messaging
│ ├── profile.php # User profile management
│ └── *.css # Customer-specific styles
│
├── farmer/
│ ├── dashboard.php # Farmer overview
│ ├── add_product.php # Add new products
│ ├── manage_products.php # Product management
│ ├── approve_requests.php # Handle customer requests
│ └── *.php # Header/footer components
│
├── admin/
│ ├── dashboard.php # Admin control panel
│ ├── manage_users.php # User management
│ ├── manage_products.php # Product oversight
│ ├── approve_requests.php # Request approval
│ ├── messages.php # Admin messaging system
│ ├── sales_analytics.php # Sales data visualization
│ ├── forecast_sales.php # Sales forecasting
│ └── *.php # Header/footer components
│
├── analytics/
│ ├── sales_report.php # Sales reporting
│ └── forecast_sales.php # Forecasting algorithms
│
├── assets/
│ ├── css/
│ │ ├── main.css # Global styles
│ │ ├── landing.css # Landing page styles
│ │ ├── admin.css # Admin panel styles
│ │ ├── farmer.css # Farmer dashboard styles
│ │ └── customer.css # Customer area styles
│ ├── js/ # JavaScript files
│ ├── images/ # Product and UI images
│ │ └── profile_images/ # User profile pictures
│ └── videos/
│ └── landing-video.mp4 # Promotional video
│
└── README.md



