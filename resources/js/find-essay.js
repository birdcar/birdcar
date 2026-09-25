/**
 * The Writing index's find field: filters essay rows by title and
 * description as the reader types, hides years with no matches, and
 * announces the count. It only appears once scripts run; without them the
 * whole archive stands as a plain list.
 */
export function initFindEssay() {
    const finder = document.querySelector('[data-find-essay]');

    if (!finder) return;

    const input = finder.querySelector('input');
    const status = document.querySelector('[data-find-status]');
    const empty = document.querySelector('[data-find-empty]');
    const years = [...document.querySelectorAll('[data-year]')];

    finder.hidden = false;

    input.addEventListener('input', () => {
        let shown = 0;

        for (const year of years) {
            let visible = 0;

            for (const essay of year.querySelectorAll('[data-essay]')) {
                const match = matchesQuery(essay.dataset.search, input.value);

                essay.hidden = !match;
                if (match) visible++;
            }

            year.hidden = visible === 0;
            shown += visible;
        }

        empty.hidden = shown > 0;
        status.textContent = describeResults(shown, input.value);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !input.value) return;
        input.value = '';
        input.dispatchEvent(new Event('input'));
    });
}

/** Every word of the query must appear somewhere in the essay's text. */
export function matchesQuery(text, query) {
    const haystack = text.toLowerCase();

    return query.toLowerCase().split(/\s+/).filter(Boolean).every((word) => haystack.includes(word));
}

export function describeResults(count, query) {
    if (!query.trim()) return '';

    return count === 1 ? '1 essay matches.' : `${count} essays match.`;
}
