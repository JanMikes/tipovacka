import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/*
 * Organizer score-entry form („Zapsat výsledek").
 *
 * - State toggle Probíhá / Ukončený: reveals the „poslední zápas" checkbox for
 *   finished matches; overtime inputs appear only when the entered regular
 *   score is a draw AND the state is finished.
 * - Score steppers (+/−) around the two score inputs.
 * - Dynamic event rows (goals / cards) cloned from the Symfony CollectionType
 *   prototype; player-name inputs get a tom-select autocomplete backed by the
 *   source roster endpoint (?tym=<team>), with free-create for new names.
 * - Non-blocking warning when goal-row counts don't match the entered score.
 */
export default class extends Controller {
    static targets = ['homeScore', 'awayScore', 'overtime', 'overtimeHome', 'overtimeAway', 'finishOnly', 'eventsList', 'eventRow', 'warning'];
    static values = {
        playersUrl: String,
        homeTeam: String,
        awayTeam: String,
        prototype: String,
    };

    connect() {
        this.nextIndex = this.eventRowTargets.length;
        this.eventRowTargets.forEach((row) => this.initAutocomplete(row));
        this.refresh();
    }

    disconnect() {
        this.eventRowTargets.forEach((row) => {
            const input = row.querySelector('[data-role="player"]');
            if (input && input.tomselect) {
                input.tomselect.destroy();
            }
        });
    }

    // ── UI state ─────────────────────────────────────────────────────────

    stateChanged() {
        this.refresh();
    }

    scoreChanged() {
        this.refresh();
    }

    rowChanged() {
        this.updateWarning();
    }

    refresh() {
        const finishing = this.isFinishing();
        const home = this.intValue(this.homeScoreTarget);
        const away = this.intValue(this.awayScoreTarget);
        const isDraw = home !== null && away !== null && home === away;

        if (this.hasOvertimeTarget) {
            const showOvertime = finishing && isDraw;
            this.overtimeTarget.classList.toggle('hidden', !showOvertime);

            // Disable (not clear) hidden overtime inputs: disabled inputs are not
            // submitted, and a transient non-draw while correcting a score no
            // longer wipes the stored overtime values once the draw is restored.
            if (this.hasOvertimeHomeTarget) this.overtimeHomeTarget.disabled = !showOvertime;
            if (this.hasOvertimeAwayTarget) this.overtimeAwayTarget.disabled = !showOvertime;
        }

        if (this.hasFinishOnlyTarget) {
            this.finishOnlyTarget.classList.toggle('hidden', !finishing);

            if (!finishing) {
                const checkbox = this.finishOnlyTarget.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            }
        }

        this.updateWarning();
    }

    updateWarning() {
        if (!this.hasWarningTarget) {
            return;
        }

        const home = this.intValue(this.homeScoreTarget);
        const away = this.intValue(this.awayScoreTarget);
        let homeGoals = 0;
        let awayGoals = 0;
        let goalRows = 0;

        this.eventRowTargets.forEach((row) => {
            const type = row.querySelector('[data-role="type"]');
            const side = row.querySelector('[data-role="side"]');
            if (!type || !side || type.value !== 'goal') {
                return;
            }
            goalRows += 1;
            if (side.value === 'home') homeGoals += 1;
            if (side.value === 'away') awayGoals += 1;
        });

        const mismatch = goalRows > 0 && home !== null && away !== null && (homeGoals !== home || awayGoals !== away);
        this.warningTarget.classList.toggle('hidden', !mismatch);
    }

    // ── Score steppers ───────────────────────────────────────────────────

    step(event) {
        const input = event.params.side === 'home' ? this.homeScoreTarget : this.awayScoreTarget;
        const current = this.intValue(input) ?? 0;
        input.value = Math.max(0, current + event.params.delta);
        this.refresh();
    }

    // ── Event rows ───────────────────────────────────────────────────────

    addGoal(event) {
        this.addRow({ type: 'goal', side: event.params.side });
    }

    addCard() {
        this.addRow({ type: 'yellow_card', side: 'home' });
    }

    addRow(presets) {
        const html = this.prototypeValue.replace(/__name__/g, String(this.nextIndex));
        this.nextIndex += 1;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        this.eventsListTarget.appendChild(row);

        const type = row.querySelector('[data-role="type"]');
        const side = row.querySelector('[data-role="side"]');
        if (type && presets.type) type.value = presets.type;
        if (side && presets.side) side.value = presets.side;

        this.initAutocomplete(row);
        this.updateWarning();

        const player = row.querySelector('[data-role="player"]');
        if (player && player.tomselect) {
            player.tomselect.focus();
        }
    }

    removeRow(event) {
        const row = event.target.closest('[data-score-entry-target="eventRow"]');
        if (!row) {
            return;
        }

        const input = row.querySelector('[data-role="player"]');
        if (input && input.tomselect) {
            input.tomselect.destroy();
        }

        row.remove();
        this.updateWarning();
    }

    sideChanged(event) {
        const row = event.target.closest('[data-score-entry-target="eventRow"]');
        if (row) {
            this.loadPlayerOptions(row);
        }
        this.updateWarning();
    }

    // ── Player autocomplete ──────────────────────────────────────────────

    initAutocomplete(row) {
        const input = row.querySelector('[data-role="player"]');
        if (!input || input.tomselect) {
            return;
        }

        new TomSelect(input, {
            create: true,
            createOnBlur: true,
            persist: false,
            maxItems: 1,
            maxOptions: 100,
            valueField: 'name',
            labelField: 'name',
            searchField: ['name'],
            placeholder: 'Jméno hráče…',
            render: {
                option_create: (data, escape) => `<div class="create py-1">Přidat hráče <strong>${escape(data.input)}</strong>…</div>`,
                no_results: () => '<div class="no-results">Žádný hráč — napište jméno</div>',
            },
        });

        this.loadPlayerOptions(row);
    }

    loadPlayerOptions(row) {
        const input = row.querySelector('[data-role="player"]');
        const side = row.querySelector('[data-role="side"]');
        if (!input || !input.tomselect || !side) {
            return;
        }

        const team = side.value === 'away' ? this.awayTeamValue : this.homeTeamValue;
        const selected = input.tomselect.getValue();

        fetch(`${this.playersUrlValue}?tym=${encodeURIComponent(team)}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : []))
            .then((players) => {
                const ts = input.tomselect;
                if (!ts) {
                    return;
                }
                ts.clearOptions();
                players.forEach((player) => ts.addOption(player));
                if (selected) {
                    ts.addOption({ name: selected });
                    ts.setValue(selected, true);
                }
            })
            .catch(() => {
                /* autocomplete is best-effort; free typing always works */
            });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    isFinishing() {
        const radio = this.element.querySelector('input[type="radio"][value="finished"]');

        // No state toggle rendered (correcting a finished match) — the state is
        // implicitly „Ukončený".
        if (!radio) {
            return true;
        }

        return radio.checked;
    }

    intValue(input) {
        if (!input || input.value === '') {
            return null;
        }
        const value = Number.parseInt(input.value, 10);
        return Number.isNaN(value) ? null : value;
    }
}
