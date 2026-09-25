import { expect, test } from 'bun:test';
import { SCENES, floorLane, packetProgress, pointOnLane, pointOnRoute, stepAtLine } from './miniature';

test('fixed-state lanes turn once, along the floor grid, and end at the next desk', () => {
    const from = { x: 17.9, y: 43.3 };
    const to = { x: 45, y: 49.6 };
    const [start, corner, end] = floorLane(from, to);
    const firstLeg = { x: corner.x - start.x, y: corner.y - start.y };
    const secondLeg = { x: end.x - corner.x, y: end.y - corner.y };

    expect(start).toEqual(from);
    expect(end).toEqual(to);
    expect(firstLeg.y / firstLeg.x).toBeCloseTo(0.559 / 0.829, 5);
    expect(secondLeg.y / secondLeg.x).toBeCloseTo(-0.602 / 0.799, 5);
});

test('packets leave their desk and arrive where their route or lane ends', () => {
    const route = SCENES.today.routes[0];
    const lane = SCENES.fixed.lanes[0];

    expect(pointOnRoute(route, 0)).toEqual(route.from);
    expect(pointOnRoute(route, 1).x).toBeCloseTo(route.to.x);
    expect(pointOnRoute(route, 1).y).toBeCloseTo(route.to.y);
    expect(pointOnLane(lane, 0)).toEqual(lane[0]);
    expect(pointOnLane(lane, 1).x).toBeCloseTo(lane.at(-1).x);
    expect(pointOnLane(lane, 1).y).toBeCloseTo(lane.at(-1).y);
});

test('packet progress loops without leaving the route', () => {
    for (const time of [0, 1600, 3199, 3200, 99999]) {
        const progress = packetProgress(time, 3, 1);
        expect(progress).toBeGreaterThanOrEqual(0);
        expect(progress).toBeLessThan(1);
    }
});

test('the story follows the step under the reading line, or the nearest one between steps', () => {
    const step = (name, top, bottom) => ({ name, getBoundingClientRect: () => ({ top, bottom }) });
    const steps = [step('today', 0, 400), step('walkthrough', 500, 900), step('fixed', 900, 1300)];

    expect(stepAtLine(steps, 650).name).toBe('walkthrough');
    expect(stepAtLine(steps, 460).name).toBe('walkthrough');
    expect(stepAtLine(steps, 1500).name).toBe('fixed');
});
