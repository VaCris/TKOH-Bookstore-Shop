const CheckoutModule = (() => {
    const config = {
        endpoints: {
            createSession: '/api/checkout/create-session'
        },
        selectors: {
            button: '#checkout-button',
            stripeKey: '[data-stripe-key]',
            cartItem: '[data-isbn]'
        }
    };

    const dom = {
        getButton: () => document.querySelector(config.selectors.button),
        getStripeKey: () => document.querySelector(config.selectors.stripeKey)?.dataset.stripeKey,
        getCartItems: () => {
            return Array.from(document.querySelectorAll(config.selectors.cartItem)).map(el => ({
                isbn: el.dataset.isbn
            }));
        }
    };

    const api = {
        createSession: async (items) => {
            return APIModule.post(config.endpoints.createSession, { items });
        }
    };

    const stripe = {
        redirect: (sessionId, stripeKey) => {
            const stripeInstance = Stripe(stripeKey);
            stripeInstance.redirectToCheckout({ sessionId });
        }
    };

    const submit = async () => {
        const button = dom.getButton();
        if (!button) return;

        UIModule.loading(button, true);

        try {
            const items = dom.getCartItems();

            if (items.length === 0) {
                throw new Error('Carrito vacío');
            }

            const data = await api.createSession(items);

            if (data.error) {
                throw new Error(data.error);
            }

            const stripeKey = dom.getStripeKey();
            if (!stripeKey) {
                throw new Error('Clave de Stripe no configurada');
            }

            StorageModule.clear();
            stripe.redirect(data.sessionId, stripeKey);

        } catch (error) {
            console.error('[Checkout Error]', error);
            UIModule.showToast(error.message || 'Error al procesar', 'danger');
            UIModule.loading(button, false);
        }
    };

    return { submit };
})();

window.proceedToCheckout = () => CheckoutModule.submit();