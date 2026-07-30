# Item 27 — the invitation landing says it once, not three times

**Status:** TODO
**Filed:** 2026-07-30, from the product owner, on
`https://wtips.cz/souteze/pozvanka/8dbe3879…` (a **shareable-link** invitation).

Screenshot: `.docs/ui-nav/screenshots/item27-invitation-duplicate-copy.png`

## The instruction (verbatim)

> „there is duplicite information „Za pár kliků se připojíte do soutěže Lipina 26/27 a začínáte
> tipovat." and then the card. remove the text above card it is useless
>
> Original text:
> ```
> Chystáš se připojit do soutěže Lipina 26/27 ve zdroji zápasů 3. MSFL sezóna 26/27 od Jammal .
> Zapamatovali jsme si to — po registraci (i po přihlášení) tě do soutěže přidáme, jakmile ověříš e-mail.
> ```
>
> expected text without useless info:
> `Chystáš se připot do soutěže Lipina 26/27`

(„připot" is a typo in the instruction — the correct Czech is **„Chystáš se připojit do soutěže"**,
which is what the template already says.)

## What is there today (established from the code — do not re-derive)

All of it in `templates/invitation/landing.html.twig`, which `{% extends 'auth/_layout.html.twig' %}`:

- **lines 7–13** — the `{% block auth_panel_tagline %}` override, i.e. the duplicated sentence:
  ```twig
  {% if competition_name %}
      Za pár kliků se připojíte do soutěže {{ competition_name }} a začínáte tipovat.
  {% else %}
      Přijměte pozvánku a začněte tipovat s partičkou.
  {% endif %}
  ```
  The layout renders it at `auth/_layout.html.twig:18–21` as
  `{% set tagline %}{% block auth_panel_tagline %}{% endblock %}{% endset %}{% if tagline|trim %}<p …>`
  — **so removing the override leaves no empty element behind.** The block is a shared slot used by
  six other auth templates (`password_reset*`, `verify_pending`, `verify_error`, `join_by_pin`);
  **removing this template's override affects only this page.**
