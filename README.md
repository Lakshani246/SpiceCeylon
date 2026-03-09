# 🌶️ SpiceCeylon - Smart E-Commerce & Sales Forecasting System for Sri Lankan Spice Producers

A comprehensive web-based platform connecting Sri Lankan spice farmers directly with global customers, featuring AI-powered sales forecasting and real-time analytics.

---

## 📋 About The Project

SpiceCeylon revolutionizes the traditional spice trade by eliminating middlemen and providing a direct farmer-to-customer marketplace. The system features role-based dashboards, real-time inventory management, integrated messaging, and machine learning-based sales forecasting to help farmers make data-driven decisions.

---

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

---

## 🛠️ Built With

**Backend**: PHP 8.2, MySQL 8.0, Python 3.10, Apache

**Frontend**: HTML5/CSS3, JavaScript, Bootstrap 5.3, Chart.js, Font Awesome 6.0, Summernote, jQuery

**Libraries & Tools**: SheetJS (XLSX), jsPDF, Chart.js, MySQLi, pandas, numpy, scikit-learn, matplotlib

---

## 📁 Project Structure

SpiceCeylon/
│
├── admin/<br>
│
├── farmer/<br>
│
├── customer/<br>
│
├── auth/<br>
│
├── config/<br>
│
├── forecast/<br>
│
├── assets/<br>
│   ├── css<br>
│   ├── js<br>
│   └── images<br>
│
├── database/<br>
│   └── spiceceylon.sql<br>
│
└── index.php</br>

<b>⚙️ Installation Guide</b><br></br>
<b>1️⃣ Clone the Repository</b>
git clone https://github.com/YOUR_USERNAME/SpiceCeylon.git

<b>2️⃣ Move Project to XAMPP</b>

Copy the project folder to:
xampp/htdocs/

<b>3️⃣ Create Database</b>

Open phpMyAdmin

Create database:spiceceylon_db

Import:database/spiceceylon.sql

<b>4️⃣ Configure Database Connection</b>

Edit the file:config/db.php

<b>5️⃣ Run the System</b>

Start Apache and MySQL in XAMPP.

Open browser:http://localhost/SpiceCeylon

🔑 Sample Login Credentials
| Role     | Email                | Password    |
| -------- | ------------------   | ----------- |
| Admin    | [jk@gmail.com]       | 9701        |
| Farmer   | [d@gmail.com]        | D67890      |
| Customer | [s@gmail.com]       | 12345       |

<b>📊 Forecasting Module</b>

The forecasting module analyzes historical sales data and predicts future demand.

Steps used:

Data collection

Data preprocessing

Model training

Sales prediction

Visualization of results

This helps farmers plan production and inventory effectively.

<b>🚧 Limitations</b>

Forecasting accuracy depends on historical data availability

System currently supports only web platform

Payment gateway integration is not implemented

<b>🔮 Future Improvements</b>

Mobile application

Online payment gateway integration

Advanced AI forecasting models

Real-time delivery tracking

Multi-language support

<b>👩‍💻 Developer</b>

Lakshani246

Final Year Project
BSc (Hons) Software Engineering

<b>📜 License</b>

This project is developed for academic purposes.

