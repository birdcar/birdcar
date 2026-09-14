import { animate, stagger } from 'motion';
import { initBooking } from './booking';

initBooking();

const menu = document.querySelector('.mobile-menu');

if (menu) {
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
}

const opening = document.querySelector('.home-opening');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

if (opening) {
    const animations = [];
    document.fonts.ready.then(() => {
        if (reducedMotion.matches) return;

        animations.push(animate(opening.querySelectorAll('h1 span'), {
            transform: ['translateY(16px)', 'translateY(0)'],
            opacity: [0.85, 1],
        }, { duration: 0.85, delay: stagger(0.09), ease: [0.16, 1, 0.3, 1] }));

        animations.push(animate(opening.querySelector('.future-horizon'), {
            clipPath: ['inset(0 48% 0 48%)', 'inset(0 0% 0 0%)'],
            opacity: [0.4, 1],
        }, { duration: 1.2, ease: [0.16, 1, 0.3, 1] }));
    });

    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) animations.forEach(animation => animation.complete());
    });
}
