<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
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
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $phone_number = !empty($_POST['phone_number']) ? sanitizeInput($_POST['phone_number']) : null;
            
            // Check if username is already taken by another user
            $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $profile_error = "Username is already taken.";
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, phone_number = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $email, $phone_number, $user_id);
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
            if (isset($_POST['cropped_data']) && !empty($_POST['cropped_data'])) {
                // Handle cropped image data
                $cropped_data = $_POST['cropped_data'];
                
                // Get image data from base64 string
                list($type, $cropped_data) = explode(';', $cropped_data);
                list(, $cropped_data) = explode(',', $cropped_data);
                $cropped_data = base64_decode($cropped_data);
                
                // Get file extension from mime type
                list(, $mime) = explode(':', $type);
                $extension = ($mime === 'image/jpeg') ? 'jpg' : 
                           (($mime === 'image/png') ? 'png' : 
                           (($mime === 'image/gif') ? 'gif' : 'jpg'));
                
                // Create new filename
                $new_filename = 'avatar_' . $user_id . '.' . $extension;
                $upload_path = 'uploads/avatars/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!file_exists('uploads/avatars')) {
                    mkdir('uploads/avatars', 0777, true);
                }
                
                // Delete old avatar if exists
                if (!empty($user['avatar'])) {
                    $old_file = $user['avatar'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Save the cropped image
                if (file_put_contents($upload_path, $cropped_data)) {
                    // Update database with new avatar path
                    $sql = "UPDATE users SET avatar = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $upload_path, $user_id);
                    if ($stmt->execute()) {
                        $avatar_success = "Avatar updated successfully.";
                        
                        // Refresh user data
                        $sql = "SELECT * FROM users WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                    } else {
                        $avatar_error = "Failed to update avatar in database.";
                    }
                } else {
                    $avatar_error = "Failed to save cropped image.";
                }
            } else {
                $avatar_error = "No cropped image data received.";
            }
        }
        // Remove Avatar
        elseif ($_POST['action'] === 'remove_avatar') {
            // Delete the existing avatar file
            if (!empty($user['avatar'])) {
                $old_file = $user['avatar'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
                
                // Update database to remove avatar path
                $sql = "UPDATE users SET avatar = NULL WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $avatar_success = "Profile picture removed successfully.";
                    
                    // Refresh user data
                    $sql = "SELECT * FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $avatar_error = "Failed to remove profile picture.";
                }
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
    <title>User Settings - NetPy Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
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
            border: 3px solid #0047cc;
        }
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload {
            text-align: center;
        }
        /* Cropper Modal Styles */
        .crop-modal {
            display: none;  /* Default state is hidden */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100vh;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
        }
        .crop-modal.show {  /* Add show class for when modal should be visible */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .crop-modal-content {
            background-color: #fefefe;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            max-height: 90vh;
            overflow: auto;
        }
        .crop-container {
            max-width: 100%;
            height: 400px;
            margin-bottom: 20px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .crop-container img {
            max-width: 100%;
            max-height: 100%;
            display: block;
        }
        .crop-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 20px auto;
            border: 3px solid #0047cc;
            background-color: #f0f0f0;
        }
        .crop-buttons {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 10px;
        }
        .crop-buttons .main-button {
            display: inline-block;
            margin: 0 8px;
            min-width: 120px;
            font-size: 13px;
            color: #fff;
            background-color: #0047cc;
            padding: 12px 25px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .crop-buttons .main-button:hover {
            background-color: #0052e6;
        }
        .crop-buttons .cancel-button {
            background-color: #6c757d;
        }
        .crop-buttons .cancel-button:hover {
            background-color: #7f8890;
        }
        .remove-button {
            background-color: #dc3545 !important;
        }
        .remove-button:hover {
            background-color: #c82333 !important;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Page Content -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>User Settings</h4>
                            <h2>Manage Your Profile</h2>
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
                        <!-- Avatar Settings -->
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
                                    <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : 'assets/images/default-avatar.png'; ?>" alt="Profile Picture">
                                </div>
                                <form action="user-settings.php" method="post" enctype="multipart/form-data" id="avatar-form">
                                    <input type="hidden" name="action" value="update_avatar">
                                    <input type="hidden" name="cropped_data" id="cropped_data">
                                    <div class="avatar-upload">
                                        <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;">
                                        <button type="button" class="main-button" onclick="document.getElementById('avatar').click()">Choose Image</button>
                                        <button type="submit" class="main-button" style="margin-left: 10px;">Upload</button>
                                        <?php if (!empty($user['avatar'])): ?>
                                            <button type="submit" name="action" value="remove_avatar" class="main-button remove-button" style="margin-left: 10px;" onclick="return confirm('Are you sure you want to remove your profile picture?')">Remove Picture</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Profile Settings -->
                        <div class="sidebar-item contact-form settings-section">
                            <div class="sidebar-heading">
                                <h2>Profile Settings</h2>
                            </div>
                            <div class="content">
                                <?php if (isset($profile_success)): ?>
                                    <div class="alert alert-success">
                                        <?php echo $profile_success; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($profile_error)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $profile_error; ?>
                                    </div>
                                <?php endif; ?>
                                <form action="user-settings.php" method="post">
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
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Phone Number (Optional)</label>
                                                <input name="phone_number" type="tel" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
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
                                <form action="user-settings.php" method="post">
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

    <!-- Crop Modal -->
    <div id="cropModal" class="crop-modal">
        <div class="crop-modal-content">
            <div class="crop-container">
                <img id="cropImage" src="" alt="Crop Preview">
            </div>
            <div class="crop-preview"></div>
            <div class="crop-buttons">
                <button class="main-button" onclick="applyCrop()">Apply Changes</button>
                <button class="main-button cancel-button" onclick="cancelCrop()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        let cropper = null;

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

            // Preview and crop avatar image before upload
            $('#avatar').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Show crop modal
                        $('#cropModal').addClass('show');
                        $('#cropImage').attr('src', e.target.result);
                        
                        // Initialize cropper
                        if (cropper) {
                            cropper.destroy();
                        }
                        
                        cropper = new Cropper($('#cropImage')[0], {
                            aspectRatio: 1,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            cropBoxResizable: false,
                            cropBoxMovable: false,
                            guides: false,
                            center: false,
                            highlight: false,
                            background: false,
                            preview: '.crop-preview'
                        });
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        function applyCrop() {
            if (cropper) {
                // Get cropped canvas
                const canvas = cropper.getCroppedCanvas({
                    width: 300,
                    height: 300
                });
                
                // Convert to base64 and set preview
                const croppedData = canvas.toDataURL();
                $('.avatar-preview img').attr('src', croppedData);
                $('#cropped_data').val(croppedData);
                
                // Close modal and cleanup
                closeCropModal();
            }
        }

        function cancelCrop() {
            $('#avatar').val('');
            closeCropModal();
        }

        function closeCropModal() {
            $('#cropModal').removeClass('show');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }
    </script>
</body>
</html> 