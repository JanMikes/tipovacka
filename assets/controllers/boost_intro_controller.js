import { Controller } from '@hotwired/stimulus';

/*
 * First-visit „co si můžu odemknout" modal on a competition's detail page
 * (item 19). The server renders the <dialog> ONLY when it is actually due, so
 * this controller has no decision to make: it opens it, and it persists the
 * dismissal.
 *
 * The dismissal is ONE path: every way of closing a <dialog> — the ✕, the
 * „Pochopil jsem, již nezobrazovat" button, Esc, and a click on the backdrop —
 * ends in the `close` event, and that is where the form is submitted (in the
 * background, so the page the player came to read is not reloaded). There is
 * therefore no way to close it without the dismissal sticking.
 *
 * Without JavaScript the dialog is never opened and the page is unaffected: a
 * closed <dialog> is `display: none`, so it costs nothing and hides nothing.
 *
 * Usage:
 *   <div data-controller="boost-intro">
 *       <dialog data-boost-intro-target="dialog">
 *           <form method="post" action="…" data-boost-intro-target="form">…</form>
 *           <button type="button" data-action="boost-intro#dismiss">✕</button>
 *       </dialog>
 *   </div>
 */
export default class extends Controller {
    static targets = ['dialog', 'form'];

    connect() {
        this.persisted = false;
        this.onClose = this.onClose.bind(this);
        this.onBackdropClick = this.onBackdropClick.bind(this);

        if (!this.hasDialogTarget || typeof this.dialogTarget.showModal !== 'function') {
            return;
        }

        this.dialogTarget.addEventListener('close', this.onClose);
        // A native modal <dialog> closes on Esc but NOT on a backdrop click, so the
        // „click outside" dismissal has to be wired by hand — exactly as
        // confirm_controller.js does it, for one dialog vocabulary, not two.
        this.dialogTarget.addEventListener('click', this.onBackdropClick);
        this.dialogTarget.showModal();
    }

    disconnect() {
        if (this.hasDialogTarget) {
            this.dialogTarget.removeEventListener('close', this.onClose);
            this.dialogTarget.removeEventListener('click', this.onBackdropClick);
        }
    }

    onBackdropClick(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }

    /** The ✕ and the „Pochopil jsem" button — both just close the dialog. */
    dismiss(event) {
        event.preventDefault();
        this.dialogTarget.close();
    }

    onClose() {
        if (this.persisted || !this.hasFormTarget) {
            return;
        }
        this.persisted = true;

        const form = this.formTarget;

        // `keepalive` so the request survives an immediate navigation (the player
        // may close the modal by clicking a link inside it).
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {
            // A failed dismissal simply means the modal is due again next time —
            // never a broken page.
            this.persisted = false;
        });
    }
}
