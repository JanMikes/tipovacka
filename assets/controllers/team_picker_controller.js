import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/*
 * Team picker for the match create/edit form.
 *
 * Enhances a plain text <input> (home/away team name) into a single-select combobox
 * that autocompletes existing teams from the source's resolution scope (global
 * directory for curated sources, local teams for private ones) and lets the organizer
 * create a new team just by typing its name.
 *
 * Progressive enhancement: with JS off the text input still posts the typed name, and
 * the server resolves it to a Team either way — so the picker is pure convenience.
 */
export default class extends Controller {
    static values = { url: String };

    connect() {
        const input = this.element.matches('input') ? this.element : this.element.querySelector('input');
        if (!input || input.dataset.teamPickerReady) {
            return;
        }
        input.dataset.teamPickerReady = '1';

        const current = input.value;

        this.select = new TomSelect(input, {
            dropdownParent: 'body', // never let a card's overflow/stacking context crop it
            maxItems: 1,
            valueField: 'name',
            labelField: 'name',
            searchField: ['name'],
            create: (name) => ({ name: name.trim() }),
            createOnBlur: true,
            persist: false,
            maxOptions: 20,
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
                // Without this override the create row falls back to tom-select's stock
                // English „Add <b>…</b>…". The `create` class is what the dark skin (and
                // its `.create.active` highlight) keys on — keep it.
                option_create: (data, escape) => `<div class="create py-1">Přidat tým „<strong>${escape(data.input)}</strong>“…</div>`,
                // `no-results` is the dark skin's class for this state (B12) — the other four
                // pickers use it, so styling it with ad-hoc utilities made the same „nothing
                // found" row read as a different component depending on which picker you opened.
                no_results: () => '<div class="no-results">Nic nenalezeno — napište název nového týmu</div>',
            },
        });

        // Preserve the pre-filled value on edit (the current team's name).
        if (current) {
            this.select.addOption({ name: current });
            this.select.setValue(current, true);
        }
    }

    disconnect() {
        if (this.select) {
            this.select.destroy();
            this.select = null;
        }
    }
}
