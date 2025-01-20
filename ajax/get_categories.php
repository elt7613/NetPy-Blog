<?php
require_once '../config.php';

// Get all categories with post count
$categories_sql = "SELECT c.*, COUNT(p.id) as post_count 
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
$categories_result = $conn->query($categories_sql);
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($categories); 