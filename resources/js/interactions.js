import { animate, stagger } from 'motion';

export function initInteractions() {
    const menu = document.querySelector('.mobile-menu');

    if (menu) {
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

    const opening = document.querySelector('.home-opening');

    if (!opening) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const startedAt = performance.now();
    const animations = new Set();
    let dismissed = false;

    function finishOpening() {
        dismissed = true;
        for (const animation of animations) animation.complete();
        animations.clear();
    }

    function play(elements, keyframes, options) {
        const animation = animate(elements, keyframes, options);
        animations.add(animation);
        animation.then(() => animations.delete(animation));
    }

    document.fonts.ready.then(() => {
        if (dismissed || reducedMotion.matches || document.hidden || window.scrollY > 0 || performance.now() - startedAt > 1200) return;

        play(opening.querySelectorAll('h1 span'), {
            transform: ['translateY(12px)', 'translateY(0)'],
            opacity: [0.85, 1],
        }, { duration: 0.62, delay: stagger(0.07), ease: [0.16, 1, 0.3, 1] });

        play(opening.querySelector('.future-horizon'), {
            clipPath: ['inset(0 48% 0 48%)', 'inset(0 0% 0 0%)'],
            opacity: [0.4, 1],
        }, { duration: 0.8, ease: [0.16, 1, 0.3, 1] });
    });

    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) finishOpening();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) finishOpening();
    });
    window.addEventListener('pagehide', finishOpening, { once: true });
}
