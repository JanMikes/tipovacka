import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: '' },
        submitOnChange: { type: Boolean, default: false },
        noResultsText: { type: String, default: 'Nic nenalezeno' },
    };

    connect() {
        // Primary line = nickname when present; otherwise fall back to the option's visible text
        // (which is the fullName for nickname-less users, or '' for the empty placeholder).
        // Subtitle line = fullName, shown only when there's a separate nickname above it.
        const primary = (data) => data.nickname || data.text;
        const subtitle = (data) => (data.fullName && data.nickname) ? data.fullName : '';

        const options = {
            allowEmptyOption: true,
            create: false,
            maxOptions: 200,
            searchField: ['text'],
            dataAttr: 'data-data',
            placeholder: this.placeholderValue || undefined,
            render: {
                no_results: () => `<div class="no-results">${this.noResultsTextValue}</div>`,
                option: (data, escape) => {
                    const sub = subtitle(data);
                    const unverified = data.unverified ? ' <span class="text-xs text-gray-400">(neověřený)</span>' : '';
                    return `<div class="py-1"><div class="leading-tight">${escape(primary(data))}${unverified}</div>${sub ? `<small class="mt-0.5 block text-xs leading-tight text-navy-900/60">${escape(sub)}</small>` : ''}</div>`;
                },
                item: (data, escape) => {
                    const sub = subtitle(data);
                    return `<div class="leading-tight"><div>${escape(primary(data))}</div>${sub ? `<small class="block text-xs leading-tight text-navy-900/60">${escape(sub)}</small>` : ''}</div>`;
                },
            },
        };

        if (this.submitOnChangeValue) {
            options.onChange = () => {
                const form = this.element.form;
                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            };
        }

        this.instance = new TomSelect(this.element, options);
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
