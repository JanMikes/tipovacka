import { Controller } from '@hotwired/stimulus';

/*
 * 8-box PIN entry (numeric). Syncs the visible single-char boxes into a hidden
 * input and toggles the submit button. Auto-advance, backspace-back, paste-fill.
 *
 * Markup:
 *   <form data-controller="pin-input" ...>
 *     <input type="hidden" name="pin" data-pin-input-target="value">
 *     <div class="pin-inputs">
 *       <input data-pin-input-target="box" ...> x8 (with a .pin-sep between 4 and 5)
 *     </div>
 *     <button data-pin-input-target="submit" disabled>...</button>
 *   </form>
 */
export default class extends Controller {
    static targets = ['box', 'value', 'submit'];

    connect() {
        this.boxTargets.forEach((box, i) => {
            box.addEventListener('input', (e) => this.onInput(e, i));
            box.addEventListener('keydown', (e) => this.onKeydown(e, i));
            box.addEventListener('paste', (e) => this.onPaste(e, i));
            box.addEventListener('focus', () => box.select());
        });
        this.sync();
    }

    onInput(e, i) {
        let v = (e.target.value || '').replace(/\D/g, '');
        e.target.value = v.slice(0, 1);
        if (v.length > 1) {
            const rest = v.slice(1).split('');
            for (let k = i + 1; k < this.boxTargets.length && rest.length; k++) {
                this.boxTargets[k].value = rest.shift();
            }
        }
        if (e.target.value && i < this.boxTargets.length - 1) {
            this.boxTargets[i + 1].focus();
        }
        this.sync();
    }

    onKeydown(e, i) {
        if (e.key === 'Backspace' && !e.target.value && i > 0) {
            this.boxTargets[i - 1].focus();
            this.boxTargets[i - 1].value = '';
            this.sync();
            e.preventDefault();
        } else if (e.key === 'ArrowLeft' && i > 0) {
            this.boxTargets[i - 1].focus();
        } else if (e.key === 'ArrowRight' && i < this.boxTargets.length - 1) {
            this.boxTargets[i + 1].focus();
        } else if (e.key === 'Enter' && this.hasSubmitTarget && !this.submitTarget.disabled) {
            // let the form submit naturally
        }
    }

    onPaste(e, i) {
        e.preventDefault();
        const txt = (e.clipboardData || window.clipboardData).getData('text') || '';
        const chars = txt.replace(/\D/g, '').split('');
        for (let k = i; k < this.boxTargets.length && chars.length; k++) {
            this.boxTargets[k].value = chars.shift();
        }
        const filled = txt.replace(/\D/g, '').length;
        const next = Math.min(i + filled, this.boxTargets.length - 1);
        this.boxTargets[next].focus();
        this.sync();
    }

    sync() {
        const code = this.boxTargets.map((b) => b.value).join('');
        this.boxTargets.forEach((b) => b.classList.toggle('filled', !!b.value));
        if (this.hasValueTarget) {
            this.valueTarget.value = code;
        }
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = code.length < this.boxTargets.length;
        }
    }
}
