(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Home] Initializing page components');

        initHeroCarousel();
        initCartFunctions();
        initLazyLoading();
        initBookSectionsScroll();

        console.log('[Home] Page initialized successfully');
    });

    function initHeroCarousel() {
        if (typeof Swiper === 'undefined') {
            console.error('[Swiper] Library not loaded');
            return;
        }

        const heroSwiper = new Swiper('.heroSwiper', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
                dynamicBullets: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            speed: 600,
            slidesPerView: 1,
            spaceBetween: 0,
            on: {
                init: function () {
                    console.log('[Swiper] Hero carousel initialized');
                },
            }
        });
    }

    function initCartFunctions() {
        updateCartBadge();
        document.addEventListener('cart:updated', updateCartBadge);
    }

    window.addToCart = function(isbn) {
        const button = event?.target?.closest('button');
        const originalContent = button ? button.innerHTML : '';

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        fetch('/carrito/agregar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ isbn: isbn, quantity: 1 })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartBadge();
                if (typeof showToast !== 'undefined') {
                    showToast('Book added to cart successfully', 'success');
                }
                document.dispatchEvent(new CustomEvent('cart:updated', { detail: { isbn: isbn } }));
            } else {
                if (typeof showToast !== 'undefined') {
                    showToast(data.message || 'Error adding book to cart', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('[Cart] Error adding item:', error);
            if (typeof showToast !== 'undefined') {
                showToast('Connection error', 'danger');
            }
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalContent;
            }
        });
    }

    function updateCartBadge() {
        fetch('/carrito/count')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.textContent = data.count || 0;
                    badge.style.display = data.count > 0 ? 'flex' : 'none';
                    badge.classList.add('pulse-once');
                    setTimeout(() => badge.classList.remove('pulse-once'), 500);
                }
            })
            .catch(error => console.error('[Cart] Error updating badge:', error));
    }

    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
        }
    }

    function initBookSectionsScroll() {
        const scrollContainers = document.querySelectorAll('.books-scroll-wrapper-pro');

        console.log('[Scroll] Initializing', scrollContainers.length, 'containers');

        scrollContainers.forEach(container => {
            container.addEventListener('scroll', () => {
                updateScrollIndicators(container);
            });

            updateScrollIndicators(container);
        });

        handleScrollButtons();

        document.querySelectorAll('.scroll-dot').forEach((dot, index) => {
            dot.addEventListener('click', function() {
                const indicators = this.parentElement;
                const section = indicators.closest('.category-section-modern');
                const container = section.querySelector('.books-scroll-wrapper-pro');

                if (container) {
                    const scrollWidth = container.scrollWidth - container.clientWidth;
                    const dots = indicators.querySelectorAll('.scroll-dot');
                    const percentage = index / (dots.length - 1);
                    const scrollTo = scrollWidth * percentage;

                    container.scrollTo({
                        left: scrollTo,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    window.scrollBooks = function(button, direction) {
        const container = button.parentElement.querySelector('.books-scroll-wrapper-pro');

        if (!container) {
            console.error('[Scroll] Container not found');
            return;
        }

        const scrollAmount = 300;

        if (direction === 'left') {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }

        setTimeout(() => updateScrollIndicators(container), 350);
    }

    function updateScrollIndicators(container) {
        const section = container.closest('.category-section-modern');
        if (!section) return;

        const indicators = section.querySelector('.scroll-indicators');
        if (!indicators) return;

        const dots = indicators.querySelectorAll('.scroll-dot');
        if (dots.length === 0) return;

        const scrollLeft = container.scrollLeft;
        const scrollWidth = container.scrollWidth - container.clientWidth;
        const percentage = scrollWidth > 0 ? scrollLeft / scrollWidth : 0;
        const activeDot = Math.round(percentage * (dots.length - 1));

        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === activeDot);
        });
    }

    function handleScrollButtons() {
        document.querySelectorAll('.books-scroll-container').forEach(scrollContainer => {
            const wrapper = scrollContainer.querySelector('.books-scroll-wrapper-pro');
            const leftBtn = scrollContainer.querySelector('.scroll-btn-left');
            const rightBtn = scrollContainer.querySelector('.scroll-btn-right');

            if (!wrapper || !leftBtn || !rightBtn) return;

            function updateButtonStates() {
                const scrollLeft = wrapper.scrollLeft;
                const scrollWidth = wrapper.scrollWidth - wrapper.clientWidth;

                if (scrollLeft <= 0) {
                    leftBtn.style.opacity = '0.5';
                    leftBtn.style.cursor = 'not-allowed';
                } else {
                    leftBtn.style.opacity = '1';
                    leftBtn.style.cursor = 'pointer';
                }

                if (scrollLeft >= scrollWidth - 1) {
                    rightBtn.style.opacity = '0.5';
                    rightBtn.style.cursor = 'not-allowed';
                } else {
                    rightBtn.style.opacity = '1';
                    rightBtn.style.cursor = 'pointer';
                }
            }

            wrapper.addEventListener('scroll', updateButtonStates);
            updateButtonStates();
        });
    }

})();
