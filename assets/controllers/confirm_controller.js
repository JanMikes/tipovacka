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
 */
export default class extends Controller {
    static values = {
        message: String,
        title: { type: String, default: 'Potvrdit akci' },
        confirmLabel: { type: String, default: 'Ano, pokračovat' },
        cancelLabel: { type: String, default: 'Zrušit' },
        variant: { type: String, default: 'danger' },
    };

    connect() {
        this.confirmed = false;
        this.onSubmit = this.onSubmit.bind(this);
        this.element.addEventListener('submit', this.onSubmit);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
        if (this.dialog) {
            this.dialog.remove();
            this.dialog = null;
        }
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
