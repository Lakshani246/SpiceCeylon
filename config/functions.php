<?php
// Start secure session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load DB connection
require_once __DIR__ . "/db.php";

// -----------------------------
// SANITIZE USER INPUT
// -----------------------------
function cleanInput($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// -----------------------------
// CHECK LOGIN
// -----------------------------
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// -----------------------------
// REDIRECT BASED ON ROLE
// -----------------------------
function redirectByRole()
{
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit;
    }

    $role = $_SESSION['role'];

    if ($role === "customer") {
        header("Location: ../customer/home.php");
        exit;
    } elseif ($role === "farmer") {
        header("Location: ../farmer/dashboard.php");
        exit;
    } elseif ($role === "admin") {
        header("Location: ../admin/dashboard.php");
        exit;
    }
}

// -----------------------------
// GET USER (Customers + Farmers)
// -----------------------------
function getUser($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// -----------------------------
// GET ADMIN
// -----------------------------
function getAdmin($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// -----------------------------
// LOGIN CUSTOMER / FARMER
// -----------------------------
function loginUser($email, $password)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        return true;
    }

    return false;
}

// -----------------------------
// LOGIN ADMIN
// -----------------------------
function loginAdmin($email, $password)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['role'] = "admin";
        $_SESSION['name'] = $admin['username'];

        return true;
    }

    return false;
}

// -----------------------------
// LOGOUT
// -----------------------------
function logout()
{
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}
?>
