document.addEventListener('DOMContentLoaded', function() {
    const sliderContainer = document.querySelector('.section-1 .slider-container');
    const slides = document.querySelectorAll('.section-1 .slide');
    const dots = document.querySelectorAll('.section-1 .dot');
    const prevBtn = document.querySelector('.section-1 .prev');
    const nextBtn = document.querySelector('.section-1 .next');

    let currentSlide = 0;
    let autoSlideInterval;
    const autoSlideDelay = 5000; // 5 seconds

    // Function to update slider position
    function updateSlider() {
        sliderContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        // Update active dot
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });
    }

    // Function to go to next slide
    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        updateSlider();
    }

    // Function to go to previous slide
    function prevSlide() {
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
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
            stopAutoSlide();
            currentSlide = index;
            updateSlider();
            startAutoSlide();
        });
    });

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