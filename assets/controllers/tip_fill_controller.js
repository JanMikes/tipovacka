import { Controller } from '@hotwired/stimulus';

/*
 * Organizer convenience for a batch tip grid (manage member tips / my tips):
 * a live „{filled}/{total} vyplněno" counter plus bulk-fill shortcut buttons.
 *
 *   score   target — every score <input>. Each carries a `data-match="<id>"`
 *                     so the two inputs of one match are grouped into a pair,
 *                     regardless of their DOM order.
 *   counter target — the element whose text shows „{filled}/{total} vyplněno".
 *
 * A match counts as *filled* only when BOTH its home and away inputs are
 * non-empty. The counter reflects filled/total of MATCHES (not inputs) and is
 * recomputed on every `input`/`change` and once on connect.
 *
 * Bulk-fill actions only touch matches that are still empty (both inputs blank)
 * — already-filled rows are never overwritten:
 *   fillHomeWin → 2:1   fillDraw → 1:1   fillAwayWin → 1:2
 * „Smazat vše" (clearAll) blanks every input. After any bulk change an `input`
 * event is dispatched per touched field so the counter and any Live/validation
 * listeners react.
 *
 * Usage:
 *   <div data-controller="tip-fill">
 *       <span data-tip-fill-target="counter"></span>
 *       <button type="button" data-action="tip-fill#fillHomeWin">2:1</button>
 *       ...
 *       <input data-tip-fill-target="score" data-match="ABC" name="...[homeScore]">
 *       <input data-tip-fill-target="score" data-match="ABC" name="...[awayScore]">
 *   </div>
 */
export default class extends Controller {
    static targets = ['score', 'counter'];
    static values = {
        suffix: { type: String, default: 'vyplněno' },
    };

    connect() {
        this.refresh();
    }

    scoreTargetConnected() {
        this.refresh();
    }

    scoreTargetDisconnected() {
        this.refresh();
    }

    // ---- bulk-fill presets (fill only still-empty matches) ----

    fillHomeWin() {
        this.fillEmpty(2, 1);
    }

    fillDraw() {
        this.fillEmpty(1, 1);
    }

    fillAwayWin() {
        this.fillEmpty(1, 2);
    }

    clearAll() {
        if (!this.hasScoreTarget) {
            return;
        }

        this.scoreTargets.forEach((input) => this.setValue(input, ''));
        this.refresh();
    }

    // ---- internals ----

    fillEmpty(home, away) {
        this.matches().forEach(({ home: homeInput, away: awayInput }) => {
            const homeEmpty = !homeInput || homeInput.value === '';
            const awayEmpty = !awayInput || awayInput.value === '';
            // Only fill rows that are completely empty; never overwrite.
            if (!homeEmpty || !awayEmpty) {
                return;
            }

            if (homeInput) {
                this.setValue(homeInput, String(home));
            }
            if (awayInput) {
                this.setValue(awayInput, String(away));
            }
        });

        this.refresh();
    }

    refresh() {
        if (!this.hasCounterTarget) {
            return;
        }

        const matches = this.matches();
        const total = matches.length;
        const filled = matches.filter(
            ({ home, away }) => home && away && home.value !== '' && away.value !== '',
        ).length;

        const text = `${filled}/${total} ${this.suffixValue}`;
        this.counterTargets.forEach((el) => { el.textContent = text; });
    }

    /**
     * Groups the score inputs into match pairs keyed by their `data-match`
     * attribute. Falls back to grouping by the closest row element when the
     * attribute is absent, so the controller degrades gracefully.
     *
     * @returns {{ home: HTMLInputElement|null, away: HTMLInputElement|null }[]}
     */
    matches() {
        if (!this.hasScoreTarget) {
            return [];
        }

        const groups = new Map();
        let fallbackKey = 0;

        this.scoreTargets.forEach((input) => {
            const key = input.dataset.match
                ?? input.closest('li, tr, [data-match]')
                ?? `__${fallbackKey++}`;

            if (!groups.has(key)) {
                groups.set(key, { home: null, away: null });
            }

            const pair = groups.get(key);
            // First seen input is treated as home, second as away.
            if (pair.home === null) {
                pair.home = input;
            } else if (pair.away === null) {
                pair.away = input;
            }
        });

        return [...groups.values()];
    }

    setValue(input, value) {
        if (input.value === value) {
            return;
        }

        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
