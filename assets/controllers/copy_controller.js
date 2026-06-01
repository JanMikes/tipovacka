import { Controller } from '@hotwired/stimulus';

/*
 * One-click copy for a read-only field (PIN, invite link, ...).
 *
 * Copies the `source` input's value to the clipboard and gives „Zkopírováno"
 * feedback on the button for ~1.5s: adds a `copied` class and flips the two
 * label/icon targets (`labelCopy` <-> `labelCopied`).
 *
 * Usage:
 *   <div class="copy-field" data-controller="copy">
 *       <input data-copy-target="source" readonly value="...">
 *       <button type="button" data-copy-target="button" data-action="copy#copy"
 *               aria-label="Kopírovat">
 *           <span data-copy-target="labelCopy"><twig:ux:icon name="lucide:copy" .../></span>
 *           <span data-copy-target="labelCopied" class="hidden"><twig:ux:icon name="lucide:check" .../></span>
 *       </button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['source', 'button', 'labelCopy', 'labelCopied'];
    static values = {
        resetDelay: { type: Number, default: 1500 },
    };

    disconnect() {
        if (this.resetTimeout) {
            clearTimeout(this.resetTimeout);
        }
    }

    async copy(event) {
        event.preventDefault();

        const text = this.sourceTarget.value;

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                this.fallbackCopy();
            }
        } else {
            this.fallbackCopy();
        }

        this.showCopied();
    }

    fallbackCopy() {
        this.sourceTarget.focus();
        this.sourceTarget.select();
        try {
            document.execCommand('copy');
        } catch {
            // Nothing else we can do; leave the selection so the user can copy manually.
        }
    }

    showCopied() {
        if (this.hasButtonTarget) {
            this.buttonTarget.classList.add('copied');
        }
        if (this.hasLabelCopyTarget && this.hasLabelCopiedTarget) {
            this.labelCopyTarget.classList.add('hidden');
            this.labelCopiedTarget.classList.remove('hidden');
        }

        if (this.resetTimeout) {
            clearTimeout(this.resetTimeout);
        }
        this.resetTimeout = setTimeout(() => this.reset(), this.resetDelayValue);
    }

    reset() {
        if (this.hasButtonTarget) {
            this.buttonTarget.classList.remove('copied');
        }
        if (this.hasLabelCopyTarget && this.hasLabelCopiedTarget) {
            this.labelCopyTarget.classList.remove('hidden');
            this.labelCopiedTarget.classList.add('hidden');
        }
    }
}
