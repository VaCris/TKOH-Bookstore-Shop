(function() {
    'use strict';

    const CART_COUNT_URL = '/carrito/count';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Navbar] Initializing components');

        initNavbar();
        initCartBadge();
        initScrollBehavior();
        initScrollToTop();

        console.log('[Navbar] Components initialized');
    });

    function initNavbar() {
        const navbar = document.querySelector('.navbar-modern');

        if (!navbar) return;

        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                if (!dropdown.parentElement.contains(e.target)) {
                    const toggle = dropdown.previousElementSibling;
                    if (toggle) {
                        bootstrap.Dropdown.getInstance(toggle)?.hide();
                    }
                }
            });
        });
    }

    function initCartBadge() {
        updateCartBadge();
        document.addEventListener('cart:updated', updateCartBadge);
    }

    function updateCartBadge() {
        fetch(CART_COUNT_URL)
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.textContent = data.count || 0;

                    if (data.count > 0) {
                        badge.style.display = 'flex';
                        badge.classList.add('pulse-once');
                        setTimeout(() => badge.classList.remove('pulse-once'), 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('[Cart] Badge update failed:', error));
    }

    function initScrollBehavior() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;

                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    function initScrollToTop() {
        const scrollBtn = document.getElementById('scrollToTop');

        if (!scrollBtn) return;

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        });

        scrollBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    function initLiveSearch() {
        const searchInput = document.querySelector('.search-form input[name="search"]');

        if (!searchInput) return;

        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);

            const query = this.value.trim();

            if (query.length < 3) return;

            searchTimeout = setTimeout(() => {
                console.log('[Search] Query:', query);
            }, 300);
        });
    }

    const style = document.createElement('style');
    style.textContent = `
        .navbar-modern.scrolled {
            box-shadow: var(--shadow-lg);
        }

        @keyframes pulse-once {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.3);
            }
        }

        .pulse-once {
            animation: pulse-once 0.5s ease-in-out;
        }
    `;
    document.head.appendChild(style);

})();
