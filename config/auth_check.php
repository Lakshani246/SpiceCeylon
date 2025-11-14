<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php?error=Please login first");
    exit;
}

$role = $_SESSION['role'];
$current_path = $_SERVER['PHP_SELF'];

if (strpos($current_path, "/customer/") !== false) {
    if ($role !== "customer") {
        redirectToRole($role);
        exit;
    }
}

if (strpos($current_path, "/farmer/") !== false) {
    if ($role !== "farmer" && $role !== "admin") {
        redirectToRole($role);
        exit;
    }
}

if (strpos($current_path, "/admin/") !== false) {
    if ($role !== "admin") {
        redirectToRole($role);
        exit;
    }
}

function redirectToRole($role)
{
    if ($role === "customer") {
        header("Location: ../customer/home.php");
        exit;
    } elseif ($role === "farmer") {
        header("Location: ../farmer/dashboard.php");
        exit;
    } elseif ($role === "admin") {
        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        header("Location: ../auth/login.php");
        exit;
    }
}
?>