- **lines ~101–110** — the card's first `<p>`: „Chystáš se připojit do soutěže **X**" plus a
  `{% if context.matchSourceName %}` clause („ve zdroji zápasů **Y**") and a
  `{% if context.inviterNickname %}` clause („od **Z**"), then `.`. Note the screenshot shows
  **„od Jammal ."** — a stray space before the full stop, because the period sits on its own line
  after the two `{% if %}` blocks. Removing the clauses removes that defect too; keep the period
  attached to the soutěž name.
- **lines ~111–118** — a second `<p>`, branching on the invitation kind:
  - `kind == 'email'` → „Dokonči registraci nebo se přihlas a rovnou tě do soutěže přidáme."
  - otherwise (shareable link / PIN) → „Zapamatovali jsme si to — po registraci (i po přihlášení) tě
    do soutěže přidáme, jakmile ověříš e-mail."

  The pasted URL is the link kind, which is why the screenshot shows the second variant.

## What to do

1. **Delete the `auth_panel_tagline` override entirely** — both branches, not just the one with the
   soutěž name.

   **Orchestrator's judgement on the `{% else %}` branch, cheap to reverse:** it is not duplicated
   (there is no soutěž name on a dead token), so it could have been kept. It is deleted anyway
   because that branch renders on the `invalid` / `expired` / `revoked` steps, where „Přijměte
   pozvánku a začněte tipovat s partičkou." sits directly above a card reading „Odkaz je neplatný
   nebo už neexistuje." — inviting the visitor to accept an invitation that does not work. That is
   worse than useless. If the product owner wants a tagline back on the error steps, it should be one
   that matches them, and that is a new decision.

2. **Shorten the card's first `<p>` to exactly „Chystáš se připojit do soutěže *X*."** — drop the
   „ve zdroji zápasů" and „od" clauses and their `{% if %}` wrappers. Keep the `<strong
   class="font-semibold text-white">` emphasis on the soutěž name; keep the period attached.

3. **Delete the second `<p>`** (both kind branches), per the „expected text".

   ⚠️ **Flagged risk — the product owner has been told, and this is their call.** For the link and
   PIN kinds that sentence is the **only** place the app explains that the join completes *after*
   e-mail verification. That is real, non-obvious behaviour: a link or a PIN never verifies an
   account, so the visitor signs up, lands in the verification airlock (B1), and is added to the
   soutěž only once they click the mail — see `.docs/features/join-intent.md` and B15 („an invite-link
   sign-up loses the competition it was invited to"), which exists because this used to fail
   silently. Removing the sentence does not change the behaviour, only the explanation of it.
   **Implement the removal as asked.** Do not reintroduce the text elsewhere on your own initiative;
   note in your report that restoring it is one line if the owner wants it back.

4. **`context.matchSourceName` and `context.inviterNickname` become unused in this template.** Leave
   the DTO and its other consumers alone — `grep` first and say what else uses them. Twig will not
   warn about an unused variable, so this is a read-then-decide, not a delete-on-sight.

5. **`tests/Integration/Invitation/InviteFunnelJourneyTest.php` asserts this copy.** Update it to the
   new text, and keep an assertion that the soutěž name is still named — that is the one thing on
   this page that must never disappear.

## What must NOT change

- **The behaviour of any of the seven steps.** This template branches into `invalid`, `expired`,
  `revoked`, `accepted`, `match_source_completed`, `email_mismatch` and the active state. Only the
  active state's copy and the tagline change; the other six render the same markup as before.
- **`<twig:Auth:InvitationForm>`** and the „Mám už účet a chci se přihlásit jinak" link below it.
- **The join-intent mechanics** (`PendingJoinStore`, `LoginSubscriber`, `User.pendingJoin*`). This is
  copy only: nothing about who joins when may change, and an e-mail invitation must still be the only
  kind that verifies an account.
- **Both routes that render this template** keep working: `competition_join_by_link`
  (`/souteze/pozvanka/{token}`) and `competition_accept_invitation` (`/pozvanka/{token}`). Both are
  **public** — check them logged out, which is the state a real invitee is in.
- The six other templates using `auth_panel_tagline` are untouched.
- Czech in the UI, English in code and comments. No „sázka" in any form.

## Acceptance criteria

1. On `/souteze/pozvanka/{valid token}`, logged out: **no** sentence above the card, and the card's
   notice reads exactly „Chystáš se připojit do soutěže **<name>**." and nothing else.
2. No empty `<p>` or stray wrapper is left where the tagline was.
3. `/pozvanka/{valid e-mail invitation token}` shows the same single sentence.
4. All seven steps still render 200 with their own copy intact.
5. `composer quality` clean.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load the page for every reachable step**, logged out. `composer quality` does not catch a Twig
  error — it passes on a page that throws at render time, and this template is almost entirely
  branches. `DevFixtures` seeds a pending e-mail invitation and a shareable link on World B
  („Sousedský pohár") — see `.docs/FIXTURES.md` for the tokens, and prefer them over hand-made data.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Invitation` and
  `tests/Integration/Auth`. **Never run `phpunit tests/` whole — it OOMs (exit 137).** Strip ANSI
  before grepping.
- Confirm by **measuring** that the card did not shift into a worse position now that ~2 lines came
  off the page — the card is vertically centred by `auth/_layout.html.twig`, so check at 1440 px and
  320 px that nothing is clipped and `scrollWidth == clientWidth`.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.

## Assumptions made

- **The notice's inner wrapper collapsed into the one `<p>` that survived.** The markup was
  `<div class="text-sm leading-relaxed text-white/70">` holding two `<p>`s; with one paragraph left
  the `<div>` was pure nesting, so the classes moved onto the `<p>`. Measured identical box model
  (Preflight zeroes `<p>` margins), so this is markup tidying, not a layout change.
- **`context.matchSourceName` / `context.inviterNickname` were left alone**, as instructed
  (read-then-decide). Both are still read by `InvitationContextResolver` and, under the same names,
  by ~20 unrelated query DTOs/templates; `matchSourceName` is non-nullable on `InvitationContext`,
  so its `{% if %}` was dead weight anyway. Nothing was deleted from the DTO.
- **The e-mail-kind sentence went too** („Dokonči registraci nebo se přihlas a rovnou tě do soutěže
  přidáme."), per step 3's „both kind branches" — the „expected text" leaves one sentence.

### The flagged risk, answered

The link/PIN explanation is **not** actually lost on the sign-up path — it is only later, at the
moment it becomes actionable. `Twig/Components/Auth/InvitationForm.php:185` flashes
„Registrace proběhla úspěšně. Potvrď e-mail a rovnou tě přidáme do soutěže *X*." on the redirect to
`/overeni-ceka` (and `:225` flashes the sign-in twin, „Nejprve si ověř svou e-mailovou adresu — pak
tě rovnou přidáme do soutěže *X*."). Both name the soutěž and render in `base.html.twig`'s flash
strip. What is gone is the *pre*-registration promise; the *post*-registration one stands.
