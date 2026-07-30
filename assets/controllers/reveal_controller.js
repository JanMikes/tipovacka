import { Controller } from '@hotwired/stimulus';

/*
 * Collapses a list to the first `visible` items and exposes a toggle button to
 * reveal the remainder. Re-collapses on a second click.
 *
 * B25: collapsing is the ENHANCED state, never the default. The server renders
 * EVERY item visible and the toggle button `hidden`; this controller hides the
 * overflow and unhides the button on connect. With JavaScript off the button
 * therefore never appears and no content is unreachable — the old contract
 * (template pre-hides the overflow with `hidden`) left the 6th and later rows
 * unreachable by any means: not by scrolling, not by any URL.
 *
 * Usage:
 *   <div data-controller="reveal"
 *        data-reveal-visible-value="5"
 *        data-reveal-more-label-value="Načíst všechny zápasy"
 *        data-reveal-less-label-value="Zobrazit méně">
 *       <ul>
 *           <li data-reveal-target="item">...</li>
 *           ...
 *       </ul>
 *       <button type="button" hidden
 *               data-reveal-target="toggle"
 *               data-action="reveal#toggle">Načíst všechny zápasy</button>
 *   </div>
 *
 * Note the label values belong on the CONTROLLER element — Stimulus reads values
 * from there, not from the button.
 */
export default class extends Controller {
    static targets = ['item', 'toggle'];
    static values = {
        visible: { type: Number, default: 5 },
        moreLabel: { type: String, default: 'Zobrazit další' },
        lessLabel: { type: String, default: 'Zobrazit méně' },
    };

    connect() {
        this.expanded = true;

        if (this.hiddenCount() === 0) {
            // Nothing to collapse ⇒ leave the list alone and keep the button away.
            return;
        }

        this.expanded = false;
        this.applyState();

        if (this.hasToggleTarget) {
            this.toggleTarget.hidden = false;
        }
    }

    disconnect() {
        // Give the page back its server-rendered shape, so a re-connect (or a
        // Live Component re-render above us) can never leave rows stuck hidden.
        this.expanded = true;
        this.applyState();

        if (this.hasToggleTarget) {
            this.toggleTarget.hidden = true;
        }
    }

    toggle(event) {
        event.preventDefault();
        this.expanded = !this.expanded;
        this.applyState();
    }

    applyState() {
        this.itemTargets.forEach((item, index) => {
            if (this.expanded || index < this.visibleValue) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        if (this.hasToggleTarget) {
            this.toggleTarget.textContent = this.expanded
                ? this.lessLabelValue
                : `${this.moreLabelValue} (${this.hiddenCount()})`;
        }
    }

    hiddenCount() {
        return Math.max(0, this.itemTargets.length - this.visibleValue);
    }
}
