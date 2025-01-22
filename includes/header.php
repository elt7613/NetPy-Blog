<?php
// Get the current file path
$current_path = $_SERVER['PHP_SELF'];
$is_admin_page = strpos($current_path, '/admin/') !== false;
$is_author_page = strpos($current_path, '/author/') !== false;
$base_url = ($is_admin_page || $is_author_page) ? '../' : '';

// Get user's avatar if logged in
$user_avatar = '';
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $avatar_sql = "SELECT avatar FROM users WHERE id = ?";
    $stmt = $conn->prepare($avatar_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $avatar_result = $stmt->get_result();
    if ($avatar_row = $avatar_result->fetch_assoc()) {
        $user_avatar = $avatar_row['avatar'];
        // Add base URL to avatar path if it's not empty
        if (!empty($user_avatar)) {
            $user_avatar = $base_url . $user_avatar;
        }
    }
}

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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

        /* Profile dropdown styles */
        .profile-dropdown {
            position: relative;
        }

        .profile-dropdown .nav-link {
            padding: 0.5rem;
            color: #181818;
            display: flex;
            align-items: center;
        }

        .profile-dropdown .profile-img {
            position: relative;
            top: -5px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Hide profile image on mobile, show text links instead */
        @media (max-width: 991px) {
            .profile-dropdown {
                display: none;
            }
            .mobile-profile-links {
                display: block;
            }
        }

        /* Hide text links on desktop, show profile dropdown instead */
        @media (min-width: 992px) {
            .profile-dropdown {
                display: block;
            }
            .mobile-profile-links {
                display: none;
            }
        }

        .profile-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 8px 0;
            min-width: 180px;
            z-index: 1000;
        }

        .profile-dropdown-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .profile-dropdown-menu ul li {
            padding: 8px 20px;
        }

        .profile-dropdown-menu ul li:hover {
            background: #f8f9fa;
        }

        .profile-dropdown-menu ul li a {
            color: #181818;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-dropdown-menu ul li a:hover {
            color: #0047cc;
        }

        /* Show profile dropdown menu on hover and click */
        .profile-dropdown:hover .profile-dropdown-menu,
        .profile-dropdown.show .profile-dropdown-menu {
            display: block;
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
                    <!-- <h2>NetPy Blog<em>.</em></h2> -->
                     <img src="../assets/logo.png" alt="NetPy Blog" style="width: 100px; height: auto;">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>home.php">Blogs</a>
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
                        <li class="nav-item">
                            <a class="nav-link" href="https://netpy.in/" target="_blank">Explore NetPy</a>
                        </li>
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                                <!-- Profile dropdown for desktop -->
                                <li class="nav-item dropdown profile-dropdown">
                                    <a class="nav-link" href="#" id="profileDropdown" role="button">
                                        <?php if (!empty($user_avatar)): ?>
                                            <img src="<?php echo $user_avatar; ?>" alt="Profile" class="profile-img">
                                        <?php else: ?>
                                            <img src="<?php echo $base_url; ?>assets/images/default-avatar.png" alt="Profile" class="profile-img">
                                        <?php endif; ?>
                                    </a>
                                    <div class="profile-dropdown-menu">
                                        <ul>
                                            <li>
                                                <a href="<?php echo $base_url; ?>admin/dashboard.php">
                                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>admin/new-post.php">
                                                    <i class="fas fa-plus"></i> New Post
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>admin/manage-users.php">
                                                    <i class="fas fa-users"></i> Users
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>admin/manage-categories.php">
                                                    <i class="fas fa-folder"></i> Categories
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>admin/manage-tags.php">
                                                    <i class="fas fa-tags"></i> Tags
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>user-settings.php">
                                                    <i class="fas fa-cog"></i> Settings
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>logout.php">
                                                    <i class="fas fa-sign-out-alt"></i> Logout
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <!-- Mobile profile links -->
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/new-post.php">New Post</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-users.php">Users</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-categories.php">Categories</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>admin/manage-tags.php">Tags</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>user-settings.php">Settings</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>logout.php">Logout</a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'author'): ?>
                                <!-- Profile dropdown for desktop -->
                                <li class="nav-item dropdown profile-dropdown">
                                    <a class="nav-link" href="#" id="profileDropdown" role="button">
                                        <?php if (!empty($user_avatar)): ?>
                                            <img src="<?php echo $user_avatar; ?>" alt="Profile" class="profile-img">
                                        <?php else: ?>
                                            <img src="<?php echo $base_url; ?>assets/images/default-avatar.png" alt="Profile" class="profile-img">
                                        <?php endif; ?>
                                    </a>
                                    <div class="profile-dropdown-menu">
                                        <ul>
                                            <li>
                                                <a href="<?php echo $base_url; ?>author/dashboard.php">
                                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>author/new-post.php">
                                                    <i class="fas fa-plus"></i> New Post
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>user-settings.php">
                                                    <i class="fas fa-cog"></i> Settings
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>logout.php">
                                                    <i class="fas fa-sign-out-alt"></i> Logout
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <!-- Mobile profile links -->
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>author/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>author/new-post.php">New Post</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>user-settings.php">Settings</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>logout.php">Logout</a>
                                </li>
                            <?php else: ?>
                                <!-- Profile dropdown for desktop -->
                                <li class="nav-item dropdown profile-dropdown">
                                    <a class="nav-link" href="#" id="profileDropdown" role="button">
                                        <?php if (!empty($user_avatar)): ?>
                                            <img src="<?php echo $user_avatar; ?>" alt="Profile" class="profile-img">
                                        <?php else: ?>
                                            <img src="<?php echo $base_url; ?>assets/images/default-avatar.png" alt="Profile" class="profile-img">
                                        <?php endif; ?>
                                    </a>
                                    <div class="profile-dropdown-menu">
                                        <ul>
                                            <li>
                                                <a href="<?php echo $base_url; ?>user-settings.php">
                                                    <i class="fas fa-cog"></i> Settings
                                                </a>
                                            </li>
                                            <li>
                                                <a href="<?php echo $base_url; ?>logout.php">
                                                    <i class="fas fa-sign-out-alt"></i> Logout
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <!-- Mobile profile links -->
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>user-settings.php">Settings</a>
                                </li>
                                <li class="nav-item mobile-profile-links">
                                    <a class="nav-link" href="<?php echo $base_url; ?>logout.php">Logout</a>
                                </li>
                            <?php endif; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
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

            // Toggle profile dropdown on click
            $('.profile-dropdown .nav-link').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).parent('.profile-dropdown').toggleClass('show');
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.profile-dropdown').length) {
                    $('.profile-dropdown').removeClass('show');
                }
            });

            // Prevent dropdown from closing when clicking inside
            $('.profile-dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html> 