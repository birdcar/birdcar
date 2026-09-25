/**
 * The closing self-check on /tools/where-work-gets-stuck: ticking the signs a
 * visitor recognizes fills one dot per sign beside its pattern and moves the
 * most familiar patterns to the top. Dots count signs, never a score. Without
 * JavaScript the chips are a plain checklist beside the patterns in order.
 */
export function initSelfCheck() {
    const form = document.querySelector('[data-self-check]');

    if (!form) return;

    const list = form.querySelector('[data-self-check-results]');
    const summary = form.querySelector('[data-self-check-summary]');
    const rows = new Map([...list.querySelectorAll('[data-pattern]')].map((row) => [row.dataset.pattern, row]));
    const names = Object.fromEntries([...rows].map(([pattern, row]) => [pattern, row.dataset.name]));

    form.dataset.enhanced = '';
    form.addEventListener('change', () => {
        const counts = tally([...form.querySelectorAll('input[type="checkbox"]')]);
        const order = rankPatterns([...rows.keys()], counts);

        for (const [pattern, row] of rows) {
            const count = counts[pattern] ?? 0;

            row.querySelectorAll('[data-dot]').forEach((dot, index) => dot.toggleAttribute('data-lit', index < count));
        }

        const reorder = () => {
            for (const pattern of order) list.append(rows.get(pattern));
        };

        if (document.startViewTransition) document.startViewTransition(reorder);
        else reorder();

        summary.textContent = summarize(order, counts, names);
    });
}

export function tally(inputs) {
    const counts = {};

    for (const input of inputs) {
        if (input.checked) counts[input.dataset.pattern] = (counts[input.dataset.pattern] ?? 0) + 1;
    }

    return counts;
}

/** Most recognized first; ties keep the field guide's own order. */
export function rankPatterns(patterns, counts) {
    return patterns
        .map((pattern, index) => ({ pattern, index, count: counts[pattern] ?? 0 }))
        .sort((a, b) => b.count - a.count || a.index - b.index)
        .map(({ pattern }) => pattern);
}

export function summarize(order, counts, names) {
    const top = order.filter((pattern) => (counts[pattern] ?? 0) > 0).slice(0, 2).map((pattern) => names[pattern]);

    if (!top.length) return '';

    return `Sounds most like: ${top.join(' and ')}.`;
}
