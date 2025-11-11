const StorageModule = (() => {
    const config = {
        key: 'tkoh_cart',
        version: '1.0'
    };

    const set = (items) => {
        try {
            localStorage.setItem(config.key, JSON.stringify({
                version: config.version,
                timestamp: new Date().toISOString(),
                items
            }));
            console.log('[Storage] Carrito guardado');
        } catch (error) {
            console.error('[Storage] Error guardando:', error);
        }
    };

    const get = () => {
        try {
            const stored = localStorage.getItem(config.key);
            if (!stored) return [];
            const parsed = JSON.parse(stored);
            return parsed.items || [];
        } catch (error) {
            console.error('[Storage] Error leyendo:', error);
            return [];
        }
    };

    const clear = () => {
        try {
            localStorage.removeItem(config.key);
            console.log('[Storage] Carrito limpiado');
        } catch (error) {
            console.error('[Storage] Error limpiando:', error);
        }
    };

    return { set, get, clear };
})();