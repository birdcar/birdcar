/**
 * The Craft & Communicate story on /work: a dotted route joins the rooms and a
 * packet travels it with scroll, level with the reader's eye. When the page
 * stacks into one column the route would cross the story text, so it breaks
 * into one segment inside each room and the packet stays hidden. The rooms,
 * tags, and story carry the meaning; without JavaScript the route is simply
 * absent, and under reduced motion the packet rests at the route's midpoint.
 */
export function initJourney() {
    const journey = document.querySelector('[data-journey]');

    if (!journey) return;

    const route = journey.querySelector('[data-journey-route]');
    const path = route.querySelector('path');
    const packet = route.querySelector('[data-journey-packet]');
    const rooms = [...journey.querySelectorAll('[data-route-room]')];
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    const stacked = window.matchMedia('(max-width: 1099px)');
    let length = 0;
    let queued = false;

    function layout() {
        const box = journey.getBoundingClientRect();
        const groups = rooms.map((room) => [...room.querySelectorAll('[data-route-anchor]')].map((anchor) => {
            const rect = anchor.getBoundingClientRect();

            return { x: rect.left - box.left + rect.width / 2, y: rect.top - box.top + rect.height / 2 };
        }));

        route.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
        path.setAttribute('d', stacked.matches ? groups.map(routePath).join(' ') : routePath(groups.flat()));
        length = path.getTotalLength();
        journey.dataset.routeReady = '';
        place();
    }

    function place() {
        queued = false;

        if (stacked.matches) return;

        const box = journey.getBoundingClientRect();
        const distance = still.matches ? length / 2 : lengthAtHeight(path, length, window.innerHeight * 0.42 - box.top);
        const point = path.getPointAtLength(distance);

        packet.setAttribute('transform', `translate(${point.x} ${point.y})`);
    }

    new ResizeObserver(layout).observe(journey);
    window.addEventListener('scroll', () => {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(place);
    }, { passive: true });
    still.addEventListener('change', place);
    stacked.addEventListener('change', layout);
    layout();
}

/**
 * A smooth route through the room anchors. Each anchor gets one Catmull-Rom
 * tangent shared by the legs on either side, so the line never kinks at a
 * room boundary; tangents are shortened where needed so every leg keeps
 * travelling downward, which the packet's eye-level search relies on.
 */
export function routePath(points) {
    if (!points.length) return '';

    const tangents = points.map((point, index) => {
        const before = points[index - 1] ?? point;
        const after = points[index + 1] ?? point;
        const tangent = { x: (after.x - before.x) / 2, y: (after.y - before.y) / 2 };
        const room = Math.min(...[point.y - before.y, after.y - point.y].filter((gap) => gap > 0)) * 3;
        const scale = tangent.y > room ? room / tangent.y : 1;

        return { x: tangent.x * scale, y: tangent.y * scale };
    });
    const round = (value) => Math.round(value * 10) / 10;

    return points.reduce((d, point, index) => {
        if (index === 0) return `M${round(point.x)} ${round(point.y)}`;

        const previous = points[index - 1];
        const leave = tangents[index - 1];
        const arrive = tangents[index];

        return `${d} C${round(previous.x + leave.x / 3)} ${round(previous.y + leave.y / 3)} ${round(point.x - arrive.x / 3)} ${round(point.y - arrive.y / 3)} ${round(point.x)} ${round(point.y)}`;
    }, '');
}

/**
 * The distance along the route whose point sits at `height`, so the packet
 * keeps pace with the reader's eye rather than with the route's sideways
 * bends. The route only travels downward, which keeps the search monotonic.
 */
export function lengthAtHeight(path, length, height) {
    let low = 0;
    let high = length;

    for (let step = 0; step < 24; step++) {
        const middle = (low + high) / 2;

        if (path.getPointAtLength(middle).y < height) low = middle;
        else high = middle;
    }

    return (low + high) / 2;
}
