import { Controller } from '@hotwired/stimulus';

/*
 * Create-competition (and match-selection management) helper:
 *
 *  - `modeChanged` — toggles the subset checkbox area vs. the „zahrnout
 *    playoff" checkbox based on the selected selection-mode radio
 *    (values: 'all' shows playoffSection, 'subset' shows subsetSection).
 *  - `sourceChanged` — reloads the page with ?zdroj=<id> so the match list
 *    matches the chosen source (server-rendered, interim until the S08 wizard).
 *    The user's current inputs are carried over as query params so the reload
 *    does not wipe them (CreateCompetitionController prefills from them).
 *  - `toggleGroup` — check-all/uncheck-all for one round group; the button
 *    lives inside a wrapper carrying `data-group-block`.
 *
 * Targets:
 *   subsetSection  — wrapper of the grouped match checkboxes (Subset mode)
 *   playoffSection — wrapper of the includePlayoff checkbox (All mode)
 *   modeRadio      — every selection-mode radio input
 */
export default class extends Controller {
    static targets = ['subsetSection', 'playoffSection', 'modeRadio'];

    connect() {
        this.syncVisibility();
    }

    modeChanged() {
        this.syncVisibility();
    }

    sourceChanged(event) {
        const url = new URL(window.location.href);

        if (event.target.value) {
            url.searchParams.set('zdroj', event.target.value);
        } else {
            url.searchParams.delete('zdroj');
        }

        const form = event.target.closest('form');

        if (form) {
            const prefix = 'competition_form';

            const textFields = {
                name: form.elements[`${prefix}[name]`],
                description: form.elements[`${prefix}[description]`],
                tipsDeadline: form.elements[`${prefix}[tipsDeadline]`],
            };

            Object.entries(textFields).forEach(([param, field]) => {
                const value = field?.value?.trim();

                if (value) {
                    url.searchParams.set(param, value);
                } else {
                    url.searchParams.delete(param);
                }
            });

            const checkedMode = form.querySelector(`input[name="${prefix}[selectionMode]"]:checked`);

            if (checkedMode) {
                url.searchParams.set('selectionMode', checkedMode.value);
            }

            const checkboxFields = {
                includePlayoff: form.elements[`${prefix}[includePlayoff]`],
                hideOthersTipsBeforeDeadline: form.elements[`${prefix}[hideOthersTipsBeforeDeadline]`],
                withPin: form.elements[`${prefix}[withPin]`],
            };

            Object.entries(checkboxFields).forEach(([param, field]) => {
                if (field) {
                    url.searchParams.set(param, field.checked ? '1' : '0');
                }
            });
        }

        window.location.assign(url.toString());
    }

    toggleGroup(event) {
        event.preventDefault();

        const block = event.target.closest('[data-group-block]');

        if (!block) {
            return;
        }

        const checkboxes = [...block.querySelectorAll('input[type="checkbox"]')];
        const allChecked = checkboxes.every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });
    }

    syncVisibility() {
        const mode = this.modeRadioTargets.find((radio) => radio.checked)?.value ?? 'all';
        const subset = mode === 'subset';

        if (this.hasSubsetSectionTarget) {
            this.subsetSectionTarget.classList.toggle('hidden', !subset);
        }

        if (this.hasPlayoffSectionTarget) {
            this.playoffSectionTarget.classList.toggle('hidden', subset);
        }
    }
}
