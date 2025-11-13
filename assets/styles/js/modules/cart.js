const CartModule = (() => {
    const config = {
        selectors: {
            cartItem: '[data-isbn]',
            quantityInput: 'input[type="text"]',
            cartCount: '#cart-count'
        }
    };

    const dom = {
        getCartItems: () => {
            return Array.from(document.querySelectorAll(config.selectors.cartItem)).map(el => ({
                isbn: el.dataset.isbn,
                cantidad: parseInt(el.querySelector(config.selectors.quantityInput)?.value) || 1
            }));
        },

        updateCartCount: (count) => {
            const badge = document.querySelector(config.selectors.cartCount);
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            }
        }
    };

    const storage = {
        sync: () => {
            const items = dom.getCartItems();
            StorageModule.set(items);
            dom.updateCartCount(items.length);
        }
    };

    const updateQuantity = (isbn, change) => {
        const cartItem = document.querySelector(`${config.selectors.cartItem}[data-isbn="${isbn}"]`);
        if (!cartItem) return;

        const input = cartItem.querySelector(config.selectors.quantityInput);
        if (!input) return;

        let cantidad = parseInt(input.value) || 1;
        cantidad = Math.max(1, cantidad + change);
        input.value = cantidad;

        storage.sync();
        console.log(`[Cart] ISBN ${isbn} - Cantidad: ${cantidad}`);
    };

    const removeItem = (isbn) => {
        if (!confirm('¿Eliminar este libro?')) return;

        const cartItem = document.querySelector(`${config.selectors.cartItem}[data-isbn="${isbn}"]`);
        if (!cartItem) return;

        cartItem.remove();
        storage.sync();
        UIModule.showToast('Libro eliminado', 'success');
    };

    const init = () => {
        storage.sync();
    };

    return {
        updateQuantity,
        removeItem,
        init,
        getItems: dom.getCartItems,
        sync: storage.sync
    };
})();

window.updateQuantity = (isbn, change) => CartModule.updateQuantity(isbn, change);
window.removeFromCart = (isbn) => CartModule.removeItem(isbn);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CartModule.init());
} else {
    CartModule.init();
}