# Form feedback — every form says what is wrong and takes you to it

**Why:** on 2026-10-17 a visitor submitted the invitation sign-up four times, got four
422s and gave up. The errors were rendered — under fields a screen above the button on
his phone — and nothing on the server recorded them. The fix became a layer EVERY form
gets, not a sign-up special.

## What every form gets for free

Wired once in `templates/form/_form_theme.html.twig` (`form_start`), so any
`form_start(form)` has it — no per-form code:

- **Stimulus `form-feedback`** (`assets/controllers/form_feedback_controller.js`) on the `<form>`:
  - **takes the visitor to the problem** — after a rejected submit it scrolls the FIRST
    `[aria-invalid="true"]` field to the middle of the screen (clear of the sticky top
    bar), focuses it and pulses it once. Classic form: when the re-rendered page loads
    (only the first rejected form of a page). Live form: after the re-render that answers
    a submit (a `submit` event or a click on a submit button → the next request carrying
    an action), hooked via `getComponent()` + `request:started`/`render:finished`;
  - **submit-time sync (Live)** — re-dispatches `change` on every field on submit and once
    Live connects (`live:connect`; Live is lazy-loaded and connects after this controller).
    Values typed before the Live JS booted, or autofilled without a `change`, otherwise
    never reach the model and the server reports an „empty" field the visitor sees filled;
  - **double-submit guard (classic)** — a second submit while the first is on its way is
    swallowed, `aria-busy` dims the buttons. Buttons are never disabled (a disabled button
    drops its name/value); a submit cancelled by another handler (confirm modal, Turbo)
    doesn't count; freed after 8 s or on `pageshow` (downloads, `target=_blank`);
  - `focusField` (summary links) and `fill` (one-tap fix buttons:
    `data-action="form-feedback#fill" data-form-feedback-field-param="…" data-form-feedback-value-param="…"`).
- **Error summary** — `templates/form/_error_summary.html.twig`, every error as
  *Field: message* linking to the field. The theme renders it at the top of a rejected
  **classic** form. A **Live** form opts in — `form_start(form, {…, error_summary: this.isValidated})`,
  or include the partial where it reads best (sign-up: right above the button) with
  `submitted: this.isValidated` so typing into a fresh form never shouts. Pass
  `error_summary: false` to suppress, `error_summary_title` to retitle.
- **Marked fields** — the theme sets `aria-invalid` (checkboxes too); `app.css` turns the
  border, the `.field` label and leading icon red, rings an invalid checkbox, and form
  errors carry an alert icon. Keep a field and its label in a `.field` wrapper.

A hand-written `<form>` opts in with `data-controller="form-feedback"` (append to its other
controllers) and marks bad inputs with `aria-invalid="true"`.

## Rules of thumb

- **No flash from a Live action that returns `null`.** A flash only renders on the NEXT
  full page load — the visitor never sees it. Use an inline field error (`FormError` +
  reset the cached `formView`, see `addInlineFormError()` in `Auth:RegistrationForm`).
- A message says **what is wrong and how to fix it**, in vykání. State non-obvious rules
  up front as a hint, not only as a rejection.

## Sign-up specifics (Auth:RegistrationForm, Auth:InvitationForm)

- Summary right above the submit button; nickname rule stated up front (no spaces or
  diacritics) plus a free suggestion from `Service/SignUp/NicknameSuggester`
  (`Petr Svoboda` → `petr.svoboda`, `…2` if taken), one tap via `fill`.
- E-mail typo hint — `_email_help.html.twig` + `Service/SignUp/EmailTypoSuggester`: a
  mistyped mailbox domain (seznam.cy, gmail.cz) is a valid address that never receives the
  verification mail, so the field offers „Nemysleli jste …@seznam.cz?".
- **`signup` log channel** — `Service/SignUp/RejectedSignUpLog` records path, e-mail and
  field → messages (never passwords) on every rejection; prod streams it to stderr outside
  the fingers_crossed buffer: `docker compose logs web | grep "Sign-up rejected"`.
