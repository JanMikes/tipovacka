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
 * 2) Scroll to the summary. On a phone the error under the e-mail field renders
 *    a screen above the button, so tapping it looked like nothing happened. The
 *    component bumps `attempt` on every submit; when it changes and the error
 *    summary is present, we bring it into view and focus it (screen readers
 *    announce it via role=alert).
 *
 * Usage (on the <form> inside the Live component):
 *   data-controller="signup-feedback"
 *   data-signup-feedback-attempt-value="{{ this.submitAttempts }}"
 * and the summary element carries data-signup-feedback-target="summary".
 * A suggested-nickname button calls `signup-feedback#useNickname` with
 * data-signup-feedback-nickname-param="…".
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
        this.element.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            if (field.disabled || field.type === 'hidden') {
                return;
            }

            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    syncTypedBeforeBoot() {
        this.element.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            if (field.disabled || field.type === 'hidden') {
                return;
            }

            const changed = field.type === 'checkbox' || field.type === 'radio'
                ? field.checked !== field.defaultChecked
                : field.value !== field.defaultValue;

            if (changed) {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // „Použít george.raska" — puts the suggested nickname into the field; the
    // `change` lets Live re-validate it, so the error clears on the spot.
    useNickname({ params: { nickname } }) {
        const field = this.element.querySelector('input[name$="[nickname]"]');

        if (!field || !nickname) {
            return;
        }

        field.value = nickname;
        field.dispatchEvent(new Event('change', { bubbles: true }));
        field.focus();
    }

    attemptValueChanged(current, previous) {
        if (!current || current === previous) {
            return;
        }

        // Live morphs the DOM right after the attribute changes — wait a frame so
        // the freshly rendered summary is the one we scroll to.
        requestAnimationFrame(() => {
            if (!this.hasSummaryTarget) {
                return;
            }

            this.summaryTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.summaryTarget.focus({ preventScroll: true });
        });
    }
}
