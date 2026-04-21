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

export default class extends Controller {
    static values = {
        mode: { type: String, default: 'datetime' },
        minDate: String,
        maxDate: String,
    };

    connect() {
        const mode = MODES[this.modeValue] ?? MODES.datetime;

        const options = {
            locale: Czech,
            time_24hr: true,
            monthSelectorType: 'static',
            disableMobile: true,
            altInput: true,
            altInputClass: this.element.className,
            ...mode,
        };

        if (this.hasMinDateValue && this.minDateValue !== '') {
            options.minDate = this.minDateValue;
        }
        if (this.hasMaxDateValue && this.maxDateValue !== '') {
            options.maxDate = this.maxDateValue;
        }

        this.instance = flatpickr(this.element, options);
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
