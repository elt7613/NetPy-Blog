<?php
// Get the current file path
$current_path = $_SERVER['PHP_SELF'];
$is_admin_page = strpos($current_path, '/admin/') !== false;
$is_author_page = strpos($current_path, '/author/') !== false;
$base_url = ($is_admin_page || $is_author_page) ? '../' : '';

// Get categories with post count for the navbar
$nav_categories_sql = "SELECT c.*, COUNT(p.id) as post_count 
                      FROM categories c 
                      LEFT JOIN posts p ON c.id = p.category_id 
                      AND p.status = 'published' 
                      AND p.deleted_at IS NULL 
                      AND p.is_active = 1
                      WHERE c.deleted_at IS NULL 
                      AND c.is_active = 1
                      GROUP BY c.id 
                      HAVING post_count > 0
                      ORDER BY c.name";
$nav_categories_result = $conn->query($nav_categories_sql);
$nav_categories = $nav_categories_result ? $nav_categories_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>NetPy Blog</title>

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
            border-top: 4px solid #0047cc;
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

        .categories-dropdown {
            position: relative;
        }

        .categories-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 15px;
            min-width: 220px;
            z-index: 1000;
        }

        /* Show categories menu on hover only for desktop */
        @media (min-width: 992px) {
            .categories-dropdown:hover .categories-menu {
                display: block;
            }
        }

        .categories-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .categories-menu ul li {
            padding: 8px 15px;
            position: relative;
            padding-left: 20px;
            text-align: left;
        }

        .categories-menu ul li:before {
            content: "•";
            position: absolute;
            top: 12px;
            left: 5px;
            color: #181818;
            font-size: 18px;
            line-height: 1;
        }

        .categories-menu ul li:hover {
            background: #eee;
            border-radius: 4px;
        }

        .categories-menu ul li a {
            color: #181818;
            text-decoration: none;
            display: block;
        }

        .categories-menu ul li a:hover {
            color: #0047cc;
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
                            <a class="nav-link" href="https://netpy.in/" target="_blank">Explore NetPy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>index.php">Blogs</a>
                        </li>
                        <li class="nav-item categories-dropdown">
                            <a class="nav-link" href="#">Categories</a>
                            <div class="categories-menu">
                                <ul>
                                    <?php foreach ($nav_categories as $category): ?>
                                    <li>
                                        <a href="<?php echo $base_url; ?>category.php?slug=<?php echo urlencode($category['slug']); ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                            <small>(<?php echo $category['post_count']; ?>)</small>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
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
    
    <script>
        $(document).ready(function() {
            // Improved toggle button handling
            $('.navbar-toggler').on('click', function(event) {
                event.stopPropagation(); // Prevent event from bubbling up
                var $navbar = $('.navbar-collapse');
                if ($navbar.hasClass('show')) {
                    $navbar.collapse('hide');
                } else {
                    $navbar.collapse('show');
                }
            });

            // Close menu when clicking outside
            $(document).on('click', function(event) {
                var $navbar = $('.navbar-collapse');
                if ($navbar.hasClass('show') && !$(event.target).closest('.navbar').length) {
                    $navbar.collapse('hide');
                }
            });

            // Close menu when clicking on a nav link (for mobile), except for categories
            $('.nav-link').not('.categories-dropdown > .nav-link').on('click', function() {
                var $navbar = $('.navbar-collapse');
                if ($navbar.hasClass('show')) {
                    $navbar.collapse('hide');
                }
            });

            // Handle categories dropdown on mobile
            if (window.matchMedia("(max-width: 991px)").matches) {
                // Remove any existing click handlers first
                $('.categories-dropdown > a').off('click');
                
                $('.categories-dropdown > a').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent event from bubbling up
                    var $menu = $(this).siblings('.categories-menu');
                    $('.categories-menu').not($menu).slideUp(); // Close other menus
                    $menu.slideToggle();
                });

                // Prevent categories menu clicks from closing the main menu
                $('.categories-menu').on('click', function(e) {
                    e.stopPropagation();
                });

                // Handle clicks on category items
                $('.categories-menu a').on('click', function(e) {
                    var $navbar = $('.navbar-collapse');
                    $navbar.collapse('hide');
                });
            }
        });
    </script>
</body>
</html> 