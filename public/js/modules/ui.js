const UIModule = (() => {
    const showToast = (message, type = 'info') => {
        console.log(`[UI] ${type.toUpperCase()}: ${message}`);
        if (typeof showToast === 'function') {
            window.showToast?.(message, type);
        }
    };

    const loading = (element, show = true) => {
        if (!element) return;
        if (show) {
            element.disabled = true;
            element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
        } else {
            element.disabled = false;
        }
    };

    const disable = (element, disabled = true) => {
        if (element) element.disabled = disabled;
    };

    return { showToast, loading, disable };
})();