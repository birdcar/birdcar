import { animate } from 'motion';

const REFERENCE_SIZE = 894;
const CROSSFADE = 650;
const OWNER_DESK = { x: 72, y: 41 };
const WARNING_LIGHT = { x: 72.6, y: 31.7 };
const FLOOR_AXES = [{ x: 0.829, y: 0.559 }, { x: 0.799, y: -0.602 }];

const DESKS = {
    approval: { x: 82.3, y: 32.5 },
    quote: { x: 17.9, y: 43.3 },
    crew: { x: 45, y: 49.6 },
    report: { x: 67.7, y: 60.3 },
    invoice: { x: 42.1, y: 63.8 },
    shop: { x: 39.1, y: 27.9 },
};

const OWNER_ROUTES = ['approval', 'quote', 'crew', 'report', 'invoice'].map((desk) => ({ from: DESKS[desk], to: OWNER_DESK }));

export const SCENES = {
    today: { warning: true, routes: OWNER_ROUTES, flowing: true, packets: true, lanes: [], markers: [] },
    walkthrough: { warning: true, routes: OWNER_ROUTES, flowing: false, packets: false, lanes: [], markers: OWNER_ROUTES.map((route) => route.from) },
    fixed: {
        warning: false,
        routes: [],
        markers: [],
        lanes: [
            ['quote', 'crew'],
            ['crew', 'shop'],
            ['quote', 'invoice'],
            ['invoice', 'report'],
            ['crew', 'report'],
        ].map(([from, to]) => floorLane(DESKS[from], DESKS[to])),
    },
};

export function floorLane(from, to) {
    const [down, up] = FLOOR_AXES;
    const determinant = down.x * up.y - down.y * up.x;
    const deltaX = to.x - from.x;
    const deltaY = to.y - from.y;
    const along = (deltaX * up.y - deltaY * up.x) / determinant;

    return [from, { x: from.x + down.x * along, y: from.y + down.y * along }, to];
}

export function controlPoint(from, to, bow = 0.2) {
    let normalX = -(to.y - from.y);
    let normalY = to.x - from.x;

    if (normalY > 0) {
        normalX = -normalX;
        normalY = -normalY;
    }

    return { x: (from.x + to.x) / 2 + normalX * bow, y: (from.y + to.y) / 2 + normalY * bow };
}

export function pointOnRoute(route, progress) {
    const control = controlPoint(route.from, route.to);
    const inverse = 1 - progress;

    return {
        x: inverse * inverse * route.from.x + 2 * inverse * progress * control.x + progress * progress * route.to.x,
        y: inverse * inverse * route.from.y + 2 * inverse * progress * control.y + progress * progress * route.to.y,
    };
}

export function pointOnLane(lane, progress) {
    const lengths = [];
    let total = 0;

    for (let index = 1; index < lane.length; index++) {
        const length = Math.hypot(lane[index].x - lane[index - 1].x, lane[index].y - lane[index - 1].y);
        lengths.push(length);
        total += length;
    }

    let distance = progress * total;
    for (let index = 0; index < lengths.length; index++) {
        if (distance <= lengths[index] || index === lengths.length - 1) {
            const ratio = lengths[index] ? Math.min(1, distance / lengths[index]) : 0;
            return {
                x: lane[index].x + (lane[index + 1].x - lane[index].x) * ratio,
                y: lane[index].y + (lane[index + 1].y - lane[index].y) * ratio,
            };
        }
        distance -= lengths[index];
    }

    return lane[lane.length - 1];
}

export function packetProgress(time, routeIndex, packetIndex, period = 3200) {
    return (time / period + routeIndex * 0.29 + packetIndex * 0.5) % 1;
}

export function stepAtLine(steps, line) {
    let closest = null;
    let closestDistance = Infinity;

    for (const step of steps) {
        const bounds = step.getBoundingClientRect();
        if (bounds.top <= line && bounds.bottom >= line) return step;
        const distance = Math.min(Math.abs(bounds.top - line), Math.abs(bounds.bottom - line));
        if (distance < closestDistance) {
            closest = step;
            closestDistance = distance;
        }
    }

    return closest;
}

