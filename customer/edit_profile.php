<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/db.php';
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE user_id='$user_id'");
$user = $user_query->fetch_assoc();

// Initialize profile_image if not exists (for existing users)
if (!isset($user['profile_image']) || empty($user['profile_image'])) {
    $user['profile_image'] = 'default-avatar.jpg';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    // Handle image upload
    $profile_image = $user['profile_image']; // Keep existing image by default
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            // Create folder if it doesn't exist
            $upload_dir = '../assets/images/profile_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists and not default
                if (!empty($user['profile_image']) && $user['profile_image'] != 'default-avatar.jpg') {
                    $old_image_path = $upload_dir . $user['profile_image'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $profile_image = $new_filename;
            }
        }
    }
    
    $update_query = "UPDATE users SET name='$name', email='$email', phone='$phone', address='$address', profile_image='$profile_image' WHERE user_id='$user_id'";
    
    if ($conn->query($update_query)) {
        $_SESSION['message'] = "Profile updated successfully!";
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SpiceCeylon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Edit Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Current Profile Image -->
                        <div class="text-center mb-4">
                            <img src="../assets/images/profile_images/<?php echo $user['profile_image']; ?>" 
                                 alt="Current Profile" 
                                 class="rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #b85c38;">
                            <p class="text-muted mt-2">Current Profile Picture</p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Change Profile Image</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                                <div class="form-text">Allowed: JPG, PNG, GIF. Max 2MB</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>