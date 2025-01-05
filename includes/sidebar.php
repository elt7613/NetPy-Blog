<?php
// Get recent posts for sidebar
$sidebar_recent_posts = getAllPosts(5, 0);

// Get all tags for tag cloud
$tags = getAllTags();
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
                        <h2>Tag Clouds</h2>
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
                            gap: 10px;
                        }
                        .sidebar-item .tags ul.tags li {
                            margin: 0;
                        }
                        .sidebar-item .tags ul.tags li a {
                            display: inline-block;
                            padding: 5px 12px;
                            background: #f8f9fa;
                            color: #353935;
                            border-radius: 20px;
                            font-size: 14px;
                            transition: all 0.3s;
                            text-decoration: none !important;
                        }
                        body .sidebar-item .tags ul.tags li a:hover,
                        .sidebar-item .tags ul.tags li a:hover,
                        .sidebar .sidebar-item .tags ul.tags li a:hover {
                            background-color: #f48840 !important;
                            color: #ffffff !important;
                            transform: translateY(-2px);
                            text-decoration: none !important;
                        }
                        .sidebar-item .tags ul.tags li a.tag-1 { font-size: 12px; opacity: 0.85; }
                        .sidebar-item .tags ul.tags li a.tag-2 { font-size: 14px; opacity: 0.9; }
                        .sidebar-item .tags ul.tags li a.tag-3 { font-size: 16px; opacity: 0.95; }
                        .sidebar-item .tags ul.tags li a.tag-4 { font-size: 18px; opacity: 1; }
                        .sidebar-item .tags ul.tags li a.tag-5 { font-size: 20px; opacity: 1; }
                    </style>
                </div>
            </div>
        </div>
    </div>
</div> 