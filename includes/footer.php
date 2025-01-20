<?php
// Get categories with post count - Limited to 10 for footer
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

// Get tags for footer - Limited to 10
$footer_tags_sql = "SELECT t.*, COUNT(DISTINCT pt.post_id) as post_count 
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
             ORDER BY post_count DESC, t.name
             LIMIT 10";
$footer_tags_result = $conn->query($footer_tags_sql);
$footer_tags = $footer_tags_result ? $footer_tags_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<footer>
    <div class="container">
        <div class="row">
            <!-- Categories Column -->
            <div class="col-lg-4 col-6">
                <div class="footer-section">
                    <h4>Categories</h4>
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
            
            <!-- Tags Column -->
            <div class="col-lg-4 col-6">
                <div class="footer-section">
                    <h4>Tags</h4>
                    <ul class="footer-tags">
                        <?php foreach ($footer_tags as $tag): ?>
                            <li><a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>"><?php echo htmlspecialchars($tag['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($footer_tags) >= 10): ?>
                        <a href="#" class="see-more" data-type="tags">See More</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Social Media Column -->
            <div class="col-lg-4 col-12">
                <div class="footer-section social-section">
                    <h4>Connect With Us</h4>
                    <!-- Newsletter Form -->
                    <div class="newsletter-section">
                        <h5>Subscribe to Newsletter</h5>
                        <div id="footer-newsletter-message" class="alert" style="display: none;"></div>
                        <form id="footer-newsletter-form" class="newsletter-form">
                            <div class="newsletter-form-content">
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

                    <ul class="social-icons">
                        <li><a href="https://www.instagram.com/netpykidz" target="_blank"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="https://x.com/netpytech" target="_blank"><i class="fab fa-x-twitter"></i></a></li>
                        <li><a href="https://whatsapp.com/channel/0029VazDKGL6xCSNuKAtnp18" target="_blank"><i class="fab fa-whatsapp"></i></a></li>
                        <li><a href="https://www.linkedin.com/company/netpy-tech" target="_blank"><i class="fab fa-linkedin"></i></a></li>
                    </ul>
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
/* Social section styling */
.social-section {
    text-align: center;
    border: none;
}

.social-section:after,
.social-section:before {
    display: none;
}

/* Newsletter section styling */
.newsletter-section {
    margin: 20px 0;
    padding: 25px;
    border-radius: 15px;
    background: #1a1f2b;
    border: none;
}

.newsletter-section:after,
.newsletter-section:before {
    display: none;
}

/* Responsive adjustments */
@media (max-width: 991px) {
    .social-section {
        margin-top: 20px;
        padding-top: 20px;
        border: none;
    }

    .footer-section h4 {
        text-align: center !important;
        margin-bottom: 20px;
    }

    .footer-categories, 
    .footer-tags {
        text-align: center !important;
        padding: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .footer-categories li, 
    .footer-tags li {
        text-align: center !important;
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .footer-categories a, 
    .footer-tags a {
        text-align: center !important;
        display: inline-block;
        padding: 5px 0;
        width: auto;
    }

    .see-more {
        text-align: center !important;
        margin: 15px auto 0;
        display: block;
        width: 100%;
    }
}

@media (max-width: 576px) {
    .newsletter-form-content {
        gap: 12px;
    }
    
    .newsletter-form input,
    .newsletter-form button {
        padding: 12px 20px;
    }

    .footer-section h4 {
        font-size: 20px;
    }
    
    .footer-categories a, 
    .footer-tags a {
        font-size: 15px;
    }
}

/* Footer section general */
.footer-section {
    margin-bottom: 20px;
    border: none;
}

.footer-section:after,
.footer-section:before {
    display: none;
}

.footer-section h4 {
    color: #fff;
    margin-bottom: 12px;
    font-size: 22px;
    font-weight: 600;
    text-align: left;
}

.footer-categories, .footer-tags {
    list-style: none;
    padding: 0;
    margin: 0;
    min-height: 150px;
    text-align: left;
}

.footer-categories li, .footer-tags li {
    margin-bottom: 8px;
    text-align: left;
}

.footer-categories a, .footer-tags a {
    color: #ccc;
    text-decoration: none;
    font-size: 16px;
    transition: color 0.3s ease;
    display: block;
    padding: 2px 0;
    line-height: 1.3;
    text-align: left;
}

.footer-categories a:hover, .footer-tags a:hover {
    color: #0d47a1;
}

.see-more {
    display: block;
    color: #0d47a1;
    text-decoration: none;
    font-size: 16px;
    margin-top: 15px;
    text-align: left;
    width: fit-content;
}

.see-more:hover {
    text-decoration: underline;
}

/* Social section styling */
.social-icons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin: 25px 0;
    padding: 0 !important;
    list-style: none;
    border: none !important;
    border-bottom: none !important;
}

/* Override the main CSS file's social icons styles */
footer ul.social-icons {
    padding-bottom: 0 !important;
    margin-bottom: 25px !important;
    border: none !important;
    border-bottom: none !important;
}

.social-icons li {
    margin: 0;
    padding: 0;
    border: none;
    display: flex;
}

.social-icons li:after,
.social-icons li:before {
    display: none;
}

.social-icons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    color: #ccc;
    text-decoration: none;
    font-size: 18px;
    transition: all 0.3s ease;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: none;
}

