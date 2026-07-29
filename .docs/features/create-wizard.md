# Create-competition wizard

The single guided „Vytvořit soutěž" flow (S08) — a Live Component
(`Competition:CreateWizard`, `src/Twig/Components/Competition/CreateWizard.php`)
hosted by the thin `competition_create` controller at `/souteze/nova`
(`?zdroj={id}` preselects a source). Four steps (Základy → Pravidla → Pozvánky →
Podpora) driven by LiveProps + LiveActions (`next` / `back` / `submit`); each step
validates server-side before advancing. Submit dispatches ONE
`CreateCompetitionCommand`, composing the whole aggregate in one transaction.

## Admin-only global branch

Step 1 shows a „Typ soutěže" toggle **only to `ROLE_ADMIN`** (`isAdmin` getter). Picking
„Globální soutěž" (the `useGlobalKind` action — re-checks `ROLE_ADMIN`, so a non-admin
can never enter the mode; `isGlobalKind` is a non-writable LiveProp) turns the same wizard
into the admin global-competition creator:

- shows an **„Vstupné (kredity)"** field, restricts the source list to **curated only**
  (private/from-scratch is hidden — a global competition must sit on a curated source),
  and forces „all matches" (the subset picker + playoff toggle are hidden);
- keeps its **own step-4 copy** („Monetizace soutěže", with the credit-balance strip and
  the per-boost price list). The W4 „Pozvete nás na pivo?" rewrite applies to the private
  branch only, so step 4 is branched global vs private;
- **skips the „Pozvánky" step** entirely — PIN / shareable link / e-mail invites are all
  invalid for a global competition (joined only via the entry-fee flow). The step flow is
  driven by `stepSequence` (`[1,2,4]` global, `[1,2,3,4]` private); the dot stepper,
  „Krok X ze Y" and `next`/`back` all derive from it;
- keeps the **rules** step and offers monetization **none | premium | boosts** (private
  wizard offers only premium/boosts; global defaults to `none`);
- submit branches to the existing, tested **`CreateGlobalCompetitionCommand`** /
  `GlobalCompetitionComposer` path (isGlobal, mode All, owner = admin, entry fee, rule
  config) instead of `CreateCompetitionCommand` — one domain path, not a duplicate.

The dedicated admin page (`admin_global_competition_create`) and the „Rovnou vytvořit
globální soutěž" checkbox on curated-source creation still exist and hit the same command.

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

## Match scope step (All / Podle týmu / Vybrané zápasy)

Step 1's „Zápasy soutěže" offers three selection modes (radios bound to the
`selectionMode` LiveProp): **Všechny zápasy** (`all`), **Podle týmu** (`teams`) and
**Vybrat jen některé zápasy** (`subset`, private only). Global (admin) mode hides
`subset` — a global competition is `all` or `teams`. Step 1 is *only* the match-scope
choice; the **playoff toggle lives on step 2** (see below).

- **Podle týmu** reveals a multi-team **`team-filter`** tom-select island (see
  [team-picker](team-picker.md)) fed by the source's teams. Picks are mirrored as a
  comma-joined UUID list into the `selectedTeamIdsCsv` LiveProp; `filterTeamUuids()`
  intersects them with the source's actual teams (drops a stale selection after a source
  switch), and `submit()`/`submitGlobal()` send them as `filterTeamIds` on
  `CreateCompetitionCommand` / `CreateGlobalCompetitionCommand`. The competition then
  dynamically includes every source match where a filter team plays — later-added team
  matches (playoff!) auto-join. Editable after creation on the same manage surface as
  `subset` (`competition_match_selection`).

## Rules step — presets and copy (W1)

Three preset tiles, driven by `RulePresetProvider::presets()` + the `scoring-preset`
Stimulus controller:

- **Standardní** — the four `base` rules at default points. **Pre-selected**, because it is
  exactly what `mount()` enables.
- **Maxi** — base + `period_exact` + `period_away_goals` + `period_home_goals` +
  `overtime_exact`.
