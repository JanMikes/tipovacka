import { Controller } from '@hotwired/stimulus';

/*
 * Prefills the scoring rule points from a preset and reflects the active preset
 * on the `.variant-card` tiles.
 *
 *   card  target — one per preset tile (Standardní / Vlastní). The inert
 *                   „+střelec" tile is `disabled` and carries no action/target.
 *   field target — one per points <input>, tagged with `data-rule="<identifier>"`.
 *
 *   defaults value — { identifier: points } map rendered from the PHP rules'
 *                    `defaultPoints` (single source of truth; no JS copy).
 *
 * Standardní → sets every rule to its default points and marks itself active.
 * Vlastní    → only marks itself active (fields stay as the user left them).
 *
 * Defensive: a missing field is skipped; an `input` event is dispatched after each
 * change so any listeners (e.g. validation) react.
 */
export default class extends Controller {
    static targets = ['card', 'field'];

    static values = {
        defaults: Object,
    };

    standard(event) {
        Object.entries(this.defaultsValue).forEach(([rule, value]) => {
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
