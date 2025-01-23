<?php
// Get categories for footer - Limited to 10
$footer_categories_sql = "SELECT c.*, COUNT(p.id) as post_count 
                  FROM categories c 
                  LEFT JOIN posts p ON c.id = p.category_id 
                  AND p.status = 'published' 
                  AND p.deleted_at IS NULL 
                  AND p.is_active = 1
                  WHERE c.deleted_at IS NULL 
                  AND c.is_active = 1
                  GROUP BY c.id 
                  HAVING post_count > 0
                  ORDER BY c.name
                  LIMIT 10";
$footer_categories_result = $conn->query($footer_categories_sql);
$footer_categories = $footer_categories_result ? $footer_categories_result->fetch_all(MYSQLI_ASSOC) : [];

// Determine settings link based on user role
$settings_link = 'login.php';
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Get current path to determine if we're in admin/author directory
    $current_path = $_SERVER['PHP_SELF'];
    $is_admin_page = strpos($current_path, '/admin/') !== false;
    $is_author_page = strpos($current_path, '/author/') !== false;
    $base_url = ($is_admin_page || $is_author_page) ? '../' : '';
    
    // Set the settings link with the correct base URL
    $settings_link = $base_url . 'user-settings.php';
    
    // If not logged in, use login page
    if (!isset($_SESSION['user_id'])) {
        $settings_link = $base_url . 'login.php';
    }
}
?>

<footer>
    <div class="container">
        <div class="row">
            <!-- Navigation Links -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul class="footer-nav">
                        <li><a href="home.php">Home</a></li>
                        <li><a href="<?php echo $settings_link; ?>"><?php echo isset($_SESSION['user_id']) ? 'Settings' : 'Login'; ?></a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms-conditions.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Categories Column -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-section">
                    <h4>Popular Categories</h4>
                    <ul class="footer-categories">
                        <?php foreach ($footer_categories as $category): ?>
                            <li><a href="category.php?slug=<?php echo urlencode($category['slug']); ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($footer_categories) >= 10): ?>
                        <a href="#" class="see-more" data-type="categories">See More</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Newsletter & Social Column -->
            <div class="col-lg-4 col-md-12">
                <div class="footer-section social-section">
                    <h4>Stay Connected</h4>
                    <!-- Newsletter Form -->
                    <div class="newsletter-section">
                        <h5>Subscribe to Newsletter</h5>
                        <div id="footer-newsletter-message" class="alert" style="display: none;"></div>
                        <form id="footer-newsletter-form" class="newsletter-form">
                            <div class="newsletter-form-content" style="position: relative; margin-top: 10px;">
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                <button type="submit" class="btn btn-primary">
                                    <span class="button-text">Subscribe</span>
                                    <span class="loading-state" style="display: none;">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="social-media-section">
                        <div class="social-icons">
                            <a href="https://www.instagram.com/netpykidz" target="_blank"><i class="fab fa-instagram"></i></a>
                            <a href="https://x.com/netpytech" target="_blank"><i class="fab fa-x-twitter"></i></a>
                            <a href="https://whatsapp.com/channel/0029VazDKGL6xCSNuKAtnp18" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <a href="https://www.linkedin.com/company/netpy-tech" target="_blank"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Copyright Section -->
    <div class="copyright-section">
        <div class="container">
            <div class="copyright-text">
                <p>Copyright © <?php echo date('Y'); ?> NetPy Technologies</p>
            </div>
        </div>
    </div>
</footer>

<!-- Modal for See More -->
<div class="modal fade" id="seeMoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
</div>

<style>
/* Updated Footer Styles */
footer {
    background: #1a1f2b;
    padding: 60px 0 0;
    color: #fff;
}

/* Footer Navigation Styles */
.footer-nav {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 992px) {
    .footer-nav {
        align-items: flex-start;
    }
}

@media (max-width: 991px) {
    .footer-nav {
        align-items: center;
    }
}