- **Vlastní (připravujeme)** — rendered but `disabled` (`.variant-card[disabled]` already
  exists). Nothing is lost: the per-rule fields below stay editable at all times.

`Scoring:RuleFields` (the post-creation rules screen) keeps its own Standardní /
Standard + střelec / Vlastní row — it *is* the custom editor, so disabling „Vlastní" there
would be wrong.

**Per-rule copy has ONE home: `RulePresetProvider::RULE_COPY`.** Both templates render
`this.ruleCopy`; the map's key order is also the rendering order inside a category
(registration order is alphabetical-by-class, which would scatter the period rules).
The wizard still *shadows* the section headings with a local, sport-interpolated map that
phrases them as questions („Tipovat také poločasy zápasu?", „Chcete tipovat také střelce
utkání?", „Tipovat celkové skóre po prodloužení či penaltách?").

Four `periods` rules exist: exact / home goals / away goals / tendency. The two goal rules
are **not** exclusive with `period_exact` (mirroring the whole-match trio); only
`period_tendency` excludes an exact period. All four are disabled by default, and
`CompetitionGuessFeatures::periodTips` is true if **any** of them is enabled.

PP and PEN are deliberately **not** split — one combined „Celkové skóre po prodloužení /
penaltách" entry backed by the single `overtime_exact` rule and the single overtime score
pair on `SportMatch`/`Guess`.

## Playoff toggle lives on step 2 — „Dohrávat turnaj?"

„Dohrávat turnaj?" (`includePlayoff`) sits at the bottom of step 2 („Pravidla"),
below the scoring fields and **outside** the `scoring-preset` controller — it is not a
scoring rule. It renders only for a **private** competition with a source and match scope
**`all`**, because that is the only mode in which the flag does anything:
`CompetitionMatchProvider` ignores it for `subset` (an explicitly ticked playoff match
counts) and for `teams` (playoff always in), and `CreateCompetitionHandler` forces it to
`true` outside mode `all`.

This ONE checkbox is the whole answer to „dohrávat turnaj" — never add a second control
writing `includePlayoff`, and never widen the visibility condition (it would promise a
choice the provider does not honour). A test asserts the markup contains exactly one
`data-model="includePlayoff"`.

## Step 4 — „Pozvete nás na pivo?" (private) vs „Monetizace soutěže" (global)

The private branch offers exactly two choices, mapping onto the single `monetization`
column (Premium XOR boosts — never a third state):

- **Férová soutěž** → `CompetitionMonetization::Premium` — the competition-wide option, so
  nobody can buy an individual edge. Carries the „Doporučujeme" badge and is the
  **pre-selected default** (both the LiveProp initialiser and `usePrivateKind()`).
- **Volná volba Premium** → `CompetitionMonetization::Boosts` — each player decides for
  themselves.

The private step shows no prices and no credit balance: nothing is charged at creation, so
the CTA is always „Vytvořit soutěž". `None` is not offered privately — it is reachable only
via the admin global branch, which keeps its original copy, price list and balance strip.

## Judgment calls

- **Match checklist = LiveProp-driven, not a `data-live-ignore` island** — it must
  re-render when the source changes. Selection lives in the writable array LiveProp
  `selectedMatchIds` (multi-checkbox `data-model="norender|selectedMatchIds[]"`, so
  ticking never round-trips); the live text filter + „Vybrat vše" are pure client-side
  (`wizard_matches` Stimulus controller) and survive because ticking does not re-render.
- **Team filter picker IS a `data-live-ignore` island** (unlike the match checklist) —
  tom-select can't live in a re-rendering region. The currently-picked teams are rendered
  as `<option selected>` server-side (`filterTeamOptions` getter) so the chips survive step
  navigation from the DOM, not from bare ids; the controller re-syncs the hidden
  `data-model="norender|selectedTeamIdsCsv"` input on mount, which also clears a stale id
  list if the picker remounts with an empty selection.
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
