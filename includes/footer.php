<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Fetch categories and tags if not already set
if (!isset($categories) || !is_array($categories)) {
    $categories = get_footer_categories($conn);
}
if (!isset($tags) || !is_array($tags)) {
    $tags = get_footer_tags($conn);
}
?>
<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <div class="footer-widget">
                    <h4>Categories</h4>
                    <ul class="footer-categories">
                        <?php if (isset($categories) && is_array($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <li>
                                    <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                        <span>(<?php echo $category['post_count']; ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="footer-widget">
                    <h4>Popular Tags</h4>
                    <ul class="footer-tags">
                        <?php if (isset($tags) && is_array($tags)): ?>
                            <?php foreach ($tags as $tag): ?>
                                <li>
                                    <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>">
                                        <?php echo htmlspecialchars($tag['name']); ?>
                                        <span>(<?php echo $tag['post_count']; ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="footer-widget">
                    <h4>Follow Us</h4>
                    <ul class="social-icons">
                        <li><a href="https://www.youtube.com/@netpytech" target="_blank"><i class="fa fa-youtube-play"></i></a></li>
                        <li><a href="https://x.com/netpytech" target="_blank"><i class="fa fa-twitter"></i></a></li>
                        <li><a href="https://www.linkedin.com/company/netpy-tech" target="_blank"><i class="fa fa-linkedin"></i></a></li>
                        <li><a href="https://www.instagram.com/netpykidz" target="_blank"><i class="fa fa-instagram"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="copyright-text">
                    <p>Copyright © <?php echo date('Y'); ?> NetPy Blog. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    footer {
        background-color: #20232e;
        padding: 60px 0 30px 0;
        color: #fff;
    }

    .footer-widget {
        margin-bottom: 30px;
    }

    .footer-widget h4 {
        color: #fff;
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        position: relative;
        padding-bottom: 10px;
        text-align: center;
    }

    .footer-widget h4:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 2px;
        background: #0047cc;
    }

    .footer-categories, .footer-tags {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }

    .footer-categories li, .footer-tags li {
        margin-bottom: 10px;
    }

    .footer-categories li a, .footer-tags li a {
        color: #fff;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        justify-content: center;
        align-items: left;
        gap: 8px;
        width: 100%;
    }

    .footer-categories li a span, .footer-tags li a span {
        color: rgba(255,255,255,0.5);
        font-size: 12px;
    }

    .footer-categories li a:hover, .footer-tags li a:hover {
        color: #0047cc;
        text-decoration: none;
    }

    .social-icons {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    .social-icons li a {
        color: #fff;
        font-size: 18px;
        transition: all 0.3s;
    }

    .social-icons li a:hover {
        color: #0047cc;
    }

    .copyright-text {
        text-align: center;
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .copyright-text p {
        color: rgba(255,255,255,0.7);
        font-size: 14px;
        margin: 0;
    }
</style> 