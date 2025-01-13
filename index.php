<?php
require_once 'config.php';
require_once 'functions.php';

// Get featured posts (all published posts)
$featured_posts_query = "SELECT p.*, u.username, u.avatar, c.name as category_name, p.image_path 
                        FROM posts p 
                        LEFT JOIN users u ON p.author_id = u.id 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.status = 'published' 
                        AND p.featured = 1
                        AND p.deleted_at IS NULL 
                        AND p.is_active = 1
                        AND c.deleted_at IS NULL 
                        AND c.is_active = 1
                        AND u.deleted_at IS NULL 
                        AND u.is_active = 1
                        ORDER BY p.created_at DESC";
$featured_posts_result = $conn->query($featured_posts_query);

// Store featured posts in an array for reuse
$featured_posts = array();
while ($post = $featured_posts_result->fetch_assoc()) {
    $featured_posts[] = $post;
}

// Use all featured posts for the featured cards section
$featured_cards = $featured_posts;

// Get categories with post count
$categories_query = "SELECT c.*, COUNT(p.id) as post_count 
                    FROM categories c 
                    LEFT JOIN posts p ON c.id = p.category_id AND p.status = 'published'
                    WHERE c.deleted_at IS NULL 
                    AND c.is_active = 1
                    GROUP BY c.id 
                    ORDER BY post_count DESC 
                    LIMIT 4";
$categories = $conn->query($categories_query);

