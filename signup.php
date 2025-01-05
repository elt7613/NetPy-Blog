<?php
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Check if there's a return URL
    if (isset($_GET['return_url'])) {
        $return_url = $_GET['return_url'];
        // Validate the return URL to ensure it's a local URL
        if (strpos($return_url, '/') === 0) {
            header('Location: ' . $return_url);
            exit;
        }
    }
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone_number = !empty($_POST['phone_number']) ? sanitizeInput($_POST['phone_number']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $error = null;
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required except phone number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if username or email already exists
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Default role for new signups
            $sql = "INSERT INTO users (username, email, phone_number, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $username, $email, $phone_number, $hashed_password, $role);
            
            if ($stmt->execute()) {
                // Add user to newsletter
                $newsletter_sql = "INSERT IGNORE INTO netpy_newsletter_users (email) VALUES (?)";
                $newsletter_stmt = $conn->prepare($newsletter_sql);
                $newsletter_stmt->bind_param("s", $email);
                $newsletter_stmt->execute();

                // Set session and redirect
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                
                // Check if there's a return URL
                if (isset($_GET['return_url'])) {
                    $return_url = $_GET['return_url'];
                    // Validate the return URL to ensure it's a local URL
                    if (strpos($return_url, '/') === 0) {
                        header('Location: ' . $return_url);
                        exit;
                    }
                }
                
                header('Location: index.php');
                exit;
            } else {
                $error = "Registration failed. Please try again.";
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
    <title>Sign Up - NetPy Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    <style>
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
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #0047cc;
        }
        .login-link a:hover {
            text-decoration: underline;
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
                            <h4>Join our community</h4>
                            <h2>Sign Up</h2>
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
                        <div class="sidebar-item contact-form">
                            <div class="content">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>
                                <form action="signup.php<?php echo isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : ''; ?>" method="post">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Username</label>
                                                <input name="username" type="text" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Email</label>
                                                <input name="email" type="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Phone Number (Optional)</label>
                                                <input name="phone_number" type="tel" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Password</label>
                                                <div class="password-field">
                                                    <input name="password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>Confirm Password</label>
                                                <div class="password-field">
                                                    <input name="confirm_password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="confirm_password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Sign Up</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                                <div class="login-link">
                                    Already have an account? <a href="login.php<?php echo isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : ''; ?>">Login here</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

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
        });
    </script>
</body>
</html> 