document.addEventListener('DOMContentLoaded', function() {
    const sliderContainer = document.querySelector('.section-1 .slider-container');
    const slides = document.querySelectorAll('.section-1 .slide');
    const dots = document.querySelectorAll('.section-1 .dot');
    const prevBtn = document.querySelector('.section-1 .prev');
    const nextBtn = document.querySelector('.section-1 .next');

    if (slides.length < 2) return; // Don't setup slider if there's only one slide

    // Clone first and last slides
    const firstSlideClone = slides[0].cloneNode(true);
    const lastSlideClone = slides[slides.length - 1].cloneNode(true);
    sliderContainer.appendChild(firstSlideClone);
    sliderContainer.insertBefore(lastSlideClone, slides[0]);

    let currentSlide = 1; // Start from first real slide (after clone)
    let isTransitioning = false;
    let autoSlideInterval;
    const autoSlideDelay = 5000; // 5 seconds

    // Initial positioning
    sliderContainer.style.transform = `translateX(-${currentSlide * 100}%)`;

    // Function to update slider position
    function updateSlider(transition = true) {
        isTransitioning = transition;
        sliderContainer.style.transition = transition ? 'transform 0.5s ease-in-out' : 'none';
        sliderContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        // Update active dot (adjust for cloned slides)
        const actualSlide = currentSlide - 1;
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === (actualSlide % slides.length));
        });
    }

    // Function to handle infinite loop transition
    function handleTransitionEnd() {
        if (!isTransitioning) return;
        
        sliderContainer.style.transition = 'none';
        if (currentSlide === 0) {
            currentSlide = slides.length;
        } else if (currentSlide === slides.length + 1) {
            currentSlide = 1;
        }
        sliderContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
        isTransitioning = false;
    }

    // Function to go to next slide
    function nextSlide() {
        if (isTransitioning) return;
        currentSlide++;
        updateSlider();
    }

    // Function to go to previous slide
    function prevSlide() {
        if (isTransitioning) return;
        currentSlide--;
        updateSlider();
    }

    // Start auto sliding
    function startAutoSlide() {
        autoSlideInterval = setInterval(nextSlide, autoSlideDelay);
    }

    // Stop auto sliding
    function stopAutoSlide() {
        clearInterval(autoSlideInterval);
    }

    // Event listeners for manual controls
    nextBtn.addEventListener('click', () => {
        stopAutoSlide();
        nextSlide();
        startAutoSlide();
    });

    prevBtn.addEventListener('click', () => {
        stopAutoSlide();
        prevSlide();
        startAutoSlide();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            if (isTransitioning) return;
            stopAutoSlide();
            currentSlide = index + 1;
            updateSlider();
            startAutoSlide();
        });
    });

    // Add transition end listener
    sliderContainer.addEventListener('transitionend', handleTransitionEnd);

    // Touch events for mobile swipe
    let touchStartX = 0;
    let touchEndX = 0;

    sliderContainer.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
        stopAutoSlide();
    });

    sliderContainer.addEventListener('touchmove', (e) => {
        touchEndX = e.touches[0].clientX;
    });

    sliderContainer.addEventListener('touchend', () => {
        const swipeThreshold = 50;
        const difference = touchStartX - touchEndX;

        if (Math.abs(difference) > swipeThreshold) {
            if (difference > 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
        startAutoSlide();
    });

    // Start the auto slide when the page loads
    startAutoSlide();

    // Pause auto slide when user hovers over the slider
    document.querySelector('.section-1 .hero').addEventListener('mouseenter', stopAutoSlide);
    document.querySelector('.section-1 .hero').addEventListener('mouseleave', startAutoSlide);
});