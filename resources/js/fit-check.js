/**
 * The Walkthrough fit card: three Yes / Not yet checks stand between the
 * visitor and the inline calendar. Only the row marked `data-fit-stops`
 * (talking to the people who do the work) stops them when answered Not yet.
 * CSS shows that stop and each row's note from the checked radios, so the
 * card reads the same without JavaScript; this module adds the gate itself.
 */
export function initFitCheck() {
    const card = document.querySelector('[data-fit-card]');

    if (!card) return;

    const rows = [...card.querySelectorAll('[data-fit-row]')];
    const action = card.querySelector('[data-fit-action]');
    const status = card.querySelector('[data-fit-status]');
    const calendar = card.querySelector('[data-fit-calendar]');

    card.dataset.state = 'check';

    card.addEventListener('change', (event) => {
        event.target.closest('[data-fit-row]')?.removeAttribute('data-missing');
        status.textContent = '';
    });

    action.addEventListener('click', (event) => {
        event.preventDefault();

        const missing = rows.filter((row) => !row.querySelector('input:checked'));

        for (const row of rows) row.toggleAttribute('data-missing', missing.includes(row));

        if (missing.length) {
            status.textContent = missing.length === 1 ? 'Answer the last check to see times.' : 'Answer all three checks to see times.';
            missing[0].querySelector('input').focus();
            return;
        }

        if (rows.some((row) => 'fitStops' in row.dataset && row.querySelector('input:checked').value === 'not-yet')) return;

        show('calendar', () => {
            calendar.querySelector('[data-cal-inline]').dispatchEvent(new Event('booking:open'));
            calendar.focus();
        });
    });

    card.querySelector('[data-fit-change]').addEventListener('click', () => show('check', () => action.focus()));

    /**
     * Swap the card's face, then run `settled` once the new face is in the
     * DOM: a view transition applies the change asynchronously, and focus or
     * Cal's sizing on a still-hidden face would fail.
     */
    function show(state, settled) {
        const swap = () => {
            card.dataset.state = state;
            card.dataset.swapped = '';
        };

        if (!document.startViewTransition) {
            swap();
            settled();
            return;
        }

        document.startViewTransition(swap).updateCallbackDone.then(settled);
    }
}
