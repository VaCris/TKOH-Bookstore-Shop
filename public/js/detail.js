(function() {
    'use strict';

    const MAX_QUANTITY = 10;
    const MIN_QUANTITY = 1;

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Detail] Initializing page components');

        initQuantitySelector();
        initShareButtons();
        initAddToCart();
        initImageZoom();
        initCurrencySwitcher();

        console.log('[Detail] Page initialized');
    });

    function initQuantitySelector() {
        const quantityInput = document.getElementById('quantity');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');

        if (!quantityInput || !decreaseBtn || !increaseBtn) return;

        decreaseBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value > MIN_QUANTITY) {
                quantityInput.value = value - 1;
            }
        });

        increaseBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value < MAX_QUANTITY) {
                quantityInput.value = value + 1;
            }
        });

        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < MIN_QUANTITY) {
                this.value = MIN_QUANTITY;
            } else if (value > MAX_QUANTITY) {
                this.value = MAX_QUANTITY;
            }
        });
    }

    function initShareButtons() {
        const shareButtons = document.querySelectorAll('[data-share]');

        shareButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const platform = this.dataset.share;
                const url = encodeURIComponent(window.location.href);
                const title = encodeURIComponent(document.title);

                let shareUrl = '';

                switch(platform) {
                    case 'facebook':
                        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                        break;
                    case 'twitter':
                        shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                        break;
                    case 'whatsapp':
                        shareUrl = `https://wa.me/?text=${title}%20${url}`;
                        break;
                    case 'email':
                        shareUrl = `mailto:?subject=${title}&body=${url}`;
                        break;
                }

                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                }
            });
        });

        const nativeShareBtn = document.getElementById('native-share');
        if (nativeShareBtn && navigator.share) {
            nativeShareBtn.style.display = 'inline-block';
            nativeShareBtn.addEventListener('click', async function() {
                try {
                    await navigator.share({
                        title: document.title,
                        url: window.location.href
                    });
                } catch(err) {
                    console.log('[Share] Native share cancelled or failed');
                }
            });
        }
    }

    function initAddToCart() {
        const addToCartBtn = document.getElementById('add-to-cart-btn');

        if (!addToCartBtn) return;

        addToCartBtn.addEventListener('click', function() {
            const isbn = this.dataset.isbn;
            const quantity = parseInt(document.getElementById('quantity').value);

            addToCartWithQuantity(isbn, quantity);
        });
    }

    function addToCartWithQuantity(isbn, quantity) {
        const cartAddUrl = getCartUrl('add');

        if (!cartAddUrl) {
            console.error('[Cart] Add URL not found');
            return;
        }

        const button = document.getElementById('add-to-cart-btn');
        const originalContent = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

        fetch(cartAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ isbn: isbn, quantity: quantity })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartBadge();

                if (typeof showToast !== 'undefined') {
                    showToast(`${quantity} book(s) added to cart`, 'success');
                }

                document.getElementById('quantity').value = MIN_QUANTITY;

                button.classList.add('btn-success');
                button.innerHTML = '<i class="bi bi-check-lg"></i> Added';

                setTimeout(() => {
                    button.classList.remove('btn-success');
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }, 2000);
            } else {
                if (typeof showToast !== 'undefined') {
                    showToast(data.message || 'Failed to add to cart', 'danger');
                }
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('[Cart] Add failed:', error);
            if (typeof showToast !== 'undefined') {
                showToast('Connection error', 'danger');
            }
            button.innerHTML = originalContent;
            button.disabled = false;
        });
    }

    function updateCartBadge() {
        const cartCountUrl = getCartUrl('count');

        if (!cartCountUrl) return;

        fetch(cartCountUrl)
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = data.count > 0 ? 'inline' : 'none';
                    badge.classList.add('pulse');
                    setTimeout(() => badge.classList.remove('pulse'), 500);
                }
            })
            .catch(error => console.error('[Cart] Badge update failed:', error));
    }

    function getCartUrl(action) {
        const container = document.querySelector('[data-cart-urls]');
        if (!container) return null;

        const urls = JSON.parse(container.dataset.cartUrls || '{}');
        return urls[action] || null;
    }

    function initImageZoom() {
        const image = document.querySelector('.book-main-image');
        if (!image) return;

        image.addEventListener('click', function() {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white" data-bs-dismiss="modal"></button>
                            <img src="${this.src}" class="img-fluid" alt="${this.alt}">
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
        });
    }

    function initCurrencySwitcher() {
        const currencyRadios = document.querySelectorAll('input[name="currency-detail"]');

        if (currencyRadios.length === 0) return;

        currencyRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                toggleCurrency(this.id.includes('usd'));
            });
        });
    }

    function toggleCurrency(showUsd) {
        const priceUsd = document.querySelectorAll('.price-usd');
        const pricePen = document.querySelectorAll('.price-pen');
        const conversionUsd = document.querySelectorAll('.conversion-usd');
        const conversionPen = document.querySelectorAll('.conversion-pen');

        if (showUsd) {
            priceUsd.forEach(el => el.style.display = 'inline');
            pricePen.forEach(el => el.style.display = 'none');
            conversionUsd.forEach(el => el.style.display = 'inline');
            conversionPen.forEach(el => el.style.display = 'none');
        } else {
            priceUsd.forEach(el => el.style.display = 'none');
            pricePen.forEach(el => el.style.display = 'inline');
            conversionUsd.forEach(el => el.style.display = 'none');
            conversionPen.forEach(el => el.style.display = 'inline');
        }

        const priceDisplay = document.getElementById('price-display');
        if (priceDisplay) {
            priceDisplay.classList.add('price-animate');
            setTimeout(() => priceDisplay.classList.remove('price-animate'), 300);
        }
    }

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .pulse {
            animation: pulse 0.5s ease-in-out;
        }

        .book-main-image {
            cursor: zoom-in;
        }

        .price-animate {
            animation: pulse 0.3s ease-in-out;
        }
    `;
    document.head.appendChild(style);

})();