function drawCube(context, x, y, edge, opacity, tint = 'yellow') {
    const run = edge * 0.87;
    const rise = edge * 0.5;
    const colors = tint === 'yellow' ? ['#fff1ad', '#f7c948', '#dca21a'] : ['#d9fbfc', '#6cc9cf', '#3c9aa1'];

    context.save();
    context.globalAlpha *= opacity;
    context.shadowColor = tint === 'yellow' ? 'rgba(255, 205, 60, .75)' : 'rgba(90, 210, 220, .7)';
    context.shadowBlur = edge * 1.6;
    context.fillStyle = colors[0];
    context.beginPath();
    context.moveTo(x, y - edge);
    context.lineTo(x + run, y - rise);
    context.lineTo(x, y);
    context.lineTo(x - run, y - rise);
    context.fill();
    context.fillStyle = colors[1];
    context.beginPath();
    context.moveTo(x - run, y - rise);
    context.lineTo(x, y);
    context.lineTo(x, y + edge);
    context.lineTo(x - run, y + rise);
    context.fill();
    context.fillStyle = colors[2];
    context.beginPath();
    context.moveTo(x, y);
    context.lineTo(x + run, y - rise);
    context.lineTo(x + run, y + rise);
    context.lineTo(x, y + edge);
    context.fill();
    context.restore();
}

export function drawScene(context, size, scene, time, animated, alpha = 1) {
    const unit = size / REFERENCE_SIZE;
    const px = (value) => (value / 100) * size;

    context.save();
    context.globalAlpha = alpha;
    context.lineCap = 'round';
    context.lineJoin = 'round';

    for (const route of scene.routes) {
        const control = controlPoint(route.from, route.to);
        context.save();
        context.globalAlpha *= scene.flowing ? 1 : 0.4;
        context.beginPath();
        context.moveTo(px(route.from.x), px(route.from.y));
        context.quadraticCurveTo(px(control.x), px(control.y), px(route.to.x), px(route.to.y));
        context.setLineDash([0.1 * unit, 6.2 * unit]);
        context.lineDashOffset = animated && scene.flowing ? -time * 0.012 * unit : 0;
        context.lineWidth = 3.4 * unit;
        context.strokeStyle = '#f5c131';
        context.shadowColor = 'rgba(255, 200, 40, .55)';
        context.shadowBlur = 5 * unit;
        context.stroke();
        context.restore();
    }

    for (const lane of scene.lanes) {
        context.save();
        context.beginPath();
        context.moveTo(px(lane[0].x), px(lane[0].y));
        for (let index = 1; index < lane.length; index++) context.lineTo(px(lane[index].x), px(lane[index].y));
        context.lineWidth = 3 * unit;
        context.strokeStyle = 'rgba(95, 200, 208, .9)';
        context.shadowColor = 'rgba(95, 210, 220, .8)';
        context.shadowBlur = 8 * unit;
        context.stroke();
        context.setLineDash([10 * unit, 26 * unit]);
        context.lineDashOffset = animated ? -time * 0.03 * unit : 0;
        context.lineWidth = 1.6 * unit;
        context.strokeStyle = 'rgba(235, 255, 255, .95)';
        context.shadowBlur = 0;
        context.stroke();
        context.restore();
    }

    scene.routes.forEach((route, routeIndex) => {
        drawCube(context, px(route.from.x), px(route.from.y), 9.5 * unit, 1);

        if (!animated || !scene.packets) return;

        for (let packetIndex = 0; packetIndex < 2; packetIndex++) {
            const progress = packetProgress(time, routeIndex, packetIndex);
            const point = pointOnRoute(route, 1 - (1 - progress) ** 2);
            drawCube(context, px(point.x), px(point.y), 5.5 * unit, Math.min(1, progress * 8, (1 - progress) * 6));
        }
    });

    scene.lanes.forEach((lane, laneIndex) => {
        const progress = animated ? packetProgress(time, laneIndex, 0, 2600) : 0.5;
        const point = pointOnLane(lane, progress < 0.5 ? progress * 2 : 2 - progress * 2);
        drawCube(context, px(point.x), px(point.y), 6.5 * unit, 1, 'teal');
    });

    for (const marker of scene.markers) {
        const pulse = animated ? (time / 1600 + marker.x / 100) % 1 : 0.35;
        context.save();
        context.beginPath();
        context.arc(px(marker.x), px(marker.y) - 2 * unit, (10 + pulse * 22) * unit, 0, Math.PI * 2);
        context.lineWidth = 2.4 * unit;
        context.strokeStyle = `rgba(247, 200, 72, ${0.95 * (1 - pulse)})`;
        context.stroke();
        context.restore();
    }

    if (scene.warning) {
        const pulse = animated ? 0.5 + 0.5 * Math.sin(time / 380) : 0.6;
        const radius = (16 + pulse * 10) * unit;
        const glow = context.createRadialGradient(px(WARNING_LIGHT.x), px(WARNING_LIGHT.y), 0, px(WARNING_LIGHT.x), px(WARNING_LIGHT.y), radius);
        glow.addColorStop(0, `rgba(255, 70, 50, ${0.45 + pulse * 0.3})`);
        glow.addColorStop(1, 'rgba(255, 70, 50, 0)');
        context.fillStyle = glow;
        context.beginPath();
        context.arc(px(WARNING_LIGHT.x), px(WARNING_LIGHT.y), radius, 0, Math.PI * 2);
        context.fill();
    }

    context.restore();
}

