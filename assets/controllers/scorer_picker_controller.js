import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/*
 * Scorer multi-picker for the guess form (tom-select over the source's roster
 * pool, options grouped per team via <optgroup>).
 *
 *   select    target — the <select multiple> with `h|Name` / `a|Name` option
 *                      values (side prefix + player name; see below).
 *   payload   target — hidden input bound to the LiveComponent `scorersJson`
 *                      model; every change writes a JSON list of
 *                      {side: 'home'|'away', name} and dispatches `input` so the
 *                      live controller syncs it.
 *   sideRadio target — the „Nového hráče přidat do týmu" toggle; free-typed
 *                      names are created under the currently selected side.
 *
 * The whole picker sits inside a data-live-ignore island: live re-renders never
 * morph it, so the tom-select DOM (and the user's in-progress selection)
 * survives model updates. State flows exclusively through the payload input.
 *
 * Option value encoding: `h|` / `a|` prefix + raw player name. A `|` inside a
 * player name is harmless — only the first two characters are the prefix.
 */
export default class extends Controller {
    static targets = ['select', 'payload', 'sideRadio'];

    static values = {
        max: { type: Number, default: 5 },
        homeTeam: String,
        awayTeam: String,
    };

    connect() {
        const teamOf = (value) => (value.startsWith('h|') ? this.homeTeamValue : this.awayTeamValue);

        // createOnBlur race: clicking a side radio blurs the tom-select input
        // and fires `create` BEFORE the radio's `checked` state flips (checked
        // updates on click, blur happens on pointerdown). Capture the intended
        // side on pointerdown so the create callback reads the side the user is
        // clicking right now; cleared next tick, when `checked` is reliable.
        this.pendingSide = null;
        this.onSidePointerDown = (event) => {
            this.pendingSide = event.currentTarget.value;
            setTimeout(() => {
                this.pendingSide = null;
            }, 0);
        };
        this.sideRadioTargets.forEach((radio) => radio.addEventListener('pointerdown', this.onSidePointerDown));

        this.instance = new TomSelect(this.selectTarget, {
            plugins: ['remove_button'],
            maxItems: this.maxValue,
            maxOptions: 200,
            create: (input) => {
                // Read the side at creation time — never a captured value.
                const side = this.currentSide();
                return {
                    value: `${side === 'home' ? 'h' : 'a'}|${input}`,
                    text: input,
                };
            },
            createOnBlur: true,
            render: {
                option_create: (data, escape) => `<div class="create py-1">Přidat hráče „<strong>${escape(data.input)}</strong>“…</div>`,
                no_results: () => '<div class="no-results">Nic nenalezeno — napište jméno nového hráče</div>',
                item: (data, escape) => `<div class="leading-tight"><div>${escape(data.text)}</div><small class="block text-[10px] leading-tight opacity-60">${escape(teamOf(String(data.value)))}</small></div>`,
            },
            onChange: () => this.sync(),
        });

        // Reflect any server-side prefill (selected options) into the payload once.
        this.sync();
    }

    disconnect() {
        this.sideRadioTargets.forEach((radio) => radio.removeEventListener('pointerdown', this.onSidePointerDown));

        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }

    currentSide() {
        if (this.pendingSide) {
            return this.pendingSide;
        }

        const checked = this.sideRadioTargets.find((radio) => radio.checked);
        return checked ? checked.value : 'home';
    }

    sync() {
        if (!this.hasPayloadTarget || !this.instance) {
            return;
        }

        const values = this.instance.getValue();
        const list = (Array.isArray(values) ? values : [values]).filter((value) => value !== '');
        const payload = list.map((value) => ({
            side: String(value).startsWith('h|') ? 'home' : 'away',
            name: String(value).slice(2),
        }));

        this.payloadTarget.value = JSON.stringify(payload);
        this.payloadTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
