const SearchModule = (() => {
    const init = () => {
        console.log('[Page] Search inicializado');
    };

    return { init };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => SearchModule.init());
} else {
    SearchModule.init();
}
