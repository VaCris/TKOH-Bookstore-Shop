const APIModule = (() => {
    const config = {
        baseURL: '',
        timeout: 30000
    };

    const request = async (endpoint, options = {}) => {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        try {
            console.log(`[API] ${finalOptions.method} ${endpoint}`);

            const response = await fetch(`${config.baseURL}${endpoint}`, finalOptions);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();

        } catch (error) {
            console.error(`[API Error] ${endpoint}:`, error);
            throw error;
        }
    };

    const get = (endpoint) => request(endpoint, { method: 'GET' });

    const post = (endpoint, data) => request(endpoint, {
        method: 'POST',
        body: JSON.stringify(data)
    });

    const put = (endpoint, data) => request(endpoint, {
        method: 'PUT',
        body: JSON.stringify(data)
    });

    const del = (endpoint) => request(endpoint, { method: 'DELETE' });

    return { request, get, post, put, del };
})();