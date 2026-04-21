import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: '' },
        submitOnChange: { type: Boolean, default: false },
        noResultsText: { type: String, default: 'Nic nenalezeno' },
    };

    connect() {
        const options = {
            allowEmptyOption: true,
            create: false,
            maxOptions: 200,
            searchField: ['text'],
            placeholder: this.placeholderValue || undefined,
            render: {
                no_results: () => `<div class="no-results">${this.noResultsTextValue}</div>`,
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
