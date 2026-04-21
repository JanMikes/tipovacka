# Confirm Modal

Styled confirmation dialog used before destructive form submissions (delete, revoke, leave, cancel, block, regenerate-invalidating-token, etc.). Replaces `window.confirm()`.

## Usage

Attach the `confirm` Stimulus controller to the `<form>` element. The controller intercepts submission, renders a `<dialog>`, and re-submits via `requestSubmit()` after the user confirms.

```twig
<form method="post" action="{{ path('portal_group_delete', {id: group.id.toRfc4122}) }}"
    data-controller="confirm"
    data-confirm-title-value="Smazat skupinu"
    data-confirm-message-value="Opravdu chceš skupinu „{{ detail.name }}“ smazat? Všichni členové přijdou o své tipy."
    data-confirm-confirm-label-value="Ano, smazat">
    <input type="hidden" name="_token" value="{{ csrf_token('group_delete_' ~ group.id.toRfc4122) }}">
    <button type="submit">Smazat skupinu</button>
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

## When to use

Use for any action that's hard or impossible to undo: deletions, revocations, leaving a group, regenerating PINs/shareable links (invalidates old ones), ending a tournament, blocking a user.

Don't use for low-stakes actions (filters, form saves that show a preview, toggles).

## Implementation

- Controller: `assets/controllers/confirm_controller.js`
- Styles: `.confirm-dialog` rules in `assets/styles/app.css` (centering + `@starting-style` transitions)
- Czech copy is inline — keep messages specific ("Smazat skupinu „{{ name }}“?"), not generic.
