(function() {
    'use strict';

    const CART_ADD_URL = '/carrito/agregar';
    const CART_COUNT_URL = '/carrito/count';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[App] Initializing global components');

        initTooltips();
        initPopovers();
        initLazyLoading();
        initFormValidation();

        console.log('[App] Global components initialized');
    });

    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function initPopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
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
                            img.classList.add('loaded');
                            observer.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });

            document.querySelectorAll('img.lazy').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    function initFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');

        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }

    function showToast(message, type = 'info', duration = 3000) {
        const toastContainer = getOrCreateToastContainer();

        const toastId = 'toast-' + Date.now();
        const iconMap = {
            success: 'check-circle-fill',
            danger: 'x-circle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };

        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${iconMap[type]} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    function getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');

        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        return container;
    }

    function formatPrice(amount, currency = 'USD') {
        const formatter = new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: currency
        });
        return formatter.format(amount);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            showToast('Copied to clipboard', 'success');
            return true;
        } catch (err) {
            console.error('[Clipboard] Copy failed:', err);
            showToast('Copy failed', 'danger');
            return false;
        }
    }

    window.showToast = showToast;
    window.formatPrice = formatPrice;
    window.copyToClipboard = copyToClipboard;
    window.debounce = debounce;

})();