.footer-nav li a {
    color: #ccc;
    text-decoration: none;
    font-size: 16px;
    transition: color 0.3s ease;
    display: inline-block;
    position: relative;
}

.footer-nav li a:hover {
    color: #0d47a1;
    transform: translateX(5px);
}

.footer-nav li a::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -2px;
    left: 0;
    background-color: #0d47a1;
    transition: width 0.3s ease;
}

.footer-nav li a:hover::after {
    width: 100%;
}

/* Footer Section Styles */
.footer-section {
    margin-bottom: 30px;
    padding: 0 15px;
    text-align: center;
}

@media (min-width: 992px) {
    .col-lg-4:first-child .footer-section {
        text-align: left;
        padding-left: 130px;
    }
    
    .col-lg-4:first-child .footer-section h4::after,
    .col-lg-4:nth-child(2) .footer-section h4::after,
    .col-lg-4:nth-child(3) .footer-section h4::after {
        left: 0;
        transform: none;
    }

    .col-lg-4:nth-child(2) .footer-section {
        text-align: left;
    }

    .col-lg-4:nth-child(3) .footer-section {
        text-align: left;
    }

    .col-lg-4:nth-child(2) .footer-categories {
        justify-content: flex-start;
    }
}

.footer-section h4 {
    color: #fff;
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 20px;
    position: relative;
    padding-bottom: 10px;
    display: inline-block;
}

.footer-section h4::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 0;
    width: 50px;
    height: 2px;
    background: #0d47a1;
    transform: translateX(-50%);
}

/* Categories Section */
.footer-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0;
    margin: 0;
    list-style: none;
    justify-content: center;
}

.footer-categories li a {
    display: inline-block;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: #ccc;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.footer-categories li a:hover {
    background: #0d47a1;
    color: #fff;
    transform: translateY(-2px);
}

/* Newsletter Section */
.newsletter-section {
    background: rgba(255, 255, 255, 0.05);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 20px;
    border: none;
}

.newsletter-form-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.newsletter-form input {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    padding: 12px 20px;
    border-radius: 25px;
    color: #fff;
}

.newsletter-form button {
    background: #0d47a1;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.newsletter-form button:hover {
    background: #1565c0;
    transform: translateY(-2px);
}

/* Social Media Section */
.social-media-section {
    margin: 20px 0;
}

.social-icons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 0;
    padding: 0;
}

.social-icons a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: #fff;
    font-size: 18px;
    transition: all 0.3s ease;
    text-decoration: none;
    border: none;
}

.social-icons a:hover {
    background: #0d47a1;
    transform: translateY(-3px);
    color: #fff;
}

/* Remove any potential borders or lines */
.social-section {
    border: none;
}

.social-section::before,
.social-section::after {
    display: none;
}

.social-media-section::before,
.social-media-section::after {
    display: none;
}

/* Copyright Section */
.copyright-section {
    margin-top: 40px;
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.copyright-text {
    color: #ccc;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 991px) {
    .footer-section {
        margin-bottom: 40px;
    }

    .footer-section h4::after {
        /* Remove this since it's now handled in the main styles */
    }

    .footer-nav {
        /* Remove this since it's now handled in the main styles */
    }

    .footer-categories {
        /* Remove this since it's now handled in the main styles */
    }

    .newsletter-section {
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
}

@media (max-width: 767px) {
    footer {
        padding: 40px 0 0;
    }

    .footer-section {
        margin-bottom: 30px;
    }

    .footer-nav li a {
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .footer-section h4 {
        font-size: 20px;
    }

    .newsletter-form input,
    .newsletter-form button {
        font-size: 14px;
        padding: 10px 15px;
    }

    .social-icons a {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
}

@media (max-width: 320px) {
    .footer-section {
        padding: 0 10px;
    }

    .newsletter-section {
        padding: 15px;
    }

    .footer-categories li a {
        padding: 4px 10px;
        font-size: 13px;
    }
}

/* Modal styling */
#seeMoreModal .modal-content {
    background: #1a1f2b;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

#seeMoreModal .modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 20px;
}

#seeMoreModal .modal-title {
    color: #fff;
    font-size: 22px;
    font-weight: 600;
}

#seeMoreModal .btn-close {
    color: #fff;
    opacity: 0.8;
    filter: invert(1) grayscale(100%) brightness(200%);
}

#seeMoreModal .modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

#seeMoreModal .modal-body::-webkit-scrollbar {
    width: 8px;
}

