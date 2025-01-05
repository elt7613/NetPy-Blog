<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle delete subscriber
if (isset($_POST['delete_subscriber'])) {
    $subscriber_id = (int)$_POST['subscriber_id'];
    $delete_sql = "DELETE FROM netpy_newsletter_users WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $subscriber_id);
    $stmt->execute();
    $_SESSION['success_msg'] = "Subscriber deleted successfully.";
    header('Location: manage-newsletter.php');
    exit;
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$subscribers_per_page = 20;
$offset = ($page - 1) * $subscribers_per_page;

// Get total number of subscribers
$count_sql = "SELECT COUNT(*) as total FROM netpy_newsletter_users";
if (!empty($search)) {
    $count_sql .= " WHERE email LIKE ?";
}
$count_stmt = $conn->prepare($count_sql);
if (!empty($search)) {
    $search_param = "%$search%";
    $count_stmt->bind_param("s", $search_param);
}
$count_stmt->execute();
$total_subscribers = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_subscribers / $subscribers_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Get subscribers with search and pagination
$sql = "SELECT * FROM netpy_newsletter_users";
if (!empty($search)) {
    $sql .= " WHERE email LIKE ?";
}
$sql .= " ORDER BY subscribed_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sii", $search_param, $subscribers_per_page, $offset);
} else {
    $stmt->bind_param("ii", $subscribers_per_page, $offset);
}
$stmt->execute();
$subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Newsletter Subscribers";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manage Newsletter - NetPy Blog</title>
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
                            <h2>Manage Newsletter Subscribers</h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="blog-posts">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <?php if (isset($_SESSION['success_msg'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success_msg'];
                            unset($_SESSION['success_msg']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="stats-box">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3>Newsletter Subscribers (<?php echo $total_subscribers; ?>)</h3>
                            <div>
                                <form method="GET" class="form-inline">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search by email..." value="<?php echo htmlspecialchars($search); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                            <?php if (!empty($search)): ?>
                                                <a href="manage-newsletter.php" class="btn btn-secondary">Clear</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Subscribed Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribers as $subscriber): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($subscriber['subscribed_at'])); ?></td>
                                        <td>
                                            <form action="" method="post" style="display: inline;">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" name="delete_subscriber" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this subscriber?')">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination justify-content-center mt-4">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="First">
                                                <span aria-hidden="true">&laquo;&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Last">
                                                <span aria-hidden="true">&raquo;&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
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