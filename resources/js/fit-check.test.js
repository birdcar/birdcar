import { afterEach, beforeEach, expect, mock, test } from 'bun:test';
import { initFitCheck } from './fit-check';

const originalDocument = globalThis.document;
let card;
let rows;
let action;
let status;
let calendar;
let inline;
let change;

function element(properties = {}) {
    const attributes = new Set();

    return Object.assign(new EventTarget(), {
        dataset: {},
        focus: mock(),
        toggleAttribute: (name, force) => (force ? attributes.add(name) : attributes.delete(name)),
        removeAttribute: (name) => attributes.delete(name),
        hasAttribute: (name) => attributes.has(name),
        ...properties,
    });
}

function row(stops = false) {
    const inputs = [element({ value: 'yes', checked: false }), element({ value: 'not-yet', checked: false })];
    const fitRow = element({
        inputs,
        querySelector: (selector) => (selector === 'input:checked' ? inputs.find((input) => input.checked) ?? null : inputs[0]),
    });

    if (stops) fitRow.dataset.fitStops = '';
    for (const input of inputs) input.closest = () => fitRow;

    return fitRow;
}

function answer(fitRow, value) {
    for (const input of fitRow.inputs) input.checked = input.value === value;
    card.dispatchEvent(Object.defineProperty(new Event('change'), 'target', { value: fitRow.inputs[0] }));
}

function showTimes() {
    const event = new Event('click', { cancelable: true });
    action.dispatchEvent(event);
    return event;
}

beforeEach(() => {
    rows = [row(), row(), row(true)];
    action = element();
    status = element({ textContent: '' });
    inline = element();
    calendar = element({ querySelector: () => inline });
    change = element();
    const parts = { '[data-fit-action]': action, '[data-fit-status]': status, '[data-fit-calendar]': calendar, '[data-fit-change]': change };
    card = element({ querySelector: (selector) => parts[selector], querySelectorAll: () => rows });
    globalThis.document = { querySelector: (selector) => (selector === '[data-fit-card]' ? card : null) };
});

afterEach(() => {
    globalThis.document = originalDocument;
});

test('pages without the fit card attach nothing', () => {
    globalThis.document = { querySelector: () => null };

    expect(() => initFitCheck()).not.toThrow();
});

test('the gate starts on the checks once scripts run', () => {
    initFitCheck();

    expect(card.dataset.state).toBe('check');
});

test('asking for times with unanswered checks marks them and focuses the first', () => {
    initFitCheck();
    answer(rows[0], 'yes');

    expect(showTimes().defaultPrevented).toBe(true);
    expect(rows.map((fitRow) => fitRow.hasAttribute('data-missing'))).toEqual([false, true, true]);
    expect(status.textContent).toBe('Answer all three checks to see times.');
    expect(rows[1].inputs[0].focus).toHaveBeenCalledTimes(1);
    expect(card.dataset.state).toBe('check');
});

test('answering a marked check clears its mark and the status', () => {
    initFitCheck();
    answer(rows[0], 'yes');
    answer(rows[1], 'yes');
    showTimes();

    expect(status.textContent).toBe('Answer the last check to see times.');

    answer(rows[2], 'yes');

    expect(rows[2].hasAttribute('data-missing')).toBe(false);
    expect(status.textContent).toBe('');
});

test('three yes answers open the calendar in place and load it', () => {
    const opened = mock();
    inline.addEventListener('booking:open', opened);
    initFitCheck();
    for (const fitRow of rows) answer(fitRow, 'yes');

    showTimes();

    expect(card.dataset.state).toBe('calendar');
    expect(opened).toHaveBeenCalledTimes(1);
    expect(calendar.focus).toHaveBeenCalledTimes(1);
});

test('not yet on a softer check still opens the calendar', () => {
    initFitCheck();
    answer(rows[0], 'not-yet');
    answer(rows[1], 'not-yet');
    answer(rows[2], 'yes');

    showTimes();

    expect(card.dataset.state).toBe('calendar');
});

test('not yet on talking to the people who do the work keeps the calendar closed', () => {
    const opened = mock();
    inline.addEventListener('booking:open', opened);
    initFitCheck();
    answer(rows[0], 'yes');
    answer(rows[1], 'yes');
    answer(rows[2], 'not-yet');

    showTimes();

    expect(card.dataset.state).toBe('check');
    expect(opened).not.toHaveBeenCalled();
});

test('changing answers returns to the checks and focuses the action', () => {
    initFitCheck();
    for (const fitRow of rows) answer(fitRow, 'yes');
    showTimes();

    change.dispatchEvent(new Event('click'));

    expect(card.dataset.state).toBe('check');
    expect(action.focus).toHaveBeenCalledTimes(1);
});

test('with view transitions, focus and the calendar load wait until the new face is in place', async () => {
    const opened = mock();
    let finish;
    inline.addEventListener('booking:open', opened);
    globalThis.document.startViewTransition = (update) => ({
        updateCallbackDone: new Promise((resolve) => { finish = () => { update(); resolve(); }; }),
    });
    initFitCheck();
    for (const fitRow of rows) answer(fitRow, 'yes');

    showTimes();

    expect(opened).not.toHaveBeenCalled();
    expect(calendar.focus).not.toHaveBeenCalled();

    finish();
    await Promise.resolve();

    expect(card.dataset.state).toBe('calendar');
    expect(opened).toHaveBeenCalledTimes(1);
    expect(calendar.focus).toHaveBeenCalledTimes(1);
});
