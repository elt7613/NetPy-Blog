<?php
// Get the current file path
$current_path = $_SERVER['PHP_SELF'];
$is_admin_page = strpos($current_path, '/admin/') !== false;
$is_author_page = strpos($current_path, '/author/') !== false;
$base_url = ($is_admin_page || $is_author_page) ? '../' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Stand Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="<?php echo $base_url; ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Additional CSS Files -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/fontawesome.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/owl.css">

    <style>
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #fff;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #f48840;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Fix for navbar-toggler icon */
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(0, 0, 0, 0.5)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>

<body>
    <!-- Preloader -->
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <header class="">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">
                    <h2>NetPy Blog<em>.</em></h2>
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>index.php">Home</a>
                        </li>
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/new-post.php">New Post</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-users.php">Users</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-categories.php">Categories</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-tags.php">Tags</a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'author'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>author/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>author/new-post.php">New Post</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>user-settings.php">Settings</a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $base_url; ?>user-settings.php">Settings</a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>logout.php">Logout</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>login.php">Login/Signup</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Core JavaScript -->
    <script src="<?php echo $base_url; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo $base_url; ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Additional Scripts -->
    <script src="<?php echo $base_url; ?>assets/js/custom.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/owl.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/slick.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/isotope.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/accordions.js"></script> 