$page_title = "Welcome to NetPy Blog";
include 'includes/header.php';
?>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
        position: relative;
    }
    .hero-section {
        background: #fff;
        padding: 120px 0;
        position: relative;
        overflow: hidden;
        margin: 0 auto;
        min-height: 600px;
    }
    .hero-content {
        width: 50%;
        padding-right: 50px;
        padding-top: 40px;
        position: relative;
        z-index: 2;
    }
    .hero-slider {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        width: 45%;
        height: 400px;
        z-index: 1;
    }
    .hero-slider .carousel-item {
        height: 400px;
    }
    .hero-slider .carousel-item img {
        height: 100%;
        object-fit: cover;
        border-radius: 10px;
    }
    .hero-slider .carousel-caption {
        background: none;
        border-radius: 0;
        padding: 20px 40px;
        bottom: 30px;
        left: 40px;
        text-align: left;
        max-width: 80%;
        margin-left: auto;
    }
    .hero-slider .carousel-caption h5 {
        font-size: 2rem;
        margin-bottom: 15px;
        text-shadow: none;
        color: #fff;
        font-weight: 600;
        line-height: 1.2;
    }
    .hero-slider .carousel-caption p {
        font-size: 1.2rem;
        margin-bottom: 20px;
        text-shadow: none;
        color: #eee;
        line-height: 1.4;
    }
    .hero-slider .carousel-caption .badge {
        background: #4A90E2;
        font-weight: 500;
        padding: 5px 15px;
    }
    .hero-slider .carousel-item::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50%;
        background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
        z-index: 1;
    }
    .hero-slider .carousel-caption {
        z-index: 2;
    }
    .hero-slider .carousel-control-prev,
    .hero-slider .carousel-control-next {
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
        opacity: 1;
        z-index: 5;
        border: 1px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    .hero-slider .carousel-control-prev:hover,
    .hero-slider .carousel-control-next:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        transform: translateY(-50%) scale(1.05);
    }
    .hero-slider .carousel-control-prev i,
    .hero-slider .carousel-control-next i {
        color: #fff;
        font-size: 22px;
        line-height: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .hero-slider .carousel-indicators {
        display: none;
    }
    .hero-slider .carousel-indicators [data-bs-target] {
        display: none;
    }
    .hero-slider .carousel-indicators .active {
        display: none;
    }
    .hero-slider .carousel-control-prev {
        left: 20px;
    }
    .hero-slider .carousel-control-next {
        right: 20px;
    }
    .hero-overlay {
        display: none;
    }
    .hero-subtitle {
        color: #4A90E2;
        font-size: 1.1rem;
        font-weight: 500;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .hero-title {
        font-size: 4.5rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        color: #000;
    }
    .hero-description {
        font-size: 1.2rem;
        color: #666;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    .hero-cta {
        background: #0047cc;
        color: white;
        padding: 15px 30px;
        border-radius: 15px;
        text-decoration: none;
        font-weight: 500;
        display: inline-block;
        transition: all 0.3s ease;
    }
    .hero-cta:hover {
        background: #0039a6;
        color: white;
        transform: translateY(-2px);
    }
    .section-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 60px 15px;
    }
    .featured-post-card {
        border: none;
        transition: transform 0.3s;
        margin-bottom: 30px;
    }
    .featured-post-card:hover {
        transform: translateY(-5px);
    }
    .category-card {
        border: none;
        background: #f8f9fa;
        transition: all 0.3s;
        text-align: center;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-radius: 15px;
        height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .category-card h4 {
        margin-bottom: 10px;
        font-size: 1.25rem;
        font-weight: 600;
    }
    .category-card p {
        margin: 0;
        font-size: 0.9rem;
    }
    .category-card:hover {
        background: #4A90E2;
        color: white;
    }
    .section-title {
        text-align: center;
        margin-bottom: 40px;
        position: relative;
        padding-bottom: 15px;
    }
    .section-title:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 3px;
        background: #4A90E2;
    }
    @media (max-width: 991px) {
        .hero-section {
            min-height: auto;
            padding: 120px 0 80px;
        }
        .hero-content {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
            padding: 0;
        }
        .hero-slider {
            position: relative;
            width: 100%;
            height: 400px;
            transform: none;
            top: auto;
            right: auto;
            margin-top: 40px;
        }
        .hero-title {
            font-size: 3.5rem;
        }
        .hero-title br {
            display: none;
        }
        .hero-description {
            margin: 0 auto 2rem auto;
        }
        .hero-slider .carousel-caption {
            padding: 20px;
            left: 20px;
            right: 20px;
            max-width: 100%;
            margin-left: 0;
            bottom: 25%;
            transform: translateY(50%);
        }
        .hero-slider .carousel-item::before {
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.4) 100%);
        }
    }
    
    @media (max-width: 576px) {
        .hero-section {
            padding: 100px 0 60px;
        }
        .hero-content {
            padding: 20px 15px 0;
        }
        .hero-slider {
            height: 300px;
            margin-top: 30px;
        }
        .hero-title {
            font-size: 2.8rem;
        }
        .hero-title br {
            display: block;
        }
        .hero-description {
            font-size: 1.1rem;
        }
        .hero-slider .carousel-caption h5 {
            font-size: 1.5rem;
        }
        .hero-slider .carousel-caption p {
            font-size: 1rem;
        }
        .hero-slider .carousel-caption {
            padding: 15px;
            left: 15px;
            right: 15px;
            bottom: 30%;
            transform: translateY(50%);
        }
        .hero-slider .carousel-caption h5 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .hero-slider .carousel-caption p {
            font-size: 1rem;
            margin-bottom: 15px;
        }
    }
    .hero-slider .carousel-caption .btn-light {
        padding: 8px 25px;
        border-radius: 30px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: #fff;
        color: #333;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .hero-slider .carousel-caption .btn-light:hover {
        background: #4A90E2;
        color: #fff;
        transform: translateY(-2px);
    }
    .cta-button {
        background: #0047cc;
        border: none;
        border-radius: 20px;
        padding: 12px 35px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .cta-button:hover {
        background: #0039a6;
        transform: translateY(-2px);
    }
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <div class="hero-subtitle">Your Gateway to Tech Knowledge</div>
            <h1 class="hero-title">Welcome to<br> NetPy Blog</h1>
            <p class="hero-description">Explore a world of programming insights, tech tutorials, and development best practices. From Python development to network engineering, discover expert articles that help you grow as a developer.</p>
            <a href="home.php" class="hero-cta">READ BLOGS</a>
        </div>
        <div id="heroSlider" class="carousel slide hero-slider" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach($featured_posts as $index => $post): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php if(isset($post['image_path']) && !empty($post['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        <?php else: ?>
                            <img src="assets/images/default-post.jpg" class="d-block w-100" alt="Default Image">
                        <?php endif; ?>
                        <div class="carousel-caption">
                            <h5><?php echo htmlspecialchars($post['title']); ?></h5>
                            <p><?php echo substr(strip_tags($post['content']), 0, 80); ?>...</p>
                            <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-sm btn-light">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
                <i class="fa fa-angle-left" aria-hidden="true"></i>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
                <i class="fa fa-angle-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>

<!-- Recent Posts Section -->
<section class="section-wrapper">
    <h2 class="section-title">Recent Posts</h2>
    <div class="row">
        <?php
        // Get recent posts
        $recent_posts_query = "SELECT p.*, u.username, u.avatar, c.name as category_name, p.image_path 
                            FROM posts p 
                            LEFT JOIN users u ON p.author_id = u.id 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.status = 'published'
                            AND p.deleted_at IS NULL 
                            AND p.is_active = 1
                            AND c.deleted_at IS NULL 
                            AND c.is_active = 1
                            AND u.deleted_at IS NULL 
                            AND u.is_active = 1
                            ORDER BY p.created_at DESC
                            LIMIT 3";
        $recent_posts_result = $conn->query($recent_posts_query);
        while($post = $recent_posts_result->fetch_assoc()):
        ?>
            <div class="col-md-4">
                <div class="card featured-post-card" style="border-radius: 20px; min-height: 450px; width: 100%;">
                    <?php if(isset($post['image_path']) && !empty($post['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>" style="height: 250px; width: 100%; object-fit: cover; border-radius: 20px;">
                    <?php else: ?>
                        <img src="assets/images/default-post.jpg" class="card-img-top" alt="Default Image" style="height: 250px; width: 100%; object-fit: cover; border-radius: 20px;">
                    <?php endif; ?>
                    <div class="card-body" style="padding: 10px;">
                        <span class="badge bg-primary" style="color: white; padding: 3px 10px; border-radius: 10px;"><?php echo htmlspecialchars($post['category_name']); ?></span>
                        <h5 class="card-title mt-2" style="overflow: hidden; margin-bottom: 10px;"><?php echo htmlspecialchars($post['title']); ?></h5>
                        <p class="card-text" style=" overflow: hidden; margin-bottom: 0px;"><?php echo substr(strip_tags($post['content']), 0, 100); ?>...</p>
                        <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn" style="border-radius: 13px; color: #0047cc;">View >>></a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <div class="text-center mt-0">
        <a href="home.php" class="btn btn-outline-primary" style="border-radius: 20px; padding: 8px 25px;">See All Blogs</a>
    </div>
</section>

<!-- Newsletter Section -->
<section class="section-wrapper" style="background-color: #f8f9fa; border-radius: 20px; padding: 40px 20px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h2 class="section-title">Subscribe to Our Newsletter</h2>
                <p class="mb-4">Stay updated with our latest blogs and tech insights delivered to your inbox.</p>
                <div id="newsletter-message" class="alert" style="display: none; margin-bottom: 15px;"></div>
                <form id="newsletter-form" class="d-flex justify-content-center gap-2">
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" style="max-width: 400px; border-radius: 15px; padding: 20px 15px; outline: none; box-shadow: none;" required>
                    <button type="submit" class="btn btn-primary" style="border-radius: 20px; padding: 5px 20px; height: 40px; margin-left: 10px; min-width: 100px;">
                        <span class="normal-text">Subscribe</span>
                        <div class="loading-state" style="display: none;">
                            <i class="fas fa-circle-notch fa-spin" style="color: white;"></i>
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="section-wrapper">
    <h2 class="section-title">Popular Categories</h2>
    <div class="row">
        <?php while($category = $categories->fetch_assoc()): ?>
            <div class="col-md-3">
                <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>" class="text-decoration-none">
                    <div class="category-card">
                        <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                        <p class="mb-0"><?php echo $category['post_count']; ?> Posts</p>
                    </div>
                </a>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- Call to Action -->
<section class="section-wrapper text-center">
    <div class="py-5">
        <h2>Ready to Start Reading?</h2>
        <p class="lead">Explore our full collection of articles and stories.</p>
        <a href="home.php" class="btn btn-primary btn-lg cta-button" style="margin-top: 15px;">Read Blogs</a>
    </div>
</section>

<!-- Initialize the carousel -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const myCarousel = document.querySelector('#heroSlider');
    if (myCarousel) {
        const carousel = new bootstrap.Carousel(myCarousel, {
            interval: 5000,
            wrap: true,
            keyboard: true,
            pause: 'hover',
            touch: true
        });

        // Add manual controls
        const prevButton = myCarousel.querySelector('.carousel-control-prev');
        const nextButton = myCarousel.querySelector('.carousel-control-next');
        
        prevButton.addEventListener('click', function() {
            carousel.prev();
        });
        
        nextButton.addEventListener('click', function() {
            carousel.next();
        });
    }
});
</script>

<script>
document.getElementById('newsletter-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const email = form.querySelector('input[name="email"]').value;
    const messageDiv = document.getElementById('newsletter-message');
    const submitBtn = form.querySelector('button[type="submit"]');
    const loadingState = submitBtn.querySelector('.loading-state');
    const buttonText = submitBtn.querySelector('.normal-text');
    
    // Show loading state
    submitBtn.disabled = true;
    loadingState.style.display = 'block';
    buttonText.style.display = 'none';
    
    fetch('subscribe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'email=' + encodeURIComponent(email)
    })
    .then(response => response.json())
    .then(data => {
        messageDiv.style.display = 'block';
        messageDiv.className = 'alert';
        
        if (data.status === 'success') {
            messageDiv.classList.add('alert-success');
            form.reset();
        } else if (data.status === 'info') {
            messageDiv.classList.add('alert-info');
        } else {
            messageDiv.classList.add('alert-danger');
        }
        
        messageDiv.textContent = data.message;
    })
    .catch(error => {
        messageDiv.style.display = 'block';
        messageDiv.className = 'alert alert-danger';
        messageDiv.textContent = 'An error occurred. Please try again later.';
    })
    .finally(() => {
        // Reset loading state
        submitBtn.disabled = false;
        loadingState.style.display = 'none';
        buttonText.style.display = 'inline-block';
        
        // Hide the message after 5 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    });
});
</script>

<?php include 'includes/footer.php'; ?> 