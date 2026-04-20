import { Controller } from '@hotwired/stimulus';

/*
 * Confirms rule-configuration submission when evaluations already exist.
 * If no evaluations are present (count is 0), the form submits without a prompt.
 */
export default class extends Controller {
    static values = {
        count: Number,
    };

    confirmIfNeeded(event) {
        if (this.countValue <= 0) {
            return;
        }

        const message = 'Tato změna přepočítá body všech dosud odehraných zápasů. Pokračovat?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    }
}
