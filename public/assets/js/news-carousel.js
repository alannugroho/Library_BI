(() => {
    const initializeCarousel = () => {
        const carousel = document.querySelector('.news-carousel');
        const track = carousel?.querySelector('.news-carousel-track');

        if (!carousel) {
            return;
        }

        const slides = Array.from(carousel.querySelectorAll('.news-slide'));
        const dots = Array.from(carousel.querySelectorAll('.news-carousel-dot'));
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let current = slides.findIndex((slide) => slide.classList.contains('is-active'));
        let timer = null;
        let paused = false;

        if (!track || slides.length < 2) {
            return;
        }

        current = 0;

        const getVisibleSlides = () => window.matchMedia('(max-width: 760px)').matches ? 1 : 2;
        const getMaxIndex = () => Math.max(0, slides.length - getVisibleSlides());

        const showSlide = (index) => {
            current = Math.max(0, Math.min(index, getMaxIndex()));
            const visibleSlides = getVisibleSlides();

            slides.forEach((slide, slideIndex) => {
                const visible = slideIndex >= current && slideIndex < current + visibleSlides;
                slide.setAttribute('aria-hidden', visible ? 'false' : 'true');
                slide.inert = !visible;
            });

            dots.forEach((dot, dotIndex) => {
                const active = dotIndex === current;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            });

            track.style.transform = `translateX(-${current * (100 / visibleSlides)}%)`;
        };

        const stopAutoPlay = () => {
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
            }
        };

        const restartAutoPlay = () => {
            stopAutoPlay();

            if (reduceMotion.matches || paused) {
                return;
            }

            timer = window.setTimeout(() => {
                showSlide(current + 1);
                restartAutoPlay();
            }, 5000);
        };

        carousel.addEventListener('click', (event) => {
            const control = event.target.closest('[data-carousel-action], .news-carousel-dot');

            if (!control || !carousel.contains(control)) {
                return;
            }

            if (control.matches('.news-carousel-dot')) {
                showSlide(Number(control.dataset.slideTo));
            } else {
                const direction = control.dataset.carouselAction === 'next' ? 1 : -1;
                showSlide(current + direction > getMaxIndex() ? 0 : current + direction < 0 ? getMaxIndex() : current + direction);
            }

            restartAutoPlay();
        });

        const pause = () => {
            paused = true;
            stopAutoPlay();
        };

        const resume = () => {
            paused = false;
            restartAutoPlay();
        };

        carousel.addEventListener('mouseenter', pause);
        carousel.addEventListener('mouseleave', resume);
        carousel.addEventListener('focusin', pause);
        carousel.addEventListener('focusout', () => {
            if (!carousel.contains(document.activeElement)) {
                resume();
            }
        });

        reduceMotion.addEventListener('change', restartAutoPlay);

        showSlide(current);
        restartAutoPlay();
        window.addEventListener('resize', () => showSlide(current));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCarousel, { once: true });
    } else {
        initializeCarousel();
    }
})();
