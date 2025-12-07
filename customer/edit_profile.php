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

// Handle profile image with proper fallback
$profile_image = 'default-avatar.jpg';
if (isset($user['profile_image']) && !empty($user['profile_image'])) {
    $profile_image_path = '../assets/images/profile_images/' . $user['profile_image'];
    if (file_exists($profile_image_path)) {
        $profile_image = $user['profile_image'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    // Handle image upload
    $new_profile_image = $profile_image; // Keep existing image by default
    
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
                if ($profile_image != 'default-avatar.jpg') {
                    $old_image_path = $upload_dir . $profile_image;
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $new_profile_image = $new_filename;
            }
        }
    }
    
    // Update user data in database
    $update_query = "UPDATE users SET 
                    name='$name', 
                    email='$email', 
                    phone='$phone', 
                    address='$address', 
                    profile_image='$new_profile_image' 
                    WHERE user_id='$user_id'";
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-image-container {
            position: relative;
            display: inline-block;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 3px solid #b85c38;
        }
        .image-placeholder {
            width: 150px;
            height: 150px;
            background: #f8f9fa;
            border: 3px dashed #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['message'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
                        <?php endif; ?>
                        
                        <!-- Current Profile Image -->
                        <div class="text-center mb-4">
                            <div class="profile-image-container">
                                <?php
                                $image_path = '../assets/images/profile_images/' . $profile_image;
                                if (file_exists($image_path) && $profile_image != 'default-avatar.jpg'):
                                ?>
                                    <img src="<?php echo $image_path; ?>" 
                                         alt="Current Profile" 
                                         class="rounded-circle profile-image"
                                         onerror="this.style.display='none'; document.getElementById('image-placeholder').style.display='flex';">
                                <?php endif; ?>
                                
                                <div id="image-placeholder" class="rounded-circle image-placeholder <?php echo (file_exists($image_path) && $profile_image != 'default-avatar.jpg') ? 'd-none' : ''; ?>">
                                    <i class="fas fa-user fa-3x"></i>
                                </div>
                            </div>
                            <p class="text-muted mt-2">Current Profile Picture</p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Change Profile Image</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                                <div class="form-text">Allowed formats: JPG, PNG, GIF. Maximum size: 2MB</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone" class="form-control" 
                                               value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>"
                                               placeholder="Enter your phone number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="1" 
                                                  placeholder="Enter your address"><?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>