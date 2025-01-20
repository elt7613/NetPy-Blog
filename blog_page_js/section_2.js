document.addEventListener('DOMContentLoaded', function() {
    const blogGrid = document.getElementById('blogGrid');
    const loadMoreBtn = document.getElementById('loadMore');
    const showLessBtn = document.getElementById('showLess');
    const searchInput = document.querySelector('.search-input');
    const filterType = document.querySelector('.filter-type');
    const tags = document.querySelectorAll('.tag');
    const blogCards = document.querySelectorAll('.blog-card');
    const popup = document.getElementById('categoriesPopup');
    const showMoreBtn = document.querySelector('.show-more-btn');
    const closePopupBtn = document.querySelector('.close-popup');
    const popupTags = document.querySelectorAll('.popup-tag');
    
    let currentCategory = 'all';
    let visibleCards = 6;
    const initialCards = 6;
    const increment = 6;

    // Initial setup
    updateVisibility();

    // Show More Categories Button
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', () => {
            popup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
    }

    // Close popup when clicking the close button
    closePopupBtn.addEventListener('click', () => {
        popup.style.display = 'none';
        document.body.style.overflow = 'auto';
    });

    // Close popup when clicking outside
    popup.addEventListener('click', (e) => {
        if (e.target === popup) {
            popup.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Category filter functionality (main tags)
    tags.forEach(tag => {
        if (!tag.classList.contains('show-more-btn')) {
            tag.addEventListener('click', () => {
                handleCategoryClick(tag);
            });
        }
    });

    // Category filter functionality (popup tags)
    popupTags.forEach(tag => {
        tag.addEventListener('click', () => {
            handleCategoryClick(tag);
            popup.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            const mainTag = document.querySelector(`.tag[data-category="${tag.dataset.category}"]`);
            if (mainTag) {
                tags.forEach(t => t.classList.remove('active'));
                mainTag.classList.add('active');
            }
        });
    });

    function handleCategoryClick(tag) {
        tags.forEach(t => t.classList.remove('active'));
        popupTags.forEach(t => t.classList.remove('active'));
        
        const mainTag = document.querySelector(`.tag[data-category="${tag.dataset.category}"]`);
        const popupTag = document.querySelector(`.popup-tag[data-category="${tag.dataset.category}"]`);
        
        if (mainTag) mainTag.classList.add('active');
        if (popupTag) popupTag.classList.add('active');

        currentCategory = tag.dataset.category;
        filterType.textContent = tag.textContent;

        visibleCards = initialCards;
        updateVisibility();
    }

    // Search functionality
    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase().trim();
        filterCards(searchTerm);
    });

    // Load more functionality
    loadMoreBtn.addEventListener('click', () => {
        visibleCards += increment;
        updateVisibility();
    });

    // Show less functionality
    showLessBtn.addEventListener('click', () => {
        visibleCards = Math.max(initialCards, visibleCards - increment);
        updateVisibility();
    });

    function updateVisibility() {
        let visibleCount = 0;
        let totalMatchingCards = 0;

        blogCards.forEach(card => {
            const cardCategory = card.dataset.category.toLowerCase();
            const matchesCategory = currentCategory === 'all' || cardCategory.toLowerCase() === currentCategory.toLowerCase();

            if (matchesCategory) {
                totalMatchingCards++;
                if (visibleCount < visibleCards) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = 'none';
            }
        });

        // Show/hide load more and show less buttons
        loadMoreBtn.style.display = visibleCards >= totalMatchingCards ? 'none' : 'block';
        showLessBtn.style.display = visibleCards > initialCards ? 'block' : 'none';

        // Show no results message if needed
        const noResultsMsg = document.querySelector('.no-results');
        if (totalMatchingCards === 0) {
            if (!noResultsMsg) {
                const msg = document.createElement('div');
                msg.className = 'no-results';
                msg.textContent = 'No posts found in this category.';
                blogGrid.appendChild(msg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    function filterCards(searchTerm) {
        let hasResults = false;
        let visibleCount = 0;

        blogCards.forEach(card => {
            const title = card.querySelector('.blog-title').textContent.toLowerCase();
            const category = card.dataset.categoryName.toLowerCase();
            const tags = card.dataset.tags ? card.dataset.tags.toLowerCase() : '';
            
            const matchesSearch = 
                title.includes(searchTerm) || 
                category.includes(searchTerm) || 
                tags.includes(searchTerm);
            
            const matchesCategory = currentCategory === 'all' || 
                card.dataset.category.toLowerCase() === currentCategory.toLowerCase();

            if (matchesSearch && matchesCategory) {
                if (visibleCount < visibleCards) {
                    card.style.display = 'block';
                    visibleCount++;
                    hasResults = true;
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = 'none';
            }
        });

        // Show/hide buttons
        loadMoreBtn.style.display = searchTerm === '' && visibleCount >= visibleCards ? 'block' : 'none';
        showLessBtn.style.display = visibleCount > initialCards ? 'block' : 'none';

        // Update filter type text
        if (searchTerm) {
            filterType.textContent = `Search: ${searchTerm}`;
        } else {
            const activeTag = document.querySelector('.tag.active, .popup-tag.active');
            filterType.textContent = activeTag ? activeTag.textContent : 'All Posts';
        }

        // Show no results message
        const noResultsMsg = document.querySelector('.no-results');
        if (!hasResults) {
            if (!noResultsMsg) {
                const msg = document.createElement('div');
                msg.className = 'no-results';
                msg.textContent = 'No matching posts found.';
                blogGrid.appendChild(msg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }
});