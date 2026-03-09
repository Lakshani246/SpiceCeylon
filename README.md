<b>🌶️ SpiceCeylon – Smart Spice Marketplace with Sales Forecasting</b>

SpiceCeylon is a web-based e-commerce platform designed to connect Sri Lankan spice farmers directly with customers. The system eliminates middlemen and allows farmers to sell their products online while providing customers with access to high-quality spices.

The platform also includes a sales forecasting module that helps farmers predict future demand using historical sales data.

<b>📌 Project Objective</b>

The main objective of this system is to:

Provide an online marketplace for Sri Lankan spices

Allow farmers to manage and sell products easily

Enable customers to purchase spices directly

Provide sales analytics and forecasting for farmers

Improve transparency and efficiency in the spice supply chain

<b>👥 System Users</b>

The system consists of three main user roles:

<b>1️⃣ Admin</b>

Manage users (farmers & customers),Manage products,Approve or reject product listings,Manage orders,Send announcements,Monitor system performance

<b>2️⃣ Farmer</b>

Add, update, and delete products,Manage product inventory,View and process orders,View earnings and sales reports,Access forecasting dashboard,Respond to customer requests

<b>3️⃣ Customer</b>

Browse spice products,Add products to cart,Purchase products,Track order history,Add products to wishlist,Send spice requests,
Review products

<b>✨ Key Features</b></br>

<b>🛒 E-Commerce Features</b>

Product catalog,Shopping cart,Secure checkout,Order tracking,Wishlist functionality

<b>📊 Sales Analytics</b>

Sales performance dashboard

Product sales reports

Revenue tracking

Export reports to CSV/PDF

<b>🤖 Sales Forecasting</b>

Predict future demand using historical sales data

Helps farmers plan production and inventory

Data visualization using charts

<b>💬 Communication System</b>

Messaging between users

Notifications

Customer request management

<b>🔐 Security</b>

Role-based access control

Secure authentication system

Password hashing

Input validation

<b>🛠️ Technologies Used</b>
Frontend 
HTML5,CSS3,JavaScript,Bootstrap,Chart.js,Font Awesome

<b>Backend</b>

PHP

MySQL

<b>Machine Learning</b>

Python

Pandas

NumPy

Scikit-learn

Matplotlib

<b>Tools</b>

XAMPP

phpMyAdmin

Git & GitHub

Visual Studio Code

<b>📁 Project Structure</b>
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
│   ├── css
│   ├── js
│   └── images
│
├── database/<br>
│   └── spiceceylon.sql
│
└── index.php</br>

<b>⚙️ Installation Guide</b>
<b>1️⃣ Clone the Repository
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
| Customer | [sr@gmail.com]       | 12345       |

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

