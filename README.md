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

1️⃣ Admin

Manage users (farmers & customers)

Manage products

Approve or reject product listings

Manage orders

Send announcements

Monitor system performance

2️⃣ Farmer

Add, update, and delete products

Manage product inventory

View and process orders

View earnings and sales reports

Access forecasting dashboard

Respond to customer requests

3️⃣ Customer

Browse spice products

Add products to cart

Purchase products

Track order history

Add products to wishlist

Send spice requests

Review products

✨ Key Features
🛒 E-Commerce Features

Product catalog

Shopping cart

Secure checkout

Order tracking

Wishlist functionality

📊 Sales Analytics

Sales performance dashboard

Product sales reports

Revenue tracking

Export reports to CSV/PDF

🤖 Sales Forecasting

Predict future demand using historical sales data

Helps farmers plan production and inventory

Data visualization using charts

💬 Communication System

Messaging between users

Notifications

Customer request management

🔐 Security

Role-based access control

Secure authentication system

Password hashing

Input validation

🛠️ Technologies Used
Frontend

HTML5

CSS3

JavaScript

Bootstrap

Chart.js

Font Awesome

Backend

PHP

MySQL

Machine Learning

Python

Pandas

NumPy

Scikit-learn

Matplotlib

Tools

XAMPP

phpMyAdmin

Git & GitHub

Visual Studio Code

📁 Project Structure
SpiceCeylon/
│
├── admin/
│
├── farmer/
│
├── customer/
│
├── auth/
│
├── config/
│
├── forecast/
│
├── assets/
│   ├── css
│   ├── js
│   └── images
│
├── database/
│   └── spiceceylon.sql
│
└── index.php

⚙️ Installation Guide
1️⃣ Clone the Repository
git clone https://github.com/YOUR_USERNAME/SpiceCeylon.git

2️⃣ Move Project to XAMPP

Copy the project folder to:
xampp/htdocs/

3️⃣ Create Database

Open phpMyAdmin

Create database:spiceceylon_db

Import:database/spiceceylon.sql

4️⃣ Configure Database Connection

Edit the file:config/db.php

5️⃣ Run the System

Start Apache and MySQL in XAMPP.

Open browser:http://localhost/SpiceCeylon

🔑 Sample Login Credentials
| Role     | Email                | Password    |
| -------- | ------------------   | ----------- |
| Admin    | [jk@gmail.com]       | 9701        |
| Farmer   | [d@gmail.com]        | D67890      |
| Customer | [sr@gmail.com]       | 12345       |

📊 Forecasting Module

The forecasting module analyzes historical sales data and predicts future demand.

Steps used:

Data collection

Data preprocessing

Model training

Sales prediction

Visualization of results

This helps farmers plan production and inventory effectively.

🚧 Limitations

Forecasting accuracy depends on historical data availability

System currently supports only web platform

Payment gateway integration is not implemented

🔮 Future Improvements

Mobile application

Online payment gateway integration

Advanced AI forecasting models

Real-time delivery tracking

Multi-language support

👩‍💻 Developer

Lakshani246

Final Year Project
BSc (Hons) Software Engineering

📜 License

This project is developed for academic purposes.

