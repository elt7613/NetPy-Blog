<?php
// Get recent posts for sidebar
$sidebar_recent_posts = getAllPosts(5, 0);

// Get all active tags for tag cloud
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

// Get categories with post count
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
?>

<div class="col-lg-4">
    <div class="sidebar">
        <div class="row">
            <div class="col-lg-12">
                <div class="sidebar-item search">
                    <form id="search_form" method="get" action="search.php">
                        <input type="text" name="q" class="searchText" placeholder="type to search..." autocomplete="off">
                    </form>
                </div>
            </div>
            
            <div class="col-lg-12">
                <div class="sidebar-item recent-posts">
                    <div class="sidebar-heading">
                        <h2>Recent Posts</h2>
                    </div>
                    <div class="content">
                        <ul>
                            <?php foreach ($sidebar_recent_posts as $post): ?>
                            <li>
                                <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                                    <h5><?php echo htmlspecialchars($post['title']); ?></h5>
                                    <span><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-12">
                <div class="sidebar-item categories">
                    <div class="sidebar-heading">
                        <h2>Categories</h2>
                    </div>
                    <div class="content">
                        <ul>
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>">
                                    - <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-12">
                <div class="sidebar-item tags">
                    <div class="sidebar-heading">
                        <h2>Tags</h2>
                    </div>
                    <div class="content">
                        <ul class="tags">
                            <?php if (!empty($tags)): ?>
                                <?php foreach ($tags as $tag): ?>
                                <li>
                                    <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" 
                                       class="tag-<?php echo min(ceil($tag['post_count'] / 2), 5); ?>"
                                       title="<?php echo $tag['post_count']; ?> posts">
                                        <?php echo htmlspecialchars($tag['name']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No tags available</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <style>
                        .sidebar-item .tags ul.tags {
                            list-style: none;
                            padding: 0;
                            margin: 0;
                            display: flex;
                            flex-wrap: wrap;
                            gap: 8px;
                        }
                        .sidebar-item .tags ul.tags li {
                            margin: 0;
                            padding: 0;
                        }
                        .sidebar-item .tags ul.tags li a {
                            display: inline-block;
                            padding: 4px 12px;
                            background: #f8f9fa;
                            border-radius: 20px;
                            color: #666;
                            text-decoration: none;
                            font-size: 13px;
                            transition: all 0.3s ease;
                        }
                        .sidebar-item .tags ul.tags li a:hover {
                            background: #f48840;
                            color: #fff;
                        }
                        .sidebar-item .tags ul.tags li a.tag-1 { font-size: 12px; }
                        .sidebar-item .tags ul.tags li a.tag-2 { font-size: 13px; }
                        .sidebar-item .tags ul.tags li a.tag-3 { font-size: 14px; }
                        .sidebar-item .tags ul.tags li a.tag-4 { font-size: 15px; }
                        .sidebar-item .tags ul.tags li a.tag-5 { font-size: 16px; }
                        
                        /* Normalize scrollbar appearance */
                        .sidebar::-webkit-scrollbar {
                            width: 5px;
                            height: 5px;  /* Added for horizontal scrollbar */
                        }
                        
                        .sidebar::-webkit-scrollbar-track {
                            background: #f1f1f1;
                        }
                        
                        .sidebar::-webkit-scrollbar-thumb {
                            background: #888;
                            border-radius: 3px;
                        }
                        
                        .sidebar::-webkit-scrollbar-thumb:hover {
                            background: #555;
                        }
                    </style>
                </div>
            </div>
        </div>
    </div>
</div>