import { Controller } from '@hotwired/stimulus';

/*
 * Prefills the scoring rule points from a preset and reflects the active preset
 * on the `.variant-card` tiles.
 *
 *   card  target — one per preset tile (Standardní / Vlastní). The inert
 *                   „+střelec" tile is `disabled` and carries no action/target.
 *   field target — one per points <input>, tagged with `data-rule="<identifier>"`.
 *
 * Standardní → sets the four real rules to 1 / 1 / 3 / 5 and marks itself active.
 * Vlastní    → only marks itself active (fields stay as the user left them).
 *
 * Defensive: a missing field is skipped; an `input` event is dispatched after each
 * change so any listeners (e.g. validation) react.
 */
export default class extends Controller {
    static targets = ['card', 'field'];

    static STANDARD = {
        correct_home_goals: 1,
        correct_away_goals: 1,
        correct_outcome: 3,
        exact_score: 5,
    };

    standard(event) {
        Object.entries(this.constructor.STANDARD).forEach(([rule, value]) => {
            this.setField(rule, value);
        });
        this.select(event.currentTarget);
    }

    custom(event) {
        // Leave the fields editable / unchanged — only switch the active tile.
        this.select(event.currentTarget);
    }

    setField(rule, value) {
        if (!this.hasFieldTarget) {
            return;
        }

        const field = this.fieldTargets.find((input) => input.dataset.rule === rule);
        if (!field) {
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    select(card) {
        if (!this.hasCardTarget) {
            return;
        }

        this.cardTargets.forEach((tile) => tile.classList.toggle('selected', tile === card));
    }
}
