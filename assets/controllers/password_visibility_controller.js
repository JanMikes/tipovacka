import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'iconShow', 'iconHide'];

    toggle() {
        const input = this.inputTarget;
        const showingPassword = input.type === 'password';

        input.type = showingPassword ? 'text' : 'password';

        if (this.hasIconShowTarget && this.hasIconHideTarget) {
            this.iconShowTarget.classList.toggle('hidden', showingPassword);
            this.iconHideTarget.classList.toggle('hidden', !showingPassword);
        }
    }
}
