<?php
require_once 'config.php';

try {
    // Create new connection without database name for initial setup
    $setup_conn = new mysqli($servername, $username, $password);
    
    // Check connection
    if ($setup_conn->connect_error) {
        throw new Exception("Connection failed: " . $setup_conn->connect_error);
    }
    
    echo "Connected successfully\n";
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($setup_conn->query($sql) === TRUE) {
        echo "Database created successfully or already exists\n";
    } else {
        throw new Exception("Error creating database: " . $setup_conn->error);
    }
    
    // Close initial connection
    $setup_conn->close();
    
    // Use the connection from config.php for rest of the operations
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Create Users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone_number VARCHAR(20) DEFAULT NULL,
        role ENUM('admin', 'author', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        avatar VARCHAR(255) DEFAULT NULL
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Users table created successfully\n";
    } else {
        throw new Exception("Error creating users table: " . mysqli_error($conn));
    }
    
    // Create Categories table
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        slug VARCHAR(50) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Categories table created successfully\n";
    } else {
        throw new Exception("Error creating categories table: " . mysqli_error($conn));
    }
    
    // Create Posts table
    $sql = "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255),
        category_id INT,
        author_id INT,
        status ENUM('draft', 'published') DEFAULT 'draft',
        featured BOOLEAN DEFAULT FALSE,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Posts table created successfully\n";
    } else {
        throw new Exception("Error creating posts table: " . mysqli_error($conn));
    }
    
    // Create Comments table
    $sql = "CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT,
        parent_id INT DEFAULT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Comments table created successfully\n";
    } else {
        throw new Exception("Error creating comments table: " . mysqli_error($conn));
    }
    
    // Create Post views tracking table
    $sql = "CREATE TABLE IF NOT EXISTS post_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        INDEX idx_post_ip (post_id, ip_address),
        INDEX idx_viewed_at (viewed_at)
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Post views table created successfully\n";
    } else {
        throw new Exception("Error creating post views table: " . mysqli_error($conn));
    }
    
    // Create Tags table
    $sql = "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        slug VARCHAR(50) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Tags table created successfully\n";
    } else {
        throw new Exception("Error creating tags table: " . mysqli_error($conn));
    }
    
    // Create Post_tags relationship table
    $sql = "CREATE TABLE IF NOT EXISTS post_tags (
        post_id INT,
        tag_id INT,
        PRIMARY KEY (post_id, tag_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Post tags table created successfully\n";
    } else {
        throw new Exception("Error creating post tags table: " . mysqli_error($conn));
    }
    
    // Create Newsletter users table
    $sql = "CREATE TABLE IF NOT EXISTS netpy_newsletter_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "Newsletter users table created successfully\n";
    } else {
        throw new Exception("Error creating newsletter users table: " . mysqli_error($conn));
    }
    
    // Insert default admin user
    $sql = "INSERT IGNORE INTO users (username, password, email, role) VALUES 
            ('admin', 'Password', 'admin@example.com', 'admin')";
    
    if (mysqli_query($conn, $sql)) {
        echo "Default admin user created successfully\n";
    } else {
        throw new Exception("Error creating default admin user: " . mysqli_error($conn));
    }
    
    // Insert default categories
    $sql = "INSERT IGNORE INTO categories (name, slug) VALUES 
            ('Lifestyle', 'lifestyle'),
            ('Fashion', 'fashion'),
            ('Nature', 'nature'),
            ('Technology', 'technology')";
    
    if (mysqli_query($conn, $sql)) {
        echo "Default categories created successfully\n";
    } else {
        throw new Exception("Error creating default categories: " . mysqli_error($conn));
    }
    
    echo "Database setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($setup_conn)) {
        $setup_conn->close();
    }
    // Note: Don't close $conn here as it's managed by config.php
} 