#seeMoreModal .modal-body::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

#seeMoreModal .modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

#seeMoreModal .modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

#modalContent ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

#modalContent ul li {
    margin: 0;
}

#modalContent ul li a {
    display: inline-block;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 25px;
    color: #fff;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

#modalContent ul li a:hover {
    background: #0d47a1;
    color: #fff;
    transform: translateY(-2px);
    border-color: #0d47a1;
}

@media (max-width: 576px) {
    #seeMoreModal .modal-content {
        margin: 10px;
        border-radius: 12px;
    }
    
    #seeMoreModal .modal-header {
        padding: 15px;
    }
    
    #seeMoreModal .modal-body {
        padding: 15px;
    }
    
    #modalContent ul {
        gap: 8px;
    }
    
    #modalContent ul li a {
        padding: 6px 12px;
        font-size: 14px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seeMoreButtons = document.querySelectorAll('.see-more');
    const modalElement = document.getElementById('seeMoreModal');
    let modalInstance;

    // Initialize modal with a check for Bootstrap availability
    function initializeModal() {
        if (typeof bootstrap !== 'undefined') {
            modalInstance = new bootstrap.Modal(modalElement);
        } else {
            console.error('Bootstrap is not loaded. Please ensure bootstrap.js is included.');
            return false;
        }
        return true;
    }

    // Initialize modal
    initializeModal();
    
    // Close button handler
    const closeButton = modalElement.querySelector('.btn-close');
    closeButton.addEventListener('click', () => {
        if (modalInstance) modalInstance.hide();
    });
    
    // Close on backdrop click
    modalElement.addEventListener('click', (e) => {
        if (e.target === modalElement && modalInstance) {
            modalInstance.hide();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalElement.classList.contains('show') && modalInstance) {
            modalInstance.hide();
        }
    });
    
    seeMoreButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            
            // Check if modal is properly initialized
            if (!modalInstance && !initializeModal()) {
                alert('Unable to show modal. Please try again later.');
                return;
            }
            
            // Set modal title
            document.querySelector('.modal-title').textContent = 
                type === 'categories' ? 'All Categories' : 'All Tags';
            
            // Fetch all items
            fetch(`/ajax/get_${type}.php`)
                .then(response => response.json())
                .then(data => {
                    const modalContent = document.getElementById('modalContent');
                    const ul = document.createElement('ul');
                    
                    data.forEach(item => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = `/${type === 'categories' ? 'category' : 'tag'}.php?slug=${encodeURIComponent(item.slug)}`;
                        a.textContent = item.name;
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                    
                    modalContent.innerHTML = '';
                    modalContent.appendChild(ul);
                    modalInstance.show();
                })
                .catch(error => console.error('Error:', error));
        });
    });
});

// Newsletter Form Handler
document.getElementById('footer-newsletter-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const email = form.querySelector('input[name="email"]').value;
    const messageDiv = document.getElementById('footer-newsletter-message');
    const submitBtn = form.querySelector('button[type="submit"]');
    const loadingState = submitBtn.querySelector('.loading-state');
    const buttonText = submitBtn.querySelector('.button-text');
    
    // Show loading state
    submitBtn.disabled = true;
    loadingState.style.display = 'inline-block';
    buttonText.style.display = 'none';
    
    // Create form data
    const formData = new FormData();
    formData.append('email', email);
    
    fetch('subscribe.php', {
        method: 'POST',
        body: formData
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
        console.error('Error:', error);
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