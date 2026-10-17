import { Controller } from '@hotwired/stimulus';

/*
 * Every form tells the visitor what is wrong and takes them to it.
 *
 * Attached automatically to every Symfony form by the `form_start` block of
 * templates/form/_form_theme.html.twig (a hand-written <form> opts in with
 * data-controller="form-feedback"). One behaviour for classic and Live forms:
 *
 * 1) Take the visitor to the problem. A rejected form scrolls its FIRST invalid
 *    field to the middle of the screen (clear of the sticky top bar), focuses it
 *    and pulses it once. Classic form: right after the re-rendered page loads.
 *    Live form: after the re-render that answers a submit. On a phone the first
 *    error often renders a screen above the button — without this, tapping the
 *    button looked like nothing happened (2026-10-17, a visitor gave up after
 *    four tries). Items of the error summary jump to their field the same way.
 *
 * 2) Submit-time sync (Live). The Live form models on `change`; a value typed
 *    before the lazy Live JS connected (a slow phone) or autofilled without a
 *    `change` never reaches the model, and the server reports an „empty" field
 *    the visitor sees filled. We re-dispatch `change` on submit and on connect.
 *
 * 3) No double submit (classic). A second submit while the first is on its way
 *    is swallowed and the submit buttons show they are busy. Submit buttons are
 *    never disabled — a disabled button drops its name/value from the request.
 *
 * 4) One-tap fixes. `fill` puts a suggested value into a field:
 *      data-action="form-feedback#fill"
 *      data-form-feedback-field-param="nickname" data-form-feedback-value-param="…"
 */

// Only the first rejected classic form of a page grabs the focus on load.
let revealedOnLoad = false;

export default class extends Controller {
    static targets = ['summary'];

    connect() {
        this.onSubmit = this.onSubmit.bind(this);
        this.onClick = this.onClick.bind(this);
        this.syncTypedBeforeBoot = this.syncTypedBeforeBoot.bind(this);
        this.element.addEventListener('submit', this.onSubmit, true);
        this.element.addEventListener('click', this.onClick, true);

        this.liveRoot = this.element.closest('[data-controller~="live"]');

        if (this.liveRoot) {
            this.connectLive();
        } else if (!revealedOnLoad && (this.firstInvalid() || this.hasSummaryTarget)) {
            // A rejection with only form-level errors (a failed payment start) has no
            // invalid field — then the summary itself is what to show.
            revealedOnLoad = true;
            requestAnimationFrame(() => this.revealProblem());
        }
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit, true);
        this.element.removeEventListener('click', this.onClick, true);
        this.liveRoot?.removeEventListener('live:connect', this.syncTypedBeforeBoot);
        clearTimeout(this.busyTimer);
    }

    connectLive() {
        // Live is lazy-loaded and usually connects AFTER this controller.
        this.liveRoot.addEventListener('live:connect', this.syncTypedBeforeBoot);
        requestAnimationFrame(this.syncTypedBeforeBoot);

        // Imported on demand: a page without Live components never loads Live.
        import('@symfony/ux-live-component')
            .then(({ getComponent }) => getComponent(this.liveRoot))
            .then((component) => {
                if (this.hooked === component) {
                    return;
                }

                this.hooked = component;
                component.on('request:started', (request) => {
                    // A model-only request may start between the submit and the action.
                    if (this.submitting && (request.actions || []).length > 0) {
                        this.submitting = false;
                        this.answeringSubmit = true;
                    }
                });
                component.on('render:finished', () => {
                    if (this.answeringSubmit) {
                        this.answeringSubmit = false;
                        requestAnimationFrame(() => this.revealProblem());
                    }
                });
            })
            .catch(() => {});
    }

    onSubmit(event) {
        if (this.liveRoot) {
            this.submitting = true;
            this.syncFields();

            return;
        }

        if (this.element.dataset.formFeedbackBusy) {
            event.preventDefault();
            event.stopImmediatePropagation();

            return;
        }

        // Other handlers (the confirm modal, Turbo) may still cancel this submit —
        // only a submit that really leaves counts as busy.
        setTimeout(() => {
            if (!event.defaultPrevented) {
                this.markBusy();
            }
        }, 0);
    }

    // A Live submit button usually fires `live#action:prevent` on click, so no
    // `submit` event ever happens — the click is the submit.
    onClick(event) {
        if (this.liveRoot && event.target.closest('button[type="submit"], input[type="submit"]')) {
            this.submitting = true;
            this.syncFields();
        }
    }

    markBusy() {
        this.element.dataset.formFeedbackBusy = '1';
        this.element.setAttribute('aria-busy', 'true');
        // A download or a target=_blank form never navigates away: free it again.
        this.busyTimer = setTimeout(() => this.clearBusy(), 8000);
        window.addEventListener('pageshow', () => this.clearBusy(), { once: true });
    }

    clearBusy() {
        delete this.element.dataset.formFeedbackBusy;
        this.element.removeAttribute('aria-busy');
    }

    syncFields() {
        this.syncable().forEach((field) => field.dispatchEvent(new Event('change', { bubbles: true })));
    }

    syncTypedBeforeBoot() {
        this.syncable().forEach((field) => {
            const changed = field.type === 'checkbox' || field.type === 'radio'
                ? field.checked !== field.defaultChecked
                : field.value !== field.defaultValue;

            if (changed) {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // A summary item: „Přezdívka: …" → that field, centred and focused.
    focusField(event) {
        const id = (event.currentTarget.getAttribute('href') || '').replace(/^#/, '');
        const field = id ? document.getElementById(id) : null;

        if (!field) {
            return;
        }

        event.preventDefault();
        this.reveal(field);
    }

    fill({ params: { field: name, value } }) {
        const field = this.element.querySelector(`[name$="[${name}]"]`);

        if (!field || !value) {
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event('change', { bubbles: true }));
        field.focus();
    }

    revealProblem() {
        const field = this.firstInvalid();

        if (field) {
            this.reveal(field);
        } else if (this.hasSummaryTarget) {
            this.summaryTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.summaryTarget.focus({ preventScroll: true });
        }
    }

    reveal(field) {
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus({ preventScroll: true });

        const box = field.closest('.field') || field.closest('label') || field;
        box.classList.remove('is-flagged');
        // Restart the pulse even when the same field is flagged twice in a row.
        void box.offsetWidth;
        box.classList.add('is-flagged');
        setTimeout(() => box.classList.remove('is-flagged'), 1600);
    }

    firstInvalid() {
        return [...this.element.querySelectorAll('[aria-invalid="true"]')]
            .find((field) => field.type !== 'hidden' && field.getClientRects().length > 0) || null;
    }

    // Live reads a radio's `value` whether or not it is checked — a `change` on an
    // unchecked radio would make it the model's value (the last radio of a group
    // always won: the wizard flipped mode and monetization on every „Pokračovat").
    // Only the checked radio of a group speaks for it.
    // Islands under data-live-ignore (tom-select pickers) sync through their own
    // hidden inputs; poking their native <select> could overwrite the selection.
    syncable() {
        return this.fields().filter((field) => (field.type !== 'radio' || field.checked) && !field.closest('[data-live-ignore]'));
    }

    fields() {
        return [...this.element.querySelectorAll('input[name], select[name], textarea[name]')]
            .filter((field) => !field.disabled && field.type !== 'hidden');
    }
}
