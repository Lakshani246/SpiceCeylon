<?php
include '../config/db.php';

$new_password = '9701'; // new password
$hash = password_hash($new_password, PASSWORD_DEFAULT);

$conn->query("UPDATE admins SET password='$hash' WHERE email='jk@gmail.com'");
echo "Admin password reset!";
?>
