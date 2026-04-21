import { Controller } from '@hotwired/stimulus';

/*
 * Hides list items beyond a visible count and exposes a toggle button to reveal
 * the remainder. Re-collapses on a second click.
 *
 * Usage:
 *   <div data-controller="reveal" data-reveal-visible-value="5">
 *       <ul>
 *           <li data-reveal-target="item" class="hidden">...</li>
 *           ...
 *       </ul>
 *       <button type="button"
 *               data-reveal-target="toggle"
 *               data-action="reveal#toggle"
 *               data-reveal-more-label-value="Zobrazit další"
 *               data-reveal-less-label-value="Zobrazit méně">
 *           Zobrazit další (X)
 *       </button>
 *   </div>
 *
 * The template is responsible for pre-hiding items with `hidden` beyond the
 * initial visible count; this controller just flips that state on click.
 */
export default class extends Controller {
    static targets = ['item', 'toggle'];
    static values = {
        visible: { type: Number, default: 5 },
        moreLabel: { type: String, default: 'Zobrazit další' },
        lessLabel: { type: String, default: 'Zobrazit méně' },
    };

    connect() {
        this.expanded = false;
    }

    toggle(event) {
        event.preventDefault();
        this.expanded = !this.expanded;

        this.itemTargets.forEach((item, index) => {
            if (this.expanded || index < this.visibleValue) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        if (this.hasToggleTarget) {
            const hiddenCount = Math.max(0, this.itemTargets.length - this.visibleValue);
            this.toggleTarget.textContent = this.expanded
                ? this.lessLabelValue
                : `${this.moreLabelValue} (${hiddenCount})`;
        }
    }
}
