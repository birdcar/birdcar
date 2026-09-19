import { animate } from 'motion';

const initialized = new WeakSet();

export function initDiagrams() {
    const figures = [...document.querySelectorAll('[data-walkthrough-diagram]')]
        .filter((figure) => !initialized.has(figure));

    if (!figures.length || !window.IntersectionObserver) return;
    for (const figure of figures) initialized.add(figure);

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const print = window.matchMedia('print');
    const startedAt = performance.now();
    const animations = new Map();
    const pending = new Set(figures);
    let dismissed = reducedMotion.matches || document.hidden || print.matches;

    function reset(element) {
        element.style.removeProperty('transform');
        element.style.removeProperty('background-color');
    }

    function restoreStatic() {
        dismissed = true;
        observer.disconnect();
        for (const [animation, element] of animations) {
            animation.cancel();
            reset(element);
        }
        animations.clear();
        pending.clear();
    }

    function play(element, keyframes, options) {
        const animation = animate(element, keyframes, options);
        animations.set(animation, element);
        animation.then(() => {
            animations.delete(animation);
            reset(element);
        });
    }

    const observer = new window.IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting || entry.intersectionRatio < 0.15 || !pending.delete(entry.target)) continue;
            observer.unobserve(entry.target);
            if (dismissed || reducedMotion.matches || document.hidden || print.matches || !entry.target.isConnected) continue;

            const stages = entry.target.querySelectorAll('[data-diagram-emphasis]');
            const colors = window.getComputedStyle(document.body);
            for (const [index, stage] of [...stages].entries()) {
                play(stage, {
                    transform: ['scale(1)', 'scale(1.12)', 'scale(1)'],
                    backgroundColor: [colors.getPropertyValue('--color-cyan').trim(), colors.getPropertyValue('--color-emphasis').trim(), colors.getPropertyValue('--color-cyan').trim()],
                }, { duration: 0.55, delay: index * 0.32, ease: [0.16, 1, 0.3, 1] });
            }

            const recommendation = entry.target.querySelector('[data-diagram-recommendation]');
            if (recommendation) {
                play(recommendation, { transform: ['translateY(0)', 'translateY(-3px)', 'translateY(0)'] }, {
                    duration: 0.65, delay: stages.length * 0.32, ease: [0.16, 1, 0.3, 1],
                });
            }
        }
    }, { threshold: 0.15 });

    document.fonts.ready.then(() => {
        if (dismissed || reducedMotion.matches || document.hidden || print.matches || performance.now() - startedAt > 1200) return;
        for (const figure of figures) observer.observe(figure);
    });

    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) restoreStatic();
    });
    print.addEventListener('change', () => {
        if (print.matches) restoreStatic();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) restoreStatic();
    });
    window.addEventListener('beforeprint', restoreStatic);
    window.addEventListener('pagehide', restoreStatic, { once: true });
}
