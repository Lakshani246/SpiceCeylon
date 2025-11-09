<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "spiceceylon_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("<h3 style='color: red;'>❌ Database Connection Failed: " . $conn->connect_error . "</h3>");
} else {
    echo "<h3 style='color: green;'>✅ Database Connection Successful!</h3>";
}
?>
