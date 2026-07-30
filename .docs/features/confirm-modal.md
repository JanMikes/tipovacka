# Confirm Modal

Styled confirmation dialog used before destructive form submissions (delete, revoke, leave, cancel, block, regenerate-invalidating-token, etc.). Replaces `window.confirm()`.

## Usage

Attach the `confirm` Stimulus controller to the `<form>` element. The controller intercepts submission, renders a `<dialog>`, and re-submits via `requestSubmit()` after the user confirms.

```twig
<form method="post" action="{{ path('competition_delete', {id: competition.id.toRfc4122}) }}"
    data-controller="confirm"
    data-confirm-title-value="Smazat soutěž"
    data-confirm-message-value="Opravdu chceš soutěž „{{ detail.name }}“ smazat? Všichni členové přijdou o své tipy."
    data-confirm-confirm-label-value="Ano, smazat">
    <input type="hidden" name="_token" value="{{ csrf_token('competition_delete_' ~ competition.id.toRfc4122) }}">
    <button type="submit">Smazat soutěž</button>
</form>
```

## Values

| Value            | Default             | Notes                                                        |
|------------------|---------------------|--------------------------------------------------------------|
| `message`        | — (required)        | Body copy. Include the affected entity name + consequence.   |
| `title`          | `Potvrdit akci`     | Dialog heading.                                              |
| `confirm-label`  | `Ano, pokračovat`   | Button that submits the form.                                |
| `cancel-label`   | `Zrušit`            | Button that closes the dialog without submitting.            |
| `variant`        | `danger`            | `danger` → red confirm button. `warning` → yellow.           |

## Targets

| Target    | Notes                                                                          |
|-----------|--------------------------------------------------------------------------------|
| `fields`  | Optional. An element inside the form that is **moved into the dialog body** on first open and revealed there — for a dialog that also asks *something* (B2: „Uzamknout tipy" → Ihned / V určený čas). |
| `stretch` | Optional. A control inside the form that must exist **only while this controller is connected**. Ships `hidden`; unhidden on `connect()`, re-hidden on `disconnect()`. |

```twig
<form method="post" action="…" id="lock-tips-{{ id }}" data-controller="confirm" …>
    <input type="hidden" name="_token" value="…">
    <div data-confirm-target="fields" hidden>
        <label><input type="radio" name="lock_mode" value="now" checked> Ihned</label>
        <label><input type="radio" name="lock_mode" value="at"> V určený čas</label>
        …
    </div>
    <button type="submit">Uzamknout tipy</button>
</form>
```

Contract:

- The dialog is appended to `<body>`, i.e. **outside** the form. The controller therefore
  stamps `form="<the form's id>"` on every control inside the target (generating an id when
  the form has none), so they are still submitted with it. Nothing to do in the template.
- **The move is reversible, and it has to stay that way** (B16). `disconnect()` puts the
  element back where it came from (a comment node marks the spot), re-hides it and strips the
  `form` attributes *before* it destroys the dialog. Without that, disconnecting would delete
  the controller's only `fields` target along with the dialog, and a later reconnect — the form
  re-parented, its `data-controller` re-evaluated, an ancestor re-rendered — would find no
  target and build the plain message-only dialog. That dialog is **byte-identical to the no-JS
  fallback**, so the failure is invisible: it looks exactly like correct degradation.
- Start the element `hidden`; the controller unhides it when it adopts it. Without JS no
  dialog is ever shown, so **the field defaults must make the plain submit do the right,
  least surprising thing** (for the lock modal: „Ihned", i.e. the pre-B2 behaviour).
- Show/hide inside the block with CSS (`:has(input:checked)`), not with a second controller.
- A flatpickr `datepicker` inside the dialog must set `data-datepicker-inline-value="true"` —
  a floating calendar is painted under the dialog's top layer, and inside a vertically
  centred dialog it has nowhere to go anyway. In flow the dialog grows around it; the
  `has-fields` class caps the dialog's height so it scrolls instead of overflowing the
  viewport. Do **not** reach for flatpickr's own `static: true`: it re-parents the controller
  element and Stimulus then unmatch→matches forever, hanging the page.
- Any `<form>` hosting a flatpickr needs `novalidate`: the calendar's internal hour/minute
  number inputs (`step="5"`) are form controls and would silently block submission.

### `stretch` — a target that is only safe *because* the dialog opens (B27)

```twig
<form method="post" action="…" data-controller="confirm" …>
    <input type="hidden" name="_token" value="…">
    <button type="submit" class="dist-unlock card-raise">Odemknout za 10 kr.</button>
    <button type="submit" data-confirm-target="stretch" class="card-stretch" hidden>Odemknout …</button>
</form>
```

The two paywall cards on match detail make the **whole card** the purchase trigger by
painting a submit over it (item 18's `.card-stretch`; `.dist-buy > .card-stretch` does the
positioning). A card-sized control that spends credits is only acceptable while the confirm
dialog is guaranteed, so it is rendered `hidden` and the controller enables it — **the big
target is the enhancement and the small explicit button is the floor**, the inverse of the
usual direction. JavaScript-off support is deferred (`PLAN.md` decision 0), but a controller
that *fails to connect* renders the identical page, and this repo has hit that twice (B16,
B25). Two rules follow:

- Never declare `display` on such a control in CSS — an author `display` out-specifies
  `[hidden]` and the oversized target would go live on a page whose JS never ran.
- Nothing interactive may be nested inside it (items 18/21). The small CTA stays reachable
  as a **sibling**, raised with `.card-raise`.

## When to use

Use for any action that's hard or impossible to undo: deletions, revocations, leaving a competition, regenerating PINs/shareable links (invalidates old ones), ending a tournament, blocking a user.

Don't use for low-stakes actions (filters, form saves that show a preview, toggles).

## Implementation

- Controller: `assets/controllers/confirm_controller.js`
- Styles: `.confirm-dialog` rules in `assets/styles/app.css` (centering + `@starting-style`
  transitions, plus the B2 block for `has-fields`)
- Czech copy is inline — keep messages specific ("Smazat soutěž „{{ name }}“?"), not generic.
