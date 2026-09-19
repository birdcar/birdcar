export function initInteractions() {
    const menu = document.querySelector('.mobile-menu');

    if (!menu) return;

    const mobileViewport = window.matchMedia('(max-width: 760px)');

    menu.addEventListener('click', (event) => {
        if (event.target.closest('nav a')) menu.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
    mobileViewport.addEventListener('change', () => {
        if (!mobileViewport.matches) menu.open = false;
    });
}
