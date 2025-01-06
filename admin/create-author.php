<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Handle author creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];

    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Username or email already exists";
    }

    if (empty($errors)) {
        // Create the author
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'author';
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Author account created successfully!";
            header('Location: manage-users.php');
            exit();
        } else {
            $errors[] = "Error creating author account";
        }
    }
}

$page_title = "Create Author";
include '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Create Author Account</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required>
                            <small class="form-text text-muted">Username must be at least 3 characters long.</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fa fa-eye" id="password-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fa fa-eye" id="confirm_password-icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Create Author</button>
                            <a href="manage-users.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>

                    <!-- Add JavaScript for password toggle -->
                    <script>
                        function togglePassword(fieldId) {
                            const passwordField = document.getElementById(fieldId);
                            const icon = document.getElementById(fieldId + '-icon');
                            
                            if (passwordField.type === 'password') {
                                passwordField.type = 'text';
                                icon.classList.remove('fa-eye');
                                icon.classList.add('fa-eye-slash');
                            } else {
                                passwordField.type = 'password';
                                icon.classList.remove('fa-eye-slash');
                                icon.classList.add('fa-eye');
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 