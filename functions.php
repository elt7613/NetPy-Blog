<?php
require_once 'config.php';

// Post functions
function getAllPosts($limit = 10, $offset = 0) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name, u.username as author_name 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.status = 'published' 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getPostBySlug($slug) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name, u.username as author_name 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.slug = ? AND p.status = 'published'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getFeaturedPosts($limit = 5) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name, u.username as author_name 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.status = 'published' AND p.featured = 1 
            ORDER BY p.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Category functions
function getAllCategories() {
    global $conn;
    $sql = "SELECT * FROM categories ORDER BY name";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getPostsByCategory($category_slug, $limit = 10, $offset = 0) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name, u.username as author_name 
            FROM posts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE c.slug = ? AND p.status = 'published' 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $category_slug, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Comment functions
function getPostComments($post_id) {
    global $conn;
    $sql = "SELECT c.*, u.username 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ? 
            ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function addComment($post_id, $user_id, $content, $parent_id = null) {
    global $conn;
    $sql = "INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $post_id, $user_id, $content, $parent_id);
    return $stmt->execute();
}

// User functions
function loginUser($username, $password) {
    global $conn;
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Debug logging
    error_log("Login attempt for username: " . $username);
    error_log("User found in database: " . ($user ? "Yes" : "No"));
    
    if ($user) {
        $passwordVerified = password_verify($password, $user['password']);
        error_log("Password verification result: " . ($passwordVerified ? "Success" : "Failed"));
        
        if ($passwordVerified) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            error_log("Login successful for user: " . $username);
            return true;
        }
    }
    error_log("Login failed for user: " . $username);
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Utility functions
function createSlug($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    return $slug;
}

function sanitizeInput($data) {
    if ($data === null) {
        return '';
    }
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

function getPagination($total, $per_page) {
    $total_pages = ceil($total / $per_page);
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, min($current_page, $total_pages));
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'per_page' => $per_page,
        'offset' => ($current_page - 1) * $per_page
    ];
}

function getAllTags() {
    global $conn;
    
    $sql = "SELECT t.*, COUNT(DISTINCT pt.post_id) as post_count 
            FROM tags t 
            LEFT JOIN post_tags pt ON t.id = pt.tag_id 
            LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published'
            GROUP BY t.id 
            HAVING post_count > 0
            ORDER BY post_count DESC, t.name ASC";
            
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
?> 