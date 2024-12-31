<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle tag creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && !empty($_POST['name'])) {
            $name = sanitizeInput($_POST['name']);
            $slug = createSlug($name);
            
            $sql = "INSERT INTO tags (name, slug) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $slug);
            $stmt->execute();
        } 
        elseif ($_POST['action'] === 'delete' && !empty($_POST['tag_id'])) {
            $tag_id = (int)$_POST['tag_id'];
            
            $sql = "DELETE FROM tags WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $tag_id);
            $stmt->execute();
        }
    }
}

// Get all tags
$sql = "SELECT * FROM tags ORDER BY name";
$result = $conn->query($sql);
$tags = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manage Tags - Stand Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
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
                            <h2>Manage Tags</h2>
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
                            <div class="sidebar-heading">
                                <h2>Add New Tag</h2>
                            </div>
                            <div class="content">
                                <form action="manage-tags.php" method="post">
                                    <input type="hidden" name="action" value="add">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input name="name" type="text" placeholder="Tag Name" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Add Tag</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12 mt-4">
                    <div class="down-contact">
                        <div class="sidebar-item contact-form">
                            <div class="sidebar-heading">
                                <h2>Existing Tags</h2>
                            </div>
                            <div class="content">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Slug</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tags as $tag): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                                <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                                                <td>
                                                    <form action="manage-tags.php" method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this tag?')">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
</body>
</html> 