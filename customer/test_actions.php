<?php
// test_actions.php
echo "Testing action files...<br>";

$actions_path = 'actions/add_to_cart.php';
echo "Looking for: $actions_path<br>";

if (file_exists($actions_path)) {
    echo "✓ File exists!<br>";
    
    // Check if we can read it
    if (is_readable($actions_path)) {
        echo "✓ File is readable!<br>";
        
        // Test basic functionality
        echo "<br>Testing basic PHP...<br>";
        require_once "config/db.php";
        echo "✓ Database connection file loaded<br>";
        
        session_start();
        $_SESSION['user_id'] = 1; // Temporary for testing
        $_SESSION['role'] = 'customer';
        
        include $actions_path;
    } else {
        echo "✗ File is not readable!<br>";
    }
} else {
    echo "✗ File does not exist!<br>";
    
    // Show current directory
    echo "<br>Current directory: " . getcwd() . "<br>";
    echo "Directory listing:<br>";
    $files = scandir('.');
    foreach ($files as $file) {
        echo "- $file<br>";
    }
    
    echo "<br>Actions folder contents:<br>";
    if (is_dir('actions')) {
        $action_files = scandir('actions');
        foreach ($action_files as $file) {
            echo "- $file<br>";
        }
    } else {
        echo "Actions folder doesn't exist!<br>";
    }
}
?>