.social-icons a:after,
.social-icons a:before {
    display: none;
}

.social-icons i {
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.social-icons a:hover {
    color: #fff;
    background: #0d47a1;
    transform: translateY(-3px);
}

/* Modal styling */
#seeMoreModal .modal-content {
    background: #fff;
    border-radius: 8px;
}

#seeMoreModal .modal-header {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem;
}

#seeMoreModal .modal-body {
    padding: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

#modalContent ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

#modalContent ul li a {
    display: inline-block;
    padding: 5px 12px;
    background: #f8f9fa;
    border-radius: 20px;
    color: #666;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

#modalContent ul li a:hover {
    background: #0d47a1;
    color: #fff;
}

/* Updated Newsletter Form Styling */
.newsletter-section h5 {
    color: #fff;
    font-size: 1rem;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 500;
}

.newsletter-form-content {
    display: flex;
    flex-direction: column;
    gap: 0px;
}

.newsletter-form input {
    background: #2a2f3b;
    border: none;
    color: #fff;
    padding: 20px 20px;
    font-size: 15px;
    width: 100%;
    outline: none;
    border-radius: 25px;
}

.newsletter-form input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.newsletter-form input:focus {
    background: #2f3545;
    box-shadow: none;
    color: #fff;
}

.newsletter-form button {
    position: relative;
    margin-top: -10px;
    background: #0d47a1;
    border: none;
    padding: 12px 20px;
    border-radius: 25px;
    color: #fff;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.newsletter-form button:hover {
    background: #1565c0;
    transform: translateY(-1px);
}

.newsletter-form .loading-state {
    display: none;
}

#footer-newsletter-message {
    font-size: 14px;
    padding: 10px;
    margin: 10px 0;
    border-radius: 8px;
    text-align: center;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

#footer-newsletter-message.alert-success {
    background: rgba(46, 125, 50, 0.2);
    border-color: rgba(46, 125, 50, 0.3);
    color: #81c784;
}

#footer-newsletter-message.alert-danger {
    background: rgba(198, 40, 40, 0.2);
    border-color: rgba(198, 40, 40, 0.3);
    color: #e57373;
}

#footer-newsletter-message.alert-info {
    background: rgba(2, 136, 209, 0.2);
    border-color: rgba(2, 136, 209, 0.3);
    color: #4fc3f7;
}

@media (max-width: 576px) {
    .newsletter-form-content {
        gap: 12px;
    }
    
    .newsletter-form input,
    .newsletter-form button {
        padding: 12px 20px;
    }
}

/* Social section specific styles */
.social-section h4 {
    text-align: center;
}

/* Updated Copyright Section */
.copyright-section {
    margin-top: 30px;
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    width: 100%;
}

.copyright-text {
    margin: 0;
    color: #ccc;
    font-size: 16px;
    text-align: center;
}

.copyright-text p {
    margin: 0;
    padding: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const seeMoreButtons = document.querySelectorAll('.see-more');
    const modalElement = document.getElementById('seeMoreModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Close button handler
    const closeButton = modalElement.querySelector('.btn-close');
    closeButton.addEventListener('click', () => {
        modal.hide();
    });
    
    // Close on backdrop click
    modalElement.addEventListener('click', (e) => {
        if (e.target === modalElement) {
            modal.hide();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalElement.classList.contains('show')) {
            modal.hide();
        }
    });
    
    seeMoreButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            
            // Set modal title
            document.querySelector('.modal-title').textContent = 
                type === 'categories' ? 'All Categories' : 'All Tags';
            
            // Fetch all items
            fetch(`ajax/get_${type}.php`)
                .then(response => response.json())
                .then(data => {
                    const modalContent = document.getElementById('modalContent');
                    const ul = document.createElement('ul');
                    
                    data.forEach(item => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = `${type === 'categories' ? 'category' : 'tag'}.php?slug=${encodeURIComponent(item.slug)}`;
                        a.textContent = item.name;
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                    
                    modalContent.innerHTML = '';
                    modalContent.appendChild(ul);
                    modal.show();
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