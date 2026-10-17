import { Controller } from '@hotwired/stimulus';

/*
 * Makes a rejected sign-up impossible to miss, and makes sure the server judges
 * what the visitor actually sees in the fields.
 *
 * 1) Submit-time sync. The Live form models on `change`; a value typed before the
 *    Live controller booted (a slow phone on mobile data) or filled by an
 *    autofill that fires no `change` never reaches the model — the server then
 *    reports „Zadejte e-mail" about a field that visibly holds one. On submit we
 *    re-dispatch `change` on every field (capture phase, so it runs before
 *    `live#action`), and Live sends the current values together with the action.
 *
 * 2) Take the visitor to the problem. On a phone the error under the e-mail
 *    field renders a screen above the button, so tapping it looked like nothing
 *    happened. The component bumps `attempt` on every submit; when it changes we
 *    scroll the FIRST invalid field to the middle of the screen, focus it and
 *    pulse it once. The summary above the button lists every problem; each item
 *    jumps to its field the same way (`focusField`).
 *
 * 3) One-tap fixes. `fill` puts a suggested value (a free nickname, the e-mail
 *    with its domain typo corrected) into a field and lets Live re-validate it.
 *
 * Usage (on the <form> inside the Live component):
 *   data-controller="signup-feedback"
 *   data-signup-feedback-attempt-value="{{ this.submitAttempts }}"
 * the summary element carries data-signup-feedback-target="summary"; a fix button
 *   data-action="signup-feedback#fill"
 *   data-signup-feedback-field-param="nickname" data-signup-feedback-value-param="…"
 */
export default class extends Controller {
    static targets = ['summary'];
    static values = { attempt: Number };

    connect() {
        this.syncFields = this.syncFields.bind(this);
        this.element.addEventListener('submit', this.syncFields, true);

        // Typed before the JS arrived: the first Live re-render would morph such a
        // field back to the model's empty value. Hand it to the model instead, once
        // the parent Live controller listens. Live is lazy-loaded and usually
        // connects AFTER this controller — hence `live:connect`; the frame-later
        // call covers the opposite order.
        this.syncTypedBeforeBoot = this.syncTypedBeforeBoot.bind(this);
        this.liveRoot = this.element.closest('[data-controller~="live"]');
        this.liveRoot?.addEventListener('live:connect', this.syncTypedBeforeBoot);
        requestAnimationFrame(this.syncTypedBeforeBoot);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.syncFields, true);
        this.liveRoot?.removeEventListener('live:connect', this.syncTypedBeforeBoot);
    }

    syncFields() {
        this.fields().forEach((field) => field.dispatchEvent(new Event('change', { bubbles: true })));
    }

    syncTypedBeforeBoot() {
        this.fields().forEach((field) => {
            const changed = field.type === 'checkbox' || field.type === 'radio'
                ? field.checked !== field.defaultChecked
                : field.value !== field.defaultValue;

            if (changed) {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // A summary item: „Přezdívka: …" → the nickname field, centred and focused.
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

    attemptValueChanged(current, previous) {
        if (!current || current === previous) {
            return;
        }

        // Live morphs the DOM right after the attribute changes — wait a frame so
        // we look at the freshly rendered errors.
        requestAnimationFrame(() => {
            const firstInvalid = this.element.querySelector('[aria-invalid="true"]');

            if (firstInvalid) {
                this.reveal(firstInvalid);
            } else if (this.hasSummaryTarget) {
                this.summaryTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.summaryTarget.focus({ preventScroll: true });
            }
        });
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

    fields() {
        return [...this.element.querySelectorAll('input[name], select[name], textarea[name]')]
            .filter((field) => !field.disabled && field.type !== 'hidden');
    }
}
