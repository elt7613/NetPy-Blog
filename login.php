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
                    header('Location: home.php');
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
        /* Add styles for modal positioning */
        #forgotPasswordModal .modal-dialog {
            margin-top: 150px;
        }
        #forgotPasswordModal {
            background-color: rgba(0, 0, 0, 0.5);
        }
        #forgotPasswordModal .modal-content {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Loader styles */
        .loader-container {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loader {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #0047cc;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal button styles */
        #forgotPasswordModal .btn-primary {
            background-color: #0047cc;
            border-color: #0047cc;
            width: 100%;
            margin-top: 10px;
        }
        #forgotPasswordModal .btn-primary:hover {
            background-color: #1a5edb;
            border-color: #1a5edb;
        }
        #forgotPasswordModal .form-group {
            margin-bottom: 15px;
        }
        .timer-container {
            text-align: center;
            margin: 10px 0;
            color: #666;
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
                                                    <input name="password" id="loginPassword" type="password" required>
                                                    <i class="fa fa-eye toggle-password" data-target="loginPassword"></i>
                                                </div>
                                                <div class="forgot-password-link" style="text-align: right; margin-top: 5px;">
                                                    <a href="#" id="forgotPasswordLink" style="color: #0047cc; font-size: 14px;">Forgot Password?</a>
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

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="forgotPasswordStep1">
                        <p>Enter your email address to receive a verification code.</p>
                        <div class="form-group">
                            <input type="email" class="form-control" id="resetEmail" placeholder="Enter your email" required>
                        </div>
                        <button type="button" class="btn btn-primary" id="sendOtpBtn">Send Verification Code</button>
                    </div>
                    
                    <div class="loader-container" id="otpLoader">
                        <div class="loader"></div>
                        <p style="margin-top: 10px;">Sending verification code...</p>
                    </div>
                    
                    <div id="forgotPasswordStep2" style="display: none;">
                        <p>Enter the verification code sent to your email.</p>
                        <div class="form-group">
                            <input type="text" class="form-control" id="otpCode" placeholder="Enter verification code" required>
                        </div>
                        <div class="timer-container">
                            Time remaining: <span id="timer">10:00</span>
                        </div>
                        <button type="button" class="btn btn-primary" id="verifyOtpBtn">Verify Code</button>
                    </div>
                    
                    <div id="forgotPasswordStep3" style="display: none;">
                        <p>Enter your new password.</p>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" class="form-control" id="newPassword" placeholder="New password" required>
                                <i class="fa fa-eye toggle-password" data-target="newPassword"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" class="form-control" id="confirmNewPassword" placeholder="Confirm new password" required>
                                <i class="fa fa-eye toggle-password" data-target="confirmNewPassword"></i>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" id="resetPasswordBtn">Reset Password</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Password toggle functionality
            $('.toggle-password').click(function() {
                const icon = $(this);
                const targetId = icon.data('target');
                const input = targetId.includes('name') ? 
                    $('input[name="' + targetId + '"]') : 
                    $('#' + targetId);
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Forgot Password Modal
            $('#forgotPasswordLink').click(function(e) {
                e.preventDefault();
                resetModalState();
                $('#forgotPasswordModal').modal('show');
            });

            function resetModalState() {
                $('#forgotPasswordStep1').show();
                $('#forgotPasswordStep2, #forgotPasswordStep3, #otpLoader').hide();
                $('#resetEmail, #otpCode, #newPassword, #confirmNewPassword').val('');
                clearInterval(timerInterval);
            }

            let timerInterval;
            function startTimer(duration) {
                let timer = duration;
                clearInterval(timerInterval);
                
                timerInterval = setInterval(function () {
                    let minutes = parseInt(timer / 60, 10);
                    let seconds = parseInt(timer % 60, 10);

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    $('#timer').text(minutes + ":" + seconds);

                    if (--timer < 0) {
                        clearInterval(timerInterval);
                        alert('Verification code has expired. Please request a new one.');
                        resetModalState();
                    }
                }, 1000);
            }

            // Send OTP
            $('#sendOtpBtn').click(function() {
                const email = $('#resetEmail').val();
                if (!email) {
                    alert('Please enter your email address.');
                    return;
                }

                // Show loader and hide step 1
                $('#forgotPasswordStep1').hide();
                $('#otpLoader').show();

                $.ajax({
                    url: 'send_reset_otp.php',
                    method: 'POST',
                    data: { email: email },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            // Hide loader and show step 2
                            $('#otpLoader').hide();
                            $('#forgotPasswordStep2').show();
                            startTimer(600); // 10 minutes
                        } else {
                            alert(data.message);
                            // Show step 1 again if there's an error
                            $('#otpLoader').hide();
                            $('#forgotPasswordStep1').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        alert('Failed to connect to the server. Please try again.');
                        $('#otpLoader').hide();
                        $('#forgotPasswordStep1').show();
                    }
                });
            });

            // Reset modal state when it's closed
            $('#forgotPasswordModal').on('hidden.bs.modal', function () {
                resetModalState();
            });

            // Verify OTP
            $('#verifyOtpBtn').click(function() {
                const email = $('#resetEmail').val();
                const otp = $('#otpCode').val();
                
                if (!otp) {
                    alert('Please enter the verification code.');
                    return;
                }

                // Disable button and show loading state
                const $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...');

                $.ajax({
                    url: 'verify_reset_otp.php',
                    method: 'POST',
                    data: { 
                        email: email,
                        otp: otp 
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('#forgotPasswordStep2').hide();
                            $('#forgotPasswordStep3').show();
                            clearInterval(timerInterval);
                        } else {
                            alert(data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        alert('Failed to verify code. Please try again.');
                    },
                    complete: function() {
                        // Re-enable button and restore text
                        $btn.prop('disabled', false).text('Verify Code');
                    }
                });
            });

            // Reset Password
            $('#resetPasswordBtn').click(function() {
                const email = $('#resetEmail').val();
                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmNewPassword').val();
                
                if (!newPassword || !confirmPassword) {
                    alert('Please fill in all fields.');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    alert('Passwords do not match.');
                    return;
                }

                // Disable button and show loading state
                const $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Resetting...');

                $.ajax({
                    url: 'reset_password.php',
                    method: 'POST',
                    data: { 
                        email: email,
                        password: newPassword
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            alert('Password reset successful. You can now login with your new password.');
                            $('#forgotPasswordModal').modal('hide');
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        alert('Failed to reset password. Please try again.');
                    },
                    complete: function() {
                        // Re-enable button and restore text
                        $btn.prop('disabled', false).text('Reset Password');
                    }
                });
            });
        });
    </script>
</body>
</html> 