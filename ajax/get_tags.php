<?php
require_once '../config.php';

// Get all active tags
$tags_sql = "SELECT t.*, COUNT(DISTINCT pt.post_id) as post_count 
             FROM tags t 
             LEFT JOIN post_tags pt ON t.id = pt.tag_id 
             LEFT JOIN posts p ON pt.post_id = p.id 
             AND p.status = 'published' 
             AND p.deleted_at IS NULL 
             AND p.is_active = 1
             WHERE t.deleted_at IS NULL 
             AND t.is_active = 1
             GROUP BY t.id 
             HAVING post_count > 0 
             ORDER BY post_count DESC, t.name";
$tags_result = $conn->query($tags_sql);
$tags = $tags_result ? $tags_result->fetch_all(MYSQLI_ASSOC) : [];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($tags); 