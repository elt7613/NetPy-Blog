<?php
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $error = null;
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "All fields are required.";
    } else {
        // Get username from email
        $sql = "SELECT username FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            $username = $result['username'];
            // Use loginUser function to check credentials and active status
            if (loginUser($username, $password)) {
                // Check if there's a return URL
                if (isset($_GET['return_url'])) {
                    $return_url = $_GET['return_url'];
                    // Validate the return URL to ensure it's a local URL
                    if (strpos($return_url, '/') === 0) {
                        header('Location: ' . $return_url);
                        exit;
                    }
                }
                
                // If no return URL or invalid, redirect to default location
                if ($_SESSION['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = "Invalid credentials or account is inactive.";
            }
        } else {
            $error = "Invalid credentials or account is inactive.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - NetPy Blog</title>
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
        .signup-link {
            text-align: center;
            margin-top: 20px;
        }
        .signup-link a {
            color: #f48840;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .signup-button {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background-color: #0047cc;
            color: #fff !important;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        .signup-button:hover {
            background-color: #1a5edb;
            color: #fff !important;
            text-decoration: none;
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
                            <h4>Welcome back</h4>
                            <h2>Login</h2>
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
                                <form action="login.php<?php echo isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : ''; ?>" method="post">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <label>Email</label>
                                                <input name="email" type="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <label>Password</label>
                                                <div class="password-field">
                                                    <input name="password" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="password"></i>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Login</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                                <div class="signup-link">
                                    Don't have an account? 
                                    <a href="signup.php<?php echo isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : ''; ?>" class="signup-button">Sign Up</a>
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