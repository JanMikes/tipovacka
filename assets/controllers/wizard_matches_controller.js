import { Controller } from '@hotwired/stimulus';

/*
 * Client-side conveniences for the create-wizard subset match checklist. The
 * checklist itself is server-rendered and its selection travels through the
 * `selectedMatchIds[]` LiveProp (each checkbox is `data-model`-bound with the
 * `norender` modifier, so ticking never round-trips) — this controller only
 * adds instant text filtering and per-group „Vybrat vše", neither of which
 * touches the server.
 *
 * Targets:
 *   filter — the text input
 *   group  — one wrapper per round group (also carries `data-group-block`)
 *   row    — one <label> per match, tagged with `data-search="home away"` (lower-cased)
 */
export default class extends Controller {
    static targets = ['filter', 'group', 'row'];

    filter() {
        const query = this.hasFilterTarget ? this.filterTarget.value.trim().toLowerCase() : '';

        this.rowTargets.forEach((row) => {
            const haystack = row.dataset.search ?? '';
            row.classList.toggle('hidden', query !== '' && !haystack.includes(query));
        });

        // Hide a group whose every row is filtered out.
        this.groupTargets.forEach((group) => {
            const rows = [...group.querySelectorAll('[data-wizard-matches-target="row"]')];
            const allHidden = rows.length > 0 && rows.every((row) => row.classList.contains('hidden'));
            group.classList.toggle('hidden', allHidden);
        });
    }

    toggleGroup(event) {
        event.preventDefault();

        const block = event.target.closest('[data-group-block]');

        if (!block) {
            return;
        }

        // Only toggle the currently visible (non-filtered) checkboxes.
        const checkboxes = [...block.querySelectorAll('input[type="checkbox"]')].filter(
            (checkbox) => !checkbox.closest('[data-wizard-matches-target="row"]')?.classList.contains('hidden'),
        );
        const allChecked = checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
            // Dispatch so the LiveComponent `selectedMatchIds` model syncs.
            checkbox.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }
}
