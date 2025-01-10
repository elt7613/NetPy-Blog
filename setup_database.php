<?php
// Include the config.php file to use the database connection
require_once 'config.php'; // Ensure the path to config.php is correct

// SQL queries to create tables
$queries = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone_number VARCHAR(20) DEFAULT NULL,
        role ENUM('admin', 'author', 'user') DEFAULT 'user',
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        avatar VARCHAR(255) DEFAULT NULL
    )",

    // Password resets table
    "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp VARCHAR(6) NOT NULL,
        expiry DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX email_index (email),
        INDEX otp_index (otp)
    )",

    // Categories table
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        slug VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name_not_deleted (name, deleted_at),
        UNIQUE KEY unique_slug_not_deleted (slug, deleted_at)
    )",

    // Posts table
    "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255),
        category_id INT,
        author_id INT,
        status ENUM('draft', 'published') DEFAULT 'draft',
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        featured BOOLEAN DEFAULT FALSE,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_slug_not_deleted (slug, deleted_at)
    )",

    // Comments table
    "CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT,
        parent_id INT DEFAULT NULL,
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
    )",

    // Post views tracking table
    "CREATE TABLE IF NOT EXISTS post_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        INDEX idx_post_ip (post_id, ip_address),
        INDEX idx_viewed_at (viewed_at)
    )",

    // Tags table
    "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        slug VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name_not_deleted (name, deleted_at),
        UNIQUE KEY unique_slug_not_deleted (slug, deleted_at)
    )",

    // Post_tags relationship table
    "CREATE TABLE IF NOT EXISTS post_tags (
        post_id INT,
        tag_id INT,
        PRIMARY KEY (post_id, tag_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )",

    // Newsletter users table
    "CREATE TABLE IF NOT EXISTS netpy_newsletter_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email_not_deleted (email, deleted_at)
    )",

    // Insert default admin user (password: Password)
    "INSERT INTO users (username, password, email, role) VALUES 
    ('admin', 'Password', 'admin@example.com', 'admin') 
    ON DUPLICATE KEY UPDATE username=username",

    // Insert some default categories
    "INSERT INTO categories (name, slug) VALUES 
    ('Lifestyle', 'lifestyle'),
    ('Fashion', 'fashion'),
    ('Nature', 'nature'),
    ('Technology', 'technology') 
    ON DUPLICATE KEY UPDATE name=name"
];

// Execute each query
foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Query executed successfully: " . substr($query, 0, 50) . "...<br>";
    } else {
        echo "Error executing query: " . $conn->error . "<br>";
    }
}

// Close the connection
$conn->close();
?>
