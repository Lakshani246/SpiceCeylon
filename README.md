# 🌶️ SpiceCeylon - Smart E-Commerce & Sales Forecasting System for Sri Lankan Spice Producers

A comprehensive web-based platform connecting Sri Lankan spice farmers directly with global customers, featuring AI-powered sales forecasting and real-time analytics.

## 📋 About The Project

SpiceCeylon revolutionizes the traditional spice trade by eliminating middlemen and providing a direct farmer-to-customer marketplace. The system features role-based dashboards, real-time inventory management, integrated messaging, and machine learning-based sales forecasting to help farmers make data-driven decisions.

## 🚀 Key Features

### Customer Features
- **Product Browsing**: Browse spices by category, price, and popularity with advanced filtering
- **Shopping Cart**: Real-time price calculation with multiple package sizes
- **Secure Checkout**: Shipping fee calculation based on zones with multiple payment options
- **Order Tracking**: Real-time order status updates with history tracking
- **Product Requests**: Request specific spice varieties from farmers
- **Wishlist Management**: Save favorite products for future purchases
- **Messaging System**: Direct communication with farmers and admins
- **Reviews & Ratings**: Leave feedback on purchased products

### Farmer Features
- **Farmer Dashboard**: Comprehensive overview of sales, earnings, and performance metrics
- **Product Management**: Complete CRUD operations for spice products with image upload
- **Inventory Tracking**: Real-time stock monitoring with low-stock alerts
- **Order Management**: View and update orders containing their products
- **Customer Requests**: Handle and fulfill customer product requests
- **Sales Analytics**: Interactive charts for sales trends and top products
- **Earnings Monitor**: Track income with payment history
- **Sales Forecasting**: AI-powered demand predictions using Python ML

### Admin Features
- **Admin Dashboard**: Complete platform oversight with key metrics
- **User Management**: Manage customers, farmers, and admins with status controls
- **Product Approval**: Review and approve farmer products with rejection reasons
- **Order Management**: Full control over all orders with status updates
- **Request Management**: Assign customer requests to appropriate farmers
- **Sales Analytics**: Real-time sales data visualization with charts
- **Sales Forecasting**: AI-powered demand predictions for business planning
- **Content Management**: Edit website content (home, about, FAQ, contact)
- **Website Settings**: Configure shipping zones, payment methods, and social links
- **Announcements**: Send targeted broadcasts to specific user groups
- **Messaging Hub**: Monitor all platform communications

### System Features
- **Real-time Notifications**: Instant updates for orders, messages, and status changes
- **Auto-sync Settings**: Configuration changes reflect immediately on all pages
- **Beautiful Popups**: Centered success/error/confirmation modals with animations
- **CSV/PDF Export**: Export sales reports and analytics data
- **Sales Forecasting**: Python-based ML predictions with historical data
- **Responsive Design**: Fully responsive on desktop, tablet, and mobile
- **Role-based Access**: Secure authentication with different permission levels

## 🛠️ Built With

### Backend
- **PHP**: 8.2 - Server-side scripting language
- **MySQL**: 8.0 - Relational database management
- **Python**: 3.10 - Machine learning for sales forecasting
- **Apache**: Web server

### Frontend
- **HTML5/CSS3**: Modern semantic markup and styling
- **JavaScript**: Client-side interactivity
- **Bootstrap**: 5.3 - Responsive UI framework
- **Chart.js**: Interactive data visualization
- **Font Awesome**: 6.0 - Icon library
- **Summernote**: Rich text editor for content management
- **jQuery**: DOM manipulation and AJAX calls

### Libraries & Tools
- **SheetJS (XLSX)**: Excel file export
- **jsPDF**: PDF report generation
- **Chart.js**: Real-time charts and graphs
- **MySQLi**: Database connectivity
- **Python ML Libraries**: pandas, numpy, scikit-learn, matplotlib

## 📁 Project Structure
spiceceylon/
├── admin/ - Admin area
│ ├── dashboard.php - Admin dashboard
│ ├── manage_users.php - User management
│ ├── manage_products.php - Product approval
│ ├── manage_orders.php - Order management
│ ├── manage_requests.php - Request management
│ ├── website_management.php - Website settings
│ ├── content_editor.php - Page content editor
│ └── messaging_hub.php - Message center
│
├── farmer/ - Farmer area
│ ├── dashboard.php - Farmer dashboard
│ ├── manage_products.php - Product management
│ ├── orders.php - Order management
│ ├── customer_requests.php - Request handling
│ ├── my_sales.php - Sales analytics
│ ├── forecasting.php - Sales forecasting
│ └── earnings.php - Earnings monitor
│
├── customer/ - Customer area
│ ├── home.php - Product browsing
│ ├── cart.php - Shopping cart
│ ├── checkout.php - Checkout process
│ ├── orders.php - Order history
│ ├── wishlist.php - Saved products
│ ├── request.php - Product requests
│ └── messages.php - Messaging
│
├── auth/ - Authentication
│ ├── login.php - User login
│ ├── register.php - User registration
│ ├── logout.php - Logout with confirmation
│ └── forgot_password.php - Password recovery
│
├── config/ - Configuration
│ ├── db.php - Database connection
│ ├── settings.php - Settings helper
│ └── functions.php - Global functions
│
├── assets/ - Static assets
│ ├── css/ - Stylesheets
│ ├── js/ - JavaScript files
│ └── images/ - Images and uploads
│ ├── products/ - Product images
│ ├── profile_images/ - User profile pictures
│ ├── about/ - About page images
│ └── team/ - Team member photos
│
├── videos/ - Landing page video
│
├── forecast/ - Machine Learning
│ ├── forecast_model.py - Python prediction model
│ ├── train_model.py - Model training script
│ └── requirements.txt - Python dependencies
│
└── database/ - Database files
└── spiceceylon.sql - Complete database schema

