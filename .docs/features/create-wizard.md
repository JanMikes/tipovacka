# Create-competition wizard

The single guided „Vytvořit soutěž" flow (S08) — a Live Component
(`Competition:CreateWizard`, `src/Twig/Components/Competition/CreateWizard.php`)
hosted by the thin `portal_competition_create` controller at `/portal/souteze/nova`
(`?zdroj={id}` preselects a source). Four steps (Základy → Pravidla → Pozvánky →
Podpora) driven by LiveProps + LiveActions (`next` / `back` / `submit`); each step
validates server-side before advancing. Submit dispatches ONE
`CreateCompetitionCommand`, composing the whole aggregate in one transaction.

## Dot stepper (reusable)

Ported into `assets/styles/app.css` (re-derived — the DS source rule was malformed):

```twig
<div class="stepper">
  {% for i in 1..N %}
    <div class="step-item">
      <div class="step-num {{ step == i ? 'active' : (step > i ? 'done' : '') }}">{{ i }}</div>
      <span class="step-label {{ step >= i ? 'is-current' : '' }}">{{ labels[i-1] }}</span>
    </div>
    {% if not loop.last %}<div class="step-bar {{ step > i ? 'done' : '' }}"></div>{% endif %}
  {% endfor %}
</div>
```

`.step-num.active` = accent fill + focus ring; `.step-num.done` = translucent accent;
`.step-bar.done` = accent connector. Pair with `.option-card` (`.selected`) for the
selectable source/monetization tiles.

## Judgment calls

- **Match checklist = LiveProp-driven, not a `data-live-ignore` island** — it must
  re-render when the source changes. Selection lives in the writable array LiveProp
  `selectedMatchIds` (multi-checkbox `data-model="norender|selectedMatchIds[]"`, so
  ticking never round-trips); the live text filter + „Vybrat vše" are pure client-side
  (`wizard_matches` Stimulus controller) and survive because ticking does not re-render.
- **Rules = two writable arrays** (`enabledRuleIds`, `rulePoints`) instead of a Symfony
  sub-form, so the preset tiles (`scoring-preset` controller) + steppers stay instant
  client-side; section metadata comes from the shared `RulePresetProvider` (also used by
  `Scoring:RuleFields`).
- **WYSIWYG PIN + link** — both are generated at mount and passed to the command, so the
  previews shown in step 3 are the values the competition is actually created with (the
  handler self-heals a rare PIN collision).
- **Atomic invitations** — the wizard handler validates invite e-mails synchronously via
  `CompetitionInviter` (strict mode → `InvalidInvitationEmails`); a bad address rolls the
  whole creation back. Delivery itself rides the post-commit `CompetitionInvitationSent`
  event, so real SMTP failures never roll back.
