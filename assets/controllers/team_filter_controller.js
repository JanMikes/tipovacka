import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/*
 * Multi-team picker for the competition team filter (create wizard + manage page).
 *
 * Enhances a <select multiple> into a tom-select combobox that autocompletes the
 * teams playing in the chosen source (remote endpoint, keyed on the team UUID).
 * Selected team ids are mirrored, comma-joined, into an optional hidden `payload`
 * input so a LiveComponent (the wizard) can read them via a `data-model` binding;
 * on a plain form (the manage page) the <select multiple name="…[]"> posts them.
 *
 * The whole picker lives in a `data-live-ignore` island so live re-renders never
 * wipe the tom-select DOM. Currently-selected teams are rendered as <option selected>
 * server-side, so chips survive step navigation (they reappear from the DOM, not
 * from ids alone).
 */
export default class extends Controller {
    static targets = ['select', 'payload'];
    static values = { url: String };

    connect() {
        if (this.selectTarget.dataset.teamFilterReady) {
            return;
        }
        this.selectTarget.dataset.teamFilterReady = '1';

        this.select = new TomSelect(this.selectTarget, {
            dropdownParent: 'body', // never let a card's overflow/stacking context crop it
            // The plugin's stock chip tooltip is the English „Remove".
            plugins: { remove_button: { title: 'Odebrat' } },
            valueField: 'id',
            labelField: 'name',
            searchField: ['name'],
            persist: false,
            create: false,
            maxOptions: 30,
            loadThrottle: 200,
            load: (query, callback) => {
                fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                    .then((response) => (response.ok ? response.json() : []))
                    .then((teams) => callback(teams))
                    .catch(() => callback());
            },
            render: {
                option: (data, escape) => {
                    const meta = [data.shortName, data.country].filter(Boolean).join(' · ');
                    return `<div class="flex items-center justify-between gap-2"><span>${escape(data.name)}</span>${meta ? `<span class="text-xs text-white/40">${escape(meta)}</span>` : ''}</div>`;
                },
                item: (data, escape) => `<div>${escape(data.name)}</div>`,
                no_results: () => '<div class="px-3 py-2 text-sm text-white/40">Žádný tým nenalezen</div>',
            },
        });

        this.select.on('change', () => this.sync());
        // Reconcile the payload with the DOM's actual selection on mount — this also
        // clears any stale id list left in the hidden input when the picker remounts.
        this.sync();
    }

    sync() {
        if (!this.hasPayloadTarget) {
            return;
        }

        this.payloadTarget.value = this.select.getValue().join(',');
        this.payloadTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    disconnect() {
        if (this.select) {
            this.select.destroy();
            this.select = null;
        }
    }
}
