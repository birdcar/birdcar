import { expect, test } from 'bun:test';
import { lengthAtHeight, routePath } from './journey';

function segments(d) {
    return [...d.matchAll(/C([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+)/g)].map((match) => match.slice(1).map(Number));
}

const anchors = [{ x: 960, y: 290 }, { x: 1115, y: 485 }, { x: 924, y: 830 }, { x: 1125, y: 890 }, { x: 1200, y: 1000 }];

test('the route starts at the first anchor and passes through every other one', () => {
    const d = routePath(anchors);
    const legs = segments(d);

    expect(d.startsWith('M960 290')).toBe(true);
    expect(legs.map((leg) => [leg[4], leg[5]])).toEqual(anchors.slice(1).map((point) => [point.x, point.y]));
});

test('the route bends smoothly through each anchor instead of kinking', () => {
    const legs = segments(routePath(anchors));

    for (let index = 1; index < legs.length; index++) {
        const [arriveX, arriveY] = [legs[index - 1][2], legs[index - 1][3]];
        const [anchorX, anchorY] = [legs[index - 1][4], legs[index - 1][5]];
        const [leaveX, leaveY] = [legs[index][0], legs[index][1]];
        const cross = (anchorX - arriveX) * (leaveY - anchorY) - (anchorY - arriveY) * (leaveX - anchorX);
        const scale = Math.hypot(anchorX - arriveX, anchorY - arriveY) * Math.hypot(leaveX - anchorX, leaveY - anchorY);

        expect(Math.abs(cross) / scale).toBeLessThan(0.01);
    }
});

test('every leg keeps travelling downward so the packet can follow the reading line', () => {
    let previousY = anchors[0].y;

    for (const [firstY, secondY, endY] of segments(routePath(anchors)).map((leg) => [leg[1], leg[3], leg[5]])) {
        expect(firstY).toBeGreaterThanOrEqual(previousY);
        expect(secondY).toBeLessThanOrEqual(endY);
        previousY = endY;
    }
});

test('a single anchor draws a point and no anchors draw nothing', () => {
    expect(routePath([{ x: 10, y: 20 }])).toBe('M10 20');
    expect(routePath([])).toBe('');
});

test('the packet sits at the point on the route level with the reading line', () => {
    const path = { getPointAtLength: (distance) => ({ x: 0, y: distance / 2 }) };

    expect(lengthAtHeight(path, 1000, 250)).toBeCloseTo(500, 1);
});

test('the packet rests at the route ends when the reading line is above or below it', () => {
    const path = { getPointAtLength: (distance) => ({ x: 0, y: 100 + distance }) };

    expect(lengthAtHeight(path, 800, 0)).toBeCloseTo(0, 1);
    expect(lengthAtHeight(path, 800, 5000)).toBeCloseTo(800, 1);
});
