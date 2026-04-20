import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    toggle() {
        const input = this.inputTarget;

        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
}
