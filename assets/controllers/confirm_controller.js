import { Controller } from '@hotwired/stimulus';

/*
 * Intercepts form submission and asks the user to confirm via a styled modal
 * before actually submitting. Replacement for window.confirm() on destructive
 * actions (delete, remove, revoke, etc.).
 *
 * Usage:
 *   <form method="post" action="..."
 *         data-controller="confirm"
 *         data-confirm-message-value="Opravdu chceš tohoto člena odebrat?">
 *       ...
 *   </form>
 *
 * Optional values:
 *   data-confirm-title-value="Custom title"
 *   data-confirm-confirm-label-value="Ano, smazat"
 *   data-confirm-cancel-label-value="Zpět"
 *   data-confirm-variant-value="danger" | "warning"
 *
 * Optional target — a dialog that also ASKS something (B2 „Uzamknout tipy"):
 *
 *   <div data-confirm-target="fields" hidden> …inputs… </div>
 *
 * Optional target — a submit that must only exist WHILE this controller is
 * connected (B27: a whole paywall card stretched over its own purchase form):
 *
 *   <button type="submit" data-confirm-target="stretch" class="card-stretch" hidden>…</button>
 *
 * It is rendered `hidden` and unhidden on connect, re-hidden on disconnect. A
 * card-sized control that spends credits is only safe because the dialog opens
 * first, so the big target is the ENHANCEMENT and the small explicit button is
 * the floor — the inverse of the usual direction, on purpose.
 *
 * The element is moved into the dialog body on first open and revealed there;
 * because the dialog lives on <body> (outside the form), every form control
 * inside it gets a `form="<the form's id>"` attribute so it is still submitted
 * with the form. Keep the markup usable without JS: the form must submit
 * correctly with the field defaults, since no dialog is ever shown then.
 */
export default class extends Controller {
    static targets = ['fields', 'stretch'];

    static values = {
        message: String,
        title: { type: String, default: 'Potvrdit akci' },
        confirmLabel: { type: String, default: 'Ano, pokračovat' },
        cancelLabel: { type: String, default: 'Zrušit' },
        variant: { type: String, default: 'danger' },
    };

    connect() {
        this.confirmed = false;
        this.adoptedFields = null;
        this.fieldsAnchor = null;
        this.onSubmit = this.onSubmit.bind(this);
        this.element.addEventListener('submit', this.onSubmit);
        this.toggleStretch(true);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
        this.toggleStretch(false);
        // Give the fields back to the form BEFORE the dialog is destroyed —
        // see releaseFields().
        this.releaseFields();
        if (this.dialog) {
            this.dialog.remove();
            this.dialog = null;
        }
    }

    /*
     * B27 — an oversized submit (a whole card painted over its own purchase form)
     * exists only while this controller is connected, because the dialog is the
     * only thing that makes such a target safe. Server-side guards still apply
     * (CSRF + the partial unique index that makes a double buy idempotent); this
     * is about not offering an accidental click, not about securing the spend.
     */
    toggleStretch(enabled) {
        this.stretchTargets.forEach((element) => {
            element.hidden = !enabled;
        });
    }

    onSubmit(event) {
        if (this.confirmed) {
            return;
        }
        event.preventDefault();
        this.lastSubmitter = event.submitter ?? null;
        this.openDialog();
    }

    openDialog() {
        if (!this.dialog) {
            this.buildDialog();
        }
        this.dialog.showModal();
        requestAnimationFrame(() => this.cancelBtn?.focus());
    }

    buildDialog() {
        const isDanger = this.variantValue !== 'warning';
        const iconBg = isDanger
            ? 'bg-loss/15 text-loss border border-loss/30'
            : 'bg-draw/15 text-draw border border-draw/30';
        const confirmClasses = isDanger
            ? 'btn btn-danger'
            : 'btn btn-success';

        const dialog = document.createElement('dialog');
        dialog.className = 'confirm-dialog modal-panel w-full max-w-md p-0';

        const container = document.createElement('div');
        container.className = 'p-6';

        const header = document.createElement('div');
        header.className = 'flex items-start gap-4';

        const iconEl = document.createElement('span');
        iconEl.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full ' + iconBg;
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

        const textWrap = document.createElement('div');
        textWrap.className = 'min-w-0 flex-1';

        const titleEl = document.createElement('h2');
        titleEl.className = 'text-lg font-semibold text-white';
        titleEl.textContent = this.titleValue;

        const msgEl = document.createElement('p');
        msgEl.className = 'mt-1 text-sm text-white/70';
        msgEl.textContent = this.messageValue;

        textWrap.append(titleEl, msgEl);
        header.append(iconEl, textWrap);

        if (this.hasFieldsTarget) {
            dialog.classList.add('has-fields');
            this.adoptFields(textWrap);
        }

        const actions = document.createElement('div');
        actions.className = 'mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-ghost btn-sm';
        cancelBtn.textContent = this.cancelLabelValue;
        cancelBtn.addEventListener('click', () => this.dialog.close());

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = confirmClasses + ' btn-sm';
        confirmBtn.textContent = this.confirmLabelValue;
        confirmBtn.addEventListener('click', () => this.confirm());

        actions.append(cancelBtn, confirmBtn);

        container.append(header, actions);
        dialog.append(container);

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        document.body.append(dialog);
        this.dialog = dialog;
        this.cancelBtn = cancelBtn;
    }

    /*
     * Moves the fields target into the dialog body. The dialog is appended to
     * <body>, so the controls leave the <form> element — re-associate them via
     * the `form` attribute, which submits them all the same.
     */
    adoptFields(parent) {
        if (!this.element.id) {
            this.element.id = `confirm-form-${Math.random().toString(36).slice(2, 10)}`;
        }

        const fields = this.fieldsTarget;

        // Named controls only — a picker's internal widgets (flatpickr's hour /
        // minute number inputs) carry no name and must not be re-associated.
        fields.querySelectorAll('input[name], select[name], textarea[name]')
            .forEach((control) => control.setAttribute('form', this.element.id));

        // Remember where it came from so releaseFields() can put it back exactly.
        this.fieldsAnchor = document.createComment('confirm:fields');
        fields.before(this.fieldsAnchor);

        fields.hidden = false;
        parent.append(fields);
        this.adoptedFields = fields;
    }

    /*
     * Undo adoptFields(). Without this, disconnect() would destroy the dialog
     * WITH the adopted element inside it — and the element is the controller's
     * only `fields` target. A later reconnect (the form re-parented, its
     * data-controller re-evaluated, a re-render above it) would then find no
     * target and silently build the plain message-only dialog, which is
     * byte-identical to the documented no-JS fallback. That is B16's failure
     * mode; a controller has to be able to connect twice.
     */
    releaseFields() {
        const fields = this.adoptedFields;
        if (!fields) {
            return;
        }
        this.adoptedFields = null;

        fields.hidden = true;
        fields.querySelectorAll('input[name], select[name], textarea[name]')
            .forEach((control) => control.removeAttribute('form'));

        if (this.fieldsAnchor?.parentNode) {
            this.fieldsAnchor.replaceWith(fields);
        } else {
            this.element.append(fields);
        }
        this.fieldsAnchor = null;
    }

    confirm() {
        this.confirmed = true;
        this.dialog.close();
        if (typeof this.element.requestSubmit === 'function') {
            this.element.requestSubmit(this.lastSubmitter);
        } else {
            this.element.submit();
        }
    }
}
