<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get current user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Update Profile
        if ($_POST['action'] === 'update_profile') {
            $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
            $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
            
            // Check if username is already taken by another user
            $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $profile_error = "Username is already taken.";
            } else {
                $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $username, $email, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username; // Update session username
                    $profile_success = "Profile updated successfully.";
                    
                    // Refresh user data
                    $sql = "SELECT * FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $profile_error = "Failed to update profile.";
                }
            }
        }
        // Change Password
        elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    if ($stmt->execute()) {
                        $password_success = "Password changed successfully.";
                    } else {
                        $password_error = "Failed to change password.";
                    }
                } else {
                    $password_error = "New passwords do not match.";
                }
            } else {
                $password_error = "Current password is incorrect.";
            }
        }
        // Update Avatar
        elseif ($_POST['action'] === 'update_avatar') {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['avatar']['name'];
                $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($filetype), $allowed)) {
                    $new_filename = 'avatar_' . $user_id . '.' . $filetype;
                    $upload_dir = '../uploads/avatars/';
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Delete old avatar if exists
                    if (!empty($user['avatar'])) {
                        $old_file = '../' . $user['avatar'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                        // Update database with new avatar path
                        $avatar_path = 'uploads/avatars/' . $new_filename;
                        $sql = "UPDATE users SET avatar = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $avatar_path, $user_id);
                        if ($stmt->execute()) {
                            $avatar_success = "Avatar updated successfully.";
                            
                            // Refresh user data
                            $sql = "SELECT * FROM users WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $user = $stmt->get_result()->fetch_assoc();
                        } else {
                            $avatar_error = "Failed to update avatar in database: " . $conn->error;
                        }
                    } else {
                        $avatar_error = "Failed to upload avatar. Error: " . $_FILES['avatar']['error'];
                    }
                } else {
                    $avatar_error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF";
                }
            } else {
                $avatar_error = "No file uploaded or upload error occurred: " . $_FILES['avatar']['error'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Settings - NetPy Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    <style>
        .settings-section {
            margin-bottom: 40px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 12px;
            cursor: pointer;
            color: #666;
            padding: 5px;
            z-index: 10;
            height: 20px;
            line-height: 20px;
        }
        .toggle-password:hover {
            color: #333;
        }
        .password-field input[type="password"],
        .password-field input[type="text"] {
            padding-right: 45px;
            height: 44px;
        }
        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 3px solid #f48840;
        }
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload {
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Page Content -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>Admin</h4>
                            <h2>Settings</h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="contact-us">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="down-contact">
                        <!-- Profile Settings -->
                        <div class="sidebar-item contact-form settings-section">
                            <div class="sidebar-heading">
                                <h2>Profile Picture</h2>
                            </div>
                            <div class="content">
                                <?php if (isset($avatar_success)): ?>
                                    <div class="alert alert-success">
                                        <?php echo $avatar_success; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($avatar_error)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $avatar_error; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="avatar-preview">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img src="<?php echo '../' . htmlspecialchars($user['avatar']); ?>" alt="Current Profile Picture">
                                    <?php else: ?>
                                        <img src="../assets/images/deffault-profile-img.png" alt="Default Profile Picture">
                                    <?php endif; ?>
                                </div>
                                <form action="settings.php" method="post" enctype="multipart/form-data" id="avatar-form">
                                    <input type="hidden" name="action" value="update_avatar">
                                    <div class="avatar-upload">
                                        <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;" onchange="document.getElementById('avatar-form').submit();">
                                        <button type="button" class="main-button" onclick="document.getElementById('avatar').click()">Choose Image</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Profile Information -->
                        <div class="sidebar-item contact-form settings-section">
                            <div class="sidebar-heading">
                                <h2>Profile Information</h2>
                            </div>
                            <div class="content">
                                <form action="settings.php" method="post">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Username</label>
                                                <input name="username" type="text" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Email</label>
                                                <input name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Update Profile</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Password Settings -->
                        <div class="sidebar-item contact-form settings-section">
                            <div class="sidebar-heading">
                                <h2>Change Password</h2>
                            </div>
                            <div class="content">
                                <?php if (isset($password_success)): ?>
                                    <div class="alert alert-success">
                                        <?php echo $password_success; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($password_error)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $password_error; ?>
                                    </div>
                                <?php endif; ?>
                                <form action="settings.php" method="post">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <label>Current Password</label>
                                                <div class="password-field">
                                                    <input name="current_password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="current_password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>New Password</label>
                                                <div class="password-field">
                                                    <input name="new_password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="new_password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Confirm New Password</label>
                                                <div class="password-field">
                                                    <input name="confirm_password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="confirm_password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Change Password</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/custom.js"></script>
    <script src="../assets/js/owl.js"></script>
    <script src="../assets/js/slick.js"></script>
    <script src="../assets/js/isotope.js"></script>
    <script src="../assets/js/accordions.js"></script>

    <script>
        $(document).ready(function() {
            // Password toggle functionality
            $('.toggle-password').click(function() {
                const icon = $(this);
                const input = $('input[name="' + icon.data('target') + '"]');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Preview avatar image before upload
            $('#avatar').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('.avatar-preview img').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html> 