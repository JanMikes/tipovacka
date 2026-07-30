import { Controller } from '@hotwired/stimulus';

/*
 * The smallest possible <dialog> opener (item 26): it shows a dialog and it
 * closes it. Nothing else — no form, no persistence, no content generation.
 *
 * The two existing dialog controllers are both the wrong shape for a plain
 * read-only panel: `confirm` wraps a <form> and intercepts its submit, and
 * `boost-intro` opens itself once and POSTs the dismissal on close. This one
 * owns no state, so the same controller can serve any „open this panel" button.
 *
 * The dialog is server-rendered markup, so it keeps its own accessible name
 * (`aria-labelledby`) and its close controls are ordinary buttons in the
 * template. `showModal()` puts it in the top layer and makes the rest of the
 * document inert — but inert is not unscrollable: measured in a real browser,
 * the page behind still scrolls under an open <dialog>. So this controller
 * freezes the document while the dialog is open (compensating for a classic
 * scrollbar's width so nothing jumps) and restores it on the `close` event —
 * the ONE funnel every dismissal ends in, so the freeze can never outlive the
 * dialog. `disconnect()` releases it too, for the same reason.
 *
 * Closing works three ways and all of them are just `close()`: the ✕ / „Zavřít"
 * buttons (`data-action="modal#close"`), Esc (native <dialog> behaviour), and a
 * click on the backdrop — wired by hand here, exactly as confirm_controller.js
 * does it, because a native modal <dialog> does NOT close on a backdrop click.
 *
 * Usage:
 *   <div data-controller="modal">
 *       <button type="button" data-action="modal#open">Pravidla</button>
 *       <dialog data-modal-target="dialog" aria-labelledby="…">
 *           …
 *           <button type="button" data-action="modal#close">Zavřít</button>
 *       </dialog>
 *   </div>
 */
export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        this.onBackdropClick = this.onBackdropClick.bind(this);
        this.onClose = this.onClose.bind(this);

        if (this.hasDialogTarget) {
            this.dialogTarget.addEventListener('click', this.onBackdropClick);
            this.dialogTarget.addEventListener('close', this.onClose);
        }
    }

    disconnect() {
        if (!this.hasDialogTarget) {
            return;
        }

        this.dialogTarget.removeEventListener('click', this.onBackdropClick);
        this.dialogTarget.removeEventListener('close', this.onClose);

        // Never leave a modal open with no controller left to close it: an open
        // <dialog> in the top layer would keep the whole document inert.
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }

        this.thawPage();
    }

    open(event) {
        event?.preventDefault();

        if (!this.hasDialogTarget || typeof this.dialogTarget.showModal !== 'function') {
            return;
        }

        this.freezePage();
        this.dialogTarget.showModal();
    }

    close(event) {
        event?.preventDefault();

        if (this.hasDialogTarget && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    onBackdropClick(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }

    /** Esc, the backdrop and both buttons all end here. */
    onClose() {
        this.thawPage();
    }

    freezePage() {
        if (this.frozen) {
            return;
        }
        this.frozen = true;

        const root = document.documentElement;
        // A classic scrollbar disappears with `overflow: hidden`, which would
        // shift the whole page sideways as the dialog opens. An overlay
        // scrollbar (macOS, mobile) measures 0, so this costs nothing there.
        const gutter = window.innerWidth - root.clientWidth;

        this.previousOverflow = root.style.overflow;
        this.previousPaddingRight = root.style.paddingRight;

        root.style.overflow = 'hidden';

        if (gutter > 0) {
            root.style.paddingRight = `${gutter}px`;
        }
    }

    thawPage() {
        if (!this.frozen) {
            return;
        }
        this.frozen = false;

        const root = document.documentElement;
        root.style.overflow = this.previousOverflow ?? '';
        root.style.paddingRight = this.previousPaddingRight ?? '';
    }
}
