<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    // Prevent deleting own account
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $_SESSION['success_msg'] = "User deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "You cannot delete your own account!";
    }
    header('Location: manage-users.php');
    exit();
}

// Handle role change
if (isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    // Prevent changing own role
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        $stmt->execute();
        $_SESSION['success_msg'] = "User role updated successfully!";
    } else {
        $_SESSION['error_msg'] = "You cannot change your own role!";
    }
    header('Location: manage-users.php');
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_by = isset($_GET['search_by']) ? $_GET['search_by'] : 'username';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$users_per_page = 10;
$offset = ($page - 1) * $users_per_page;

// First, get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    switch ($search_by) {
        case 'username':
            $count_sql .= " AND username LIKE ?";
            $search_param = "%$search%";
            $count_params[] = &$search_param;
            $count_types .= "s";
            break;
        case 'email':
            $count_sql .= " AND email LIKE ?";
            $search_param = "%$search%";
            $count_params[] = &$search_param;
            $count_types .= "s";
            break;
    }
}

if (!empty($role_filter)) {
    $count_sql .= " AND role = ?";
    $count_params[] = &$role_filter;
    $count_types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $count_sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    $count_params[] = &$date_from;
    $count_params[] = &$date_to;
    $count_types .= "ss";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $users_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Build the search query with pagination
$sql = "SELECT id, username, email, role, created_at FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    switch ($search_by) {
        case 'username':
            $sql .= " AND username LIKE ?";
            $search_param = "%$search%";
            $params[] = &$search_param;
            $types .= "s";
            break;
        case 'email':
            $sql .= " AND email LIKE ?";
            $search_param = "%$search%";
            $params[] = &$search_param;
            $types .= "s";
            break;
    }
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = &$role_filter;
    $types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = &$date_from;
    $params[] = &$date_to;
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = &$users_per_page;
$params[] = &$offset;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Users";
include '../includes/header.php';
?>

<style>
    .admin-menu {
        background: #f7f7f7;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
    }
    .search-form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .search-form .form-control {
        margin-bottom: 10px;
    }
    .search-form .btn {
        margin-right: 5px;
    }
    @media (max-width: 768px) {
        .search-form .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card mt-4">
                    <div class="card-header">
                        <h4>Manage Users</h4>
                    </div>
                    <div class="card-body">
                        <div class="search-form">
                            <form method="GET">
                                <div class="row">
                                    <div class="col-md-3">
                                        <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="search_by" class="form-control">
                                            <option value="username" <?php echo $search_by === 'username' ? 'selected' : ''; ?>>Username</option>
                                            <option value="email" <?php echo $search_by === 'email' ? 'selected' : ''; ?>>Email</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="role" class="form-control">
                                            <option value="">All Roles</option>
                                            <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="author" <?php echo $role_filter === 'author' ? 'selected' : ''; ?>>Author</option>
                                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>" title="From Date">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>" title="To Date">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">Search</button>
                                        <?php if (!empty($search) || !empty($role_filter) || !empty($date_from) || !empty($date_to)): ?>
                                            <a href="manage-users.php" class="btn btn-secondary w-100 mt-2">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="mb-3">
                            <a href="create-author.php" class="btn btn-primary">Create Author</a>
                        </div>

                        <?php if (isset($_SESSION['success_msg'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success_msg'];
                                unset($_SESSION['success_msg']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_msg'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error_msg'];
                                unset($_SESSION['error_msg']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No users found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <select name="new_role" onchange="this.form.submit()" class="form-control form-control-sm d-inline w-auto">
                                                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                                            <option value="author" <?php echo $user['role'] == 'author' ? 'selected' : ''; ?>>Author</option>
                                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                        <input type="hidden" name="change_role" value="1">
                                                    </form>
                                                <?php else: ?>
                                                    <?php echo ucfirst($user['role']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="delete_user" value="1">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div class="pagination justify-content-center mt-4">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php 
                                                echo !empty($search) ? '&search='.urlencode($search) : '';
                                                echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                echo !empty($role_filter) ? '&role='.urlencode($role_filter) : '';
                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                            ?>" aria-label="First">
                                                <span aria-hidden="true">&laquo;&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php 
                                                echo !empty($search) ? '&search='.urlencode($search) : '';
                                                echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                echo !empty($role_filter) ? '&role='.urlencode($role_filter) : '';
                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                            ?>" aria-label="Previous">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                echo !empty($search) ? '&search='.urlencode($search) : '';
                                                echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                echo !empty($role_filter) ? '&role='.urlencode($role_filter) : '';
                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                            ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php 
                                                echo !empty($search) ? '&search='.urlencode($search) : '';
                                                echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                echo !empty($role_filter) ? '&role='.urlencode($role_filter) : '';
                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                            ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php 
                                                echo !empty($search) ? '&search='.urlencode($search) : '';
                                                echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                echo !empty($role_filter) ? '&role='.urlencode($role_filter) : '';
                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                            ?>" aria-label="Last">
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
    </div>

    <?php include '../includes/footer.php'; ?> 