function initFigure(figure, reducedMotion) {
    const canvas = figure.querySelector('.miniature-flow');
    const context = canvas?.getContext('2d');

    if (!context) return;

    let size = 0;
    let frame = null;
    let visible = false;
    let previous = null;
    let changedAt = 0;

    const scene = () => SCENES[figure.dataset.miniatureState] ?? SCENES.today;
    const animated = () => !reducedMotion.matches && !document.hidden && visible;

    function paint(time) {
        context.clearRect(0, 0, size, size);
        const fade = previous && !reducedMotion.matches ? Math.min(1, (time - changedAt) / CROSSFADE) : 1;
        if (fade < 1) drawScene(context, size, previous, time, animated(), 1 - fade);
        else previous = null;
        drawScene(context, size, scene(), time, animated(), fade);
    }

    function render(time) {
        paint(time);
        frame = animated() || previous ? requestAnimationFrame(render) : null;
    }

    function start() {
        if (frame === null) frame = requestAnimationFrame(render);
    }

    function setState(state) {
        if (!SCENES[state] || state === figure.dataset.miniatureState) return;

        previous = scene();
        changedAt = performance.now();
        figure.dataset.miniatureState = state;
        start();
    }

    function resize() {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        size = canvas.clientWidth;
        canvas.width = Math.round(size * ratio);
        canvas.height = Math.round(size * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        paint(performance.now());
        start();
    }

    figure.addEventListener('change', (event) => {
        if (event.target.matches('input[type="radio"]')) setState(event.target.value);
    });

    new ResizeObserver(resize).observe(canvas);
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) start();
    }).observe(figure);
    reducedMotion.addEventListener('change', start);
    document.addEventListener('visibilitychange', start);

    return setState;
}

function initStory(story, figure, setState) {
    const steps = [...story.querySelectorAll('[data-story-step]')];
    const narrow = window.matchMedia('(max-width: 1099px)');
    let queued = false;

    story.classList.add('is-scrolling-story');

    function update() {
        queued = false;
        const active = stepAtLine(steps, window.innerHeight * (narrow.matches ? 0.78 : 0.5));
        if (!active) return;

        for (const step of steps) step.classList.toggle('is-active', step === active);
        const state = active.dataset.storyStep;
        const radio = figure.querySelector(`input[value="${state}"]`);
        if (radio && !radio.checked) radio.checked = true;
        setState(state);
    }

    function queue() {
        if (queued) return;
        queued = true;
        requestAnimationFrame(update);
    }

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    update();
}

function initTilt(stage, reducedMotion) {
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const zone = stage.closest('section') ?? stage;
    const spring = { type: 'spring', stiffness: 80, damping: 18 };

    zone.addEventListener('pointermove', (event) => {
        if (!finePointer.matches || reducedMotion.matches) return;
        const bounds = zone.getBoundingClientRect();
        const x = (event.clientX - bounds.left) / bounds.width - 0.5;
        const y = (event.clientY - bounds.top) / bounds.height - 0.5;
        animate(stage, { rotateY: x * 5, rotateX: y * -4 }, spring);
    });
    zone.addEventListener('pointerleave', () => animate(stage, { rotateY: 0, rotateX: 0 }, spring));
}

export function initMiniature() {
    const figures = document.querySelectorAll('[data-miniature]');

    if (!figures.length || !window.ResizeObserver || !window.IntersectionObserver) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    for (const figure of figures) {
        const setState = initFigure(figure, reducedMotion);
        const story = figure.closest('.studio-story');
        if (setState && story) initStory(story, figure, setState);
    }

    for (const stage of document.querySelectorAll('[data-miniature-tilt]')) initTilt(stage, reducedMotion);
}
