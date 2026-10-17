# Sign-up feedback — a rejected registration says what and how

**Why:** on 2026-10-17 a visitor submitted the invitation sign-up four times, got four
422s and gave up. The errors were rendered — under fields a screen above the button on
his phone — and nothing on the server recorded them.

Applies to both Live sign-up forms: `Auth:RegistrationForm` (`/registrace`) and
`Auth:InvitationForm` (every invitation landing — e-mail, shareable link, PIN, global).

- **Error summary** — `templates/components/Auth/_error_summary.html.twig`, included right
  above the submit button. After a submit attempt (`isValidated`) it lists every error as
  *Field: message* with a link to the field; it shrinks live as fields get fixed.
- **Marked fields** — an invalid field turns red as a whole (border, label, leading icon,
  alert icon on the message; the consent checkbox gets a red ring and red text), styled
  globally from `aria-invalid` in `app.css` + the form theme.
- **`signup-feedback` Stimulus controller** (on the `<form>`):
  - whenever the component's `submitAttempts` LiveProp changes (every submit), scrolls
    the FIRST invalid field to the middle of the screen (clear of the sticky top bar),
    focuses it and pulses it once; summary items jump to their field the same way
    (`focusField`);
  - re-dispatches `change` on every field on submit and once Live connects
    (`live:connect`) — values typed before the lazy Live JS booted, or autofilled
    without a `change`, otherwise never reach the model and the server reports an
    „empty" field the visitor sees filled;
  - `fill` — one-tap fix buttons (suggested nickname, corrected e-mail).
- **E-mail typo hint** — `_email_help.html.twig` + `Service/SignUp/EmailTypoSuggester`: a
  mistyped mailbox domain (seznam.cy, gmial.com) is a valid address that never receives
  the verification mail, so the field offers „Nemysleli jste …@seznam.cz?".
- **Nickname help** — `_nickname_help.html.twig` states the rule up front (no spaces or
  diacritics) and, once rejected, offers a free one from `Service/SignUp/NicknameSuggester`
  (`Petr Svoboda` → `petr.svoboda`, `…2` if taken).
- **No flashes from a Live action that returns `null`** — a flash only renders on the NEXT
  full page load. Post-validation rejections use an inline field error (`reject()` in
  `InvitationForm`), which the summary picks up.
- **`signup` log channel** — `Service/SignUp/RejectedSignUpLog` records path, e-mail and
  field → messages (never passwords) on every rejection; prod streams it to stderr outside
  the fingers_crossed buffer: `docker compose logs web | grep "Sign-up rejected"`.
