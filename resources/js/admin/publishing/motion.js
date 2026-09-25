import { animate } from 'motion';

const MODES = ['develop', 'write', 'release'];
const CONTEXTS = ['reviews', 'sources', 'brief'];
const running = new WeakMap();

function reduceMotion() {
    return globalThis.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;
}

function rootFor(component) {
    return component?.$root?.closest?.('[data-publishing-studio]') ?? component?.$root ?? document.querySelector('[data-publishing-studio]');
}

function stop(element) {
    running.get(element)?.stop?.();
    running.get(element)?.cancel?.();
    running.delete(element);
}

function motion(element, keyframes, options) {
    if (!element || reduceMotion()) return null;
    stop(element);
    const controls = animate(element, keyframes, options);
    running.set(element, controls);
    controls.finished?.catch?.(() => {}).finally?.(() => {
        if (running.get(element) === controls) running.delete(element);
    });
    return controls;
}

function animateMode(root, mode) {
    if (!root || reduceMotion()) return;
    const tab = root.querySelector(`[data-session-tab="${mode}"]`);
    if (tab) {
        motion(tab, { transform: ['translateY(2px) scale(.96)', 'translateY(0) scale(1)'] }, {
            type: 'spring', stiffness: 520, damping: 25, mass: 0.7,
        });
    }

    root.querySelectorAll('[data-session-panel]').forEach((panel) => {
        if (panel.dataset.sessionPanel !== mode) return;
        motion(panel, { opacity: [0.72, 1], transform: ['translateY(10px)', 'translateY(0)'] }, {
            type: 'spring', stiffness: 360, damping: 34, mass: 0.8,
        });
    });
}

function animateContext(root, open) {
    const context = root?.querySelector?.('[data-session-context]');
    if (!context || reduceMotion()) return;
    motion(context, {
        opacity: open ? [0, 1] : [1, 0.85],
        transform: open ? ['translateX(18px)', 'translateX(0)'] : ['translateX(0)', 'translateX(10px)'],
    }, { type: 'spring', stiffness: 310, damping: 32, mass: 0.9 });
}

function animateArrivals(root) {
    const arrivals = Array.from(root?.querySelectorAll?.('[data-publishing-enter]') ?? []).slice(0, 10);
    if (!arrivals.length || reduceMotion()) return;
    arrivals.forEach((element) => motion(element, {
        opacity: [0, 1],
        transform: ['translateY(14px)', 'translateY(0)'],
    }, {
        delay: arrivals.indexOf(element) * 0.035,
        type: 'spring', stiffness: 260, damping: 30, mass: 0.9,
    }));
}

function pulseActivity(root) {
    const activity = root?.querySelector?.('[data-publishing-activity]');
    if (!activity || reduceMotion()) return;
    motion(activity, { opacity: [0.65, 1, 0.72], transform: ['scale(.98)', 'scale(1.02)', 'scale(1)'] }, {
        duration: 0.8,
        ease: 'easeInOut',
    });
}

function celebratePublished(root, detail = {}) {
    if (!root || detail.kind !== 'published' || reduceMotion()) return;
    const ribbon = document.createElement('div');
    ribbon.setAttribute('aria-hidden', 'true');
    ribbon.dataset.publishingCelebration = 'published';
    ribbon.textContent = 'Published ✦';
    Object.assign(ribbon.style, {
        position: 'fixed',
        insetInlineStart: '50%',
        top: '1rem',
        zIndex: '9999',
        pointerEvents: 'none',
        transform: 'translateX(-50%)',
    });
    root.appendChild(ribbon);

    const sparks = Array.from({ length: 9 }, (_, index) => {
        const spark = document.createElement('i');
        spark.setAttribute('aria-hidden', 'true');
        spark.dataset.publishingSpark = String(index);
        Object.assign(spark.style, {
            position: 'fixed',
            insetInlineStart: '50%',
            top: '2.15rem',
            width: '0.45rem',
            height: '0.45rem',
            borderRadius: '999px',
            background: 'currentColor',
            pointerEvents: 'none',
            zIndex: '9998',
        });
        root.appendChild(spark);
        return spark;
    });

    motion(ribbon, { opacity: [0, 1, 1, 0], transform: ['translateX(-50%) translateY(-8px) scale(.96)', 'translateX(-50%) translateY(0) scale(1.04)', 'translateX(-50%) translateY(0) scale(1)', 'translateX(-50%) translateY(-10px) scale(.98)'] }, { duration: 1.6, ease: 'easeOut' });
    sparks.forEach((spark, index) => {
        const angle = (Math.PI * 2 * index) / sparks.length;
        motion(spark, {
            opacity: [0, 1, 0],
            transform: [`translate(0, 0) scale(.6)`, `translate(${Math.cos(angle) * 52}px, ${Math.sin(angle) * 34}px) scale(1)`, `translate(${Math.cos(angle) * 76}px, ${Math.sin(angle) * 48}px) scale(.2)`],
        }, { duration: 0.9, ease: 'easeOut' });
    });
    setTimeout(() => { ribbon.remove(); sparks.forEach((spark) => spark.remove()); }, 1800);
}

