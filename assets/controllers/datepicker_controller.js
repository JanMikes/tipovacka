import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { Czech } from 'flatpickr/dist/l10n/cs.js';

const MODES = {
    date: {
        dateFormat: 'Y-m-d',
        altFormat: 'j. F Y',
    },
    datetime: {
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        altFormat: 'j. F Y · H:i',
    },
    time: {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        altFormat: 'H:i',
    },
};

const CLEAR_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>';

export default class extends Controller {
    static values = {
        mode: { type: String, default: 'datetime' },
        minDate: String,
        maxDate: String,
    };

    connect() {
        const mode = MODES[this.modeValue] ?? MODES.datetime;

        // Wrap the input so we can absolutely-position a clear button beside the alt input.
        const wrapper = document.createElement('span');
        wrapper.className = 'relative block w-full';
        this.element.parentNode.insertBefore(wrapper, this.element);
        wrapper.appendChild(this.element);
        this.wrapper = wrapper;

        // Reserve space on the right for the clear button so it doesn't overlap text.
        const altInputClass = `${this.element.className} pr-9`.trim();

        const options = {
            locale: Czech,
            time_24hr: true,
            monthSelectorType: 'static',
            disableMobile: true,
            altInput: true,
            altInputClass,
            ...mode,
            onReady: (_dates, _str, instance) => this.toggleClearButton(instance),
            onChange: (_dates, _str, instance) => this.toggleClearButton(instance),
        };

        if (this.hasMinDateValue && this.minDateValue !== '') {
            options.minDate = this.minDateValue;
        }
        if (this.hasMaxDateValue && this.maxDateValue !== '') {
            options.maxDate = this.maxDateValue;
        }

        this.instance = flatpickr(this.element, options);

        const button = document.createElement('button');
        button.type = 'button';
        button.tabIndex = -1;
        button.setAttribute('aria-label', 'Vymazat');
        button.className = 'absolute right-2.5 top-1/2 -translate-y-1/2 hidden h-5 w-5 items-center justify-center rounded text-navy-900/40 transition hover:text-navy-900 focus:outline-none focus:ring-2 focus:ring-cyan-500';
        button.innerHTML = CLEAR_ICON;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            this.instance?.clear();
        });
        wrapper.appendChild(button);
        this.clearButton = button;

        this.toggleClearButton(this.instance);
    }

    toggleClearButton(instance) {
        if (!this.clearButton) {
            return;
        }
        const hasValue = (instance?.input?.value ?? '') !== '';
        if (hasValue) {
            this.clearButton.classList.remove('hidden');
            this.clearButton.classList.add('inline-flex');
        } else {
            this.clearButton.classList.add('hidden');
            this.clearButton.classList.remove('inline-flex');
        }
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
        if (this.clearButton) {
            this.clearButton.remove();
            this.clearButton = null;
        }
        if (this.wrapper && this.wrapper.parentNode) {
            const parent = this.wrapper.parentNode;
            while (this.wrapper.firstChild) {
                parent.insertBefore(this.wrapper.firstChild, this.wrapper);
            }
            parent.removeChild(this.wrapper);
            this.wrapper = null;
        }
    }
}
