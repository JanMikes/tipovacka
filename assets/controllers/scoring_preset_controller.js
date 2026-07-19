import { Controller } from '@hotwired/stimulus';

/*
 * Applies a scoring preset and reflects the active preset on the
 * `.variant-card` tiles.
 *
 *   card         target — one per preset tile (Standardní / Standard + střelec / Vlastní).
 *   field        target — one per points <input>, tagged with `data-rule="<identifier>"`.
 *   enabledField target — one per „Aktivní" checkbox, tagged with `data-rule`.
 *
 *   defaults value — { identifier: points } map rendered from the PHP rules'
 *                    `defaultPoints` (single source of truth; no JS copy).
 *   presets  value — { presetName: [identifiers] } — the identifiers a preset
 *                    ENABLES (everything else is disabled). Rendered from the
 *                    PHP rule categories (base rules / base + scorer_hit).
 *
 * Standardní         → enables the base rules at default points, disables the rest.
 * Standard + střelec → base rules + scorer_hit, all at default points.
 * Vlastní            → only marks itself active (fields stay as the user left them).
 *
 * Defensive: a missing field is skipped; `input`/`change` events are dispatched
 * after each change so any listeners (e.g. validation) react.
 */
export default class extends Controller {
    static targets = ['card', 'field', 'enabledField'];

    static values = {
        defaults: Object,
        presets: Object,
    };

    standard(event) {
        this.applyPreset('standard');
        this.select(event.currentTarget);
    }

    scorer(event) {
        this.applyPreset('scorer');
        this.select(event.currentTarget);
    }

    custom(event) {
        // Leave the fields editable / unchanged — only switch the active tile.
        this.select(event.currentTarget);
    }

    applyPreset(name) {
        const enabledRules = this.presetsValue[name] || [];

        Object.entries(this.defaultsValue).forEach(([rule, points]) => {
            this.setField(rule, points);
            this.setEnabled(rule, enabledRules.includes(rule));
        });
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

    setEnabled(rule, enabled) {
        if (!this.hasEnabledFieldTarget) {
            return;
        }

        const checkbox = this.enabledFieldTargets.find((input) => input.dataset.rule === rule);
        if (!checkbox) {
            return;
        }

        checkbox.checked = enabled;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }

    select(card) {
        if (!this.hasCardTarget) {
            return;
        }

        this.cardTargets.forEach((tile) => tile.classList.toggle('selected', tile === card));
    }
}
