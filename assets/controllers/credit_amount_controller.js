import { Controller } from '@hotwired/stimulus';

/*
 * Buy-credits form helper: preset buttons fill the amount input,
 * the active preset is highlighted and the submit button shows the
 * total price (1 credit = 1 CZK).
 */
export default class extends Controller {
    static targets = ['input', 'preset', 'total'];

    connect() {
        this.sync();
    }

    choose(event) {
        this.inputTarget.value = event.params.amount;
        this.sync();
    }

    sync() {
        const value = parseInt(this.inputTarget.value, 10);

        this.presetTargets.forEach((button) => {
            const active = parseInt(button.dataset.creditAmountAmountParam, 10) === value;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-ghost', !active);
        });

        if (this.hasTotalTarget) {
            this.totalTarget.textContent = Number.isFinite(value) && value > 0 ? value.toLocaleString('cs-CZ') : '—';
        }
    }
}
