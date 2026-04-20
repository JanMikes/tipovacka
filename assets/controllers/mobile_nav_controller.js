import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'button', 'iconOpen', 'iconClose'];

    connect() {
        this.open = false;
    }

    toggle() {
        this.open = !this.open;

        if (this.open) {
            this.menuTarget.classList.remove('hidden');
            this.iconOpenTarget.classList.add('hidden');
            this.iconCloseTarget.classList.remove('hidden');
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        } else {
            this.menuTarget.classList.add('hidden');
            this.iconOpenTarget.classList.remove('hidden');
            this.iconCloseTarget.classList.add('hidden');
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }
    }
}