function markDecision(root, detail) {
    const labels = { angle: 'Angle approved', plan: 'Plan approved', proposal: 'Change applied', release: 'Release approved', scheduled: 'Scheduled' };
    const label = labels[detail.kind];
    if (!root || !label || reduceMotion()) return;
    const mark = document.createElement('span');
    mark.dataset.publishingDecision = '';
    mark.setAttribute('aria-hidden', 'true');
    mark.textContent = label;
    Object.assign(mark.style, { position: 'fixed', bottom: '2rem', insetInlineEnd: '2rem', pointerEvents: 'none', zIndex: '50' });
    root.appendChild(mark);
    motion(mark, { opacity: [0, 1], transform: ['translateY(18px) scale(.9)', 'translateY(0) scale(1)'] }, { type: 'spring', stiffness: 450, damping: 22 });
    setTimeout(() => mark.remove(), 1800);
}

function arrive(root) {
    if (root?.dataset?.publishingArrival !== 'true' || reduceMotion()) return;
    const header = root.querySelector('.publishing-session-header');
    motion(header, { opacity: [0.25, 1], transform: ['translateY(24px) scale(.97)', 'translateY(0) scale(1)'] }, { type: 'spring', stiffness: 180, damping: 19 });
    root.dataset.publishingArrival = 'false';
}

export function createPublishingSession(initialMode = 'write') {
    return {
        mode: MODES.includes(initialMode) ? initialMode : 'write',
        context: 'reviews',
        contextOpen: true,
        editorState: 'saved',
        editorActionMessage: '',
        init() {
            queueMicrotask(() => { animateArrivals(rootFor(this)); arrive(rootFor(this)); });
        },
        selectMode(mode) {
            if (!MODES.includes(mode)) return;
            this.mode = mode;
            queueMicrotask(() => animateMode(rootFor(this), mode));
        },
        moveMode(offset) {
            const index = MODES.indexOf(this.mode);
            this.focusMode(MODES[(index + offset + MODES.length) % MODES.length]);
        },
        focusMode(mode) {
            if (!MODES.includes(mode)) return;
            this.selectMode(mode);
            queueMicrotask(() => rootFor(this)?.querySelector?.(`[data-session-tab="${mode}"]`)?.focus?.());
        },
        selectContext(context) {
            if (CONTEXTS.includes(context)) this.context = context;
        },
        setContextOpen(open) {
            this.contextOpen = Boolean(open);
            queueMicrotask(() => animateContext(rootFor(this), this.contextOpen));
        },
        celebrate(detail) {
            celebratePublished(rootFor(this), detail);
        },
    };
}

export function installPublishingMotion(Alpine = globalThis.Alpine) {
    Alpine?.data?.('publishingSession', createPublishingSession);
    window.addEventListener('publishing-milestone', (event) => {
        const root = document.querySelector(`[data-publishing-studio][data-article-id="${event.detail?.articleId}"]`) ?? document.querySelector('[data-publishing-studio]');
        celebratePublished(root, event.detail ?? {});
        markDecision(root, event.detail ?? {});
        pulseActivity(root);
    });
}