text

---

## 🔧 Installation Guide

### Prerequisites

XAMPP/WAMP/LAMP with PHP 8.2+, MySQL 8.0+, Python 3.10+ (for forecasting), Web browser (Chrome/Firefox/Edge), Git (for version control)

### Step-by-Step Setup

**1. Clone the Repository**
git clone https://github.com/Lakshani246/SpiceCeylon.git
cd SpiceCeylon

text

**2. Configure Database**
-- Open phpMyAdmin (http://localhost/phpmyadmin)
-- Create new database
CREATE DATABASE spiceceylon_db;

-- Import database schema
-- Navigate to database/spiceceylon.sql and import

text

**3. Configure Database Connection**
Edit `config/db.php` with your database credentials:
```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "spiceceylon_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
text

**4. Configure Python Environment (for forecasting)**

text
cd forecast
pip install -r requirements.txt
5. Set File Permissions (Linux/Mac)

text
chmod -R 755 assets/images/
chmod -R 755 assets/images/products/
chmod -R 755 assets/images/profile_images/
6. Start XAMPP/WAMP - Start Apache and MySQL services

**7. Access the Application**

text
http://localhost/SpiceCeylon/
🔑 Default Login Credentials
Super Admin: jk@gmail.com / admin123 - Full system access

Farmer: d@gmail.com / farmer123 - Product management

Farmer: r@gmail.com / farmer123 - Second farmer account

Customer: s@gmail.com / customer123 - Regular customer

Customer: peter@gmail.com / customer123 - Second customer account

💻 Usage Guide
For Customers
Browse Products: Navigate to home page to view all available spices

Add to Cart: Select quantity and package size, add to shopping cart

Checkout: Enter shipping details and select payment method

Track Orders: View order status in real-time

Request Products: Submit requests for specific spice varieties

Contact Farmers: Use messaging system for inquiries

For Farmers
Dashboard: View sales metrics and pending requests

Add Products: Upload new spices with descriptions and images

Manage Inventory: Update stock levels and prices

View Orders: See orders containing your products

Update Status: Change order status (Processing → Shipped → Delivered)

Handle Requests: Accept/reject customer product requests

View Analytics: Analyze sales trends and top products

Forecast Demand: Use ML predictions for inventory planning

For Admins
Dashboard: Monitor platform-wide metrics

User Management: Approve/reject farmer registrations

Product Approval: Review and approve new products

Order Management: Oversee all orders

Request Assignment: Assign customer requests to farmers

Content Management: Edit website pages

Settings: Configure shipping zones and payment methods

Announcements: Send notifications to users

📊 Database Schema
Core Tables
users: 6+ records - User accounts (customers, farmers)

admins: 1 record - Admin accounts

products: 44+ records - Spice products

orders: 11+ records - Customer orders

order_items: 19+ records - Items in each order

cart: Variable records - Shopping cart items

wishlist: 11+ records - Customer wishlists

Content Tables
page_content: 33+ records - Homepage, About page content

faq_items: 4 records - FAQ questions and answers

contact_info: 4 records - Contact details

team_members: 3 records - Team member information

System Tables
settings: 34+ records - Website settings

shipping_zones: 5+ records - Shipping zones and rates

payment_methods: 4 records - Available payment methods

notifications: 16+ records - User notifications

messages: 12+ records - User-to-user messages

announcements: 4 records - Admin announcements

🤖 Machine Learning Forecasting
Sales Prediction Model
forecast/forecast_model.py - Algorithm: Linear Regression / Random Forest, Features: Historical sales, seasonal patterns, Output: Next 3-6 months demand prediction, Accuracy: ~85% on test data

Running Forecasts
Navigate to farmer dashboard, Click on "Sales Forecasting", Select product and forecast period, View interactive chart with predictions, Export forecast data as CSV

🔒 Security Features
Password Hashing: BCrypt for secure password storage

Session Management: PHP sessions with timeout

SQL Injection Prevention: Prepared statements for all queries

CSRF Protection: Token-based form validation

XSS Prevention: Input sanitization and output escaping

Role-based Access: Different permissions for each user type

Secure File Uploads: Validation for image uploads


🤝 Contributing
This is a university project and is not open for external contributions. However, feel free to fork and adapt for your own use.

📝 License
This project is developed for educational purposes as part of academic requirements

👨‍💻 Developer
Lakshani246 - Final Year Project, BSc (Hons) in Software Engineering, 

🙏 Acknowledgments
Project Supervisor: For guidance and feedback

Spice Farmers: For providing requirements and feedback

Open Source Community: For libraries and tools used

Bootstrap Team: For the responsive framework

Chart.js Contributors: For visualization libraries