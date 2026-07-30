# 12 — One name for the tip split: „Rozložení tipů" everywhere

> **Status:** TODO
> **Depends on:** nothing (but see „Files another agent owns" — do NOT touch `templates/design/styleguide.html.twig`)
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

The product owner's design mocks label the tip-split strip **„DISTRIBUCE TIPŮ"**, while the app,
`CLAUDE.md` and `.docs/DOMAIN.md` all call it **„Rozložení tipů"**. Asked to settle it, the product
owner chose **„Rozložení tipů"** — the documented vocabulary wins, the mocks do not.

But the picture was worse than „two names". A code sweep found **three** user-facing names for one
feature in the shipped app:

| Name | Where | Who sees it |
|---|---|---|
| **„Rozložení tipů"** | `Match/TipStats.html.twig` (6×) — the section heading on every match surface | every player |
| **„Lišta tipů ostatních"** | `BoostType::TipDistribution->label()` — the boost you buy to unlock it | every player in a `boosts` competition |
| **„Distribuce tipů"** | `templates/home.html.twig:311` — a marketing bullet | every visitor |

So a player reads „Rozložení tipů" on the match page, is told to buy something called „Lišta tipů
ostatních" to see it, and was sold „Distribuce tipů" on the homepage. Three names, one feature.
**This item makes it one name.** After it, „Rozložení tipů" is the only phrase the product uses.

## What changes

Every user-facing occurrence, in one commit. The **rule**: the feature is „Rozložení tipů"; the
purchasable boost that unlocks it is „**Rozložení tipů ostatních**" (the possessive tail stays — it
is what distinguishes „I see how *others* tipped" from my own tip).

| File | Before | After |
|---|---|---|
| `src/Enum/BoostType.php` — `label()` | `'Lišta tipů ostatních'` | `'Rozložení tipů ostatních'` |
| `src/Enum/BoostType.php` — `description()` for `OthersTips` | „…Obsahuje i **Lištu tipů ostatních**." | „…Obsahuje i **Rozložení tipů ostatních**." |
| `templates/home.html.twig:311` | „**Distribuce tipů** · 248 hráčů" | „**Rozložení tipů** · 248 hráčů" |
| `templates/components/Boost/Panel.html.twig:85,145` (confirm messages) | „Obsahuje i **Lištu tipů ostatních**." | „Obsahuje i **Rozložení tipů ostatních**." |
| `templates/components/Match/TipStats.html.twig:59,183` (`data-confirm-title-value`) | „Odemknout „**Lišta tipů ostatních**"" | „Odemknout „**Rozložení tipů ostatních**"" |
| `templates/admin/competition/_monetization_choices.html.twig:15` | „…vylepšení — **lišta tipů**, konkrétní tipy…" | „…vylepšení — **rozložení tipů**, konkrétní tipy…" (lower-case, it is running prose) |
| `.docs/DOMAIN.md` lines ~31, ~101, ~104, ~105 | „Lišta tipů" / „Lišta tipů ostatních" | „Rozložení tipů" / „Rozložení tipů ostatních" |
| `docs/stripe.md:57` | Boost „Lišta tipů ostatních" | Boost „Rozložení tipů ostatních" |
| tests asserting either old string | — | updated to the new string |

**Grep is the acceptance test, not this table.** Before you finish, `grep -rn` for `Lišt`, `lišt`,
`Distribuce tip` and `distribuce tip` across `src/`, `templates/`, `tests/`, `fixtures/`, `docs/`,
`CLAUDE.md` and `.docs/DOMAIN.md`, and account for **every** remaining hit — either it is renamed or
it is one of the deliberate exclusions below. List them in your commit body.

### Deliberate exclusions — do NOT rename these

1. **`templates/portal/competition/detail.html.twig:72`** — the comment „Akční **lišta** — každá
   položka…". That is the ordinary Czech word for a toolbar, describing the action bar. Unrelated.
2. **`.docs/rebuild/`** and **`.docs/redesign/`** — finished-stream archives. Item 09 set the
   precedent: falsifying the record of what a completed stream decided is worse than the staleness.
3. **`.docs/ui-nav/items/08-page-competition-detail.md`** and any other **DONE** item file — same
   reason. They record what was true when they landed.
4. **`.docs/ui-nav/BUGS.md`, `CREATE-WIZARD.md`** — historical records of completed work.
5. **`BoostType::TipDistribution`, `PricingConfig::BOOST_TIP_DISTRIBUTION`, the `tip_distribution`
   enum value, the `boost_purchases` rows, `TipStats`, `TipStatsProvider`, CSS classes
   (`.dist-bar`, `.dist-fill`, `.dist-ghost-fill`, `.dist-unlock`), test method names.** **Code and
   data are English and stay exactly as they are.** This is a Czech-copy item. Renaming the enum
   value would need a migration for zero user benefit; `CLAUDE.md` mandates Czech in the UI, English
   in code.

### Files another agent owns right now — do not touch

- **`templates/design/styleguide.html.twig`** (lines 80 + 88 say „Lišta tipů ostatních") is owned by
  **item 13** (`13-design-gallery.md`), which is running concurrently and rewrites that file
  wholesale. Item 13's spec carries this rename. **Leave the file alone entirely** — do not even fix
  the string, and do not report it as a missed hit.
- **`assets/controllers/*`** and `.docs/features/team-picker.md` are owned by the **B9** agent.
- **`assets/styles/app.css`** is owned by item 13. This item needs no CSS at all.
- **`.docs/ui-nav/PLAN.md` and `UI-MAP.md`** are owned by the **orchestrator** for this round (two
  items are in flight and both would edit the same board table). Do **not** edit them — report your
  commit sha in your final message instead and the orchestrator records it.

## Out of scope

- **No visual change.** No CSS, no layout, no new component, no changed markup structure. Text only.
- **No change to what the boost costs or unlocks.** `PricingConfig` is untouched;
  `BoostType::TipDistribution` keeps its price, its superset relationship with `OthersTips` and its
  entitlement behaviour. Read `.docs/DOMAIN.md` §Monetization and change **nothing** about the rules,
  only the words naming them.
- **Premium XOR boosts stays exactly as it is** — one `monetization` column. This item must not
  create the impression of a third state.
- **The `/_design` styleguide** — item 13.
- The English label of the *other* two boosts („Konkrétní tipy kolegů", „Měnit tip během turnaje")
  is not in question. Do not touch them.

## Implementation notes

- `BoostType::label()` is the single source for the boost's name — it is read by `Boost:Panel`, the
  confirm dialogs, the premium toggles and the admin surfaces. Changing the `match` arm fixes most
  call sites at once; the hard-coded duplicates in the table above are the ones that bypass it.
  **Consider whether the two `TipStats.html.twig` `data-confirm-title-value` strings and the two
  `Boost/Panel.html.twig` confirm messages should read from `BoostType` instead of repeating the
  literal** — if that is a small, safe change, make it, because it is why this drift happened. If it
  turns out to need plumbing a new prop through a Live Component, don't: just fix the literals and
  say so.
- `.docs/DOMAIN.md` is the **authority** on the vocabulary. Add a dated row to its decision log:
  *2026-07-30 — the tip-split feature is „Rozložení tipů" everywhere; the boost that unlocks it is
  „Rozložení tipů ostatních"; „Lišta tipů" and „Distribuce tipů" are retired* — with the rationale
  (three names for one feature made the paywall read as a different feature from the thing it
  unlocks).
- `CLAUDE.md:141` already says „Rozložení tipů" and needs no change — but check whether anything
  else in `CLAUDE.md` says „Lišta".
- Tests: `grep -rn "Lišt" tests/` and fix what breaks. `BoostFlowTest`, `TipStatsSurfacesTest` and
  the premium/boost integration tests are the likely places. **Do not weaken an assertion to make it
  pass** — if a test asserts the old name, it should assert the new one.
- Czech typography: the app uses „low-high" quotes („…"). Match the surrounding file.

## Acceptance criteria

- [ ] `grep -rn -e "Lišt" -e "lišt" -e "Distribuce tip" -e "distribuce tip" src/ templates/ tests/ fixtures/ docs/ CLAUDE.md .docs/DOMAIN.md` returns **only** the deliberate exclusions listed above (the „Akční lišta" comment) — plus `templates/design/styleguide.html.twig`, which item 13 owns.
- [ ] `/` (homepage) renders „Rozložení tipů · 248 hráčů" and the string „Distribuce tipů" appears nowhere on it.
- [ ] On a `boosts` competition where the viewer owns nothing, the `Boost:Panel` purchase row for `tip_distribution` reads „Rozložení tipů ostatních", and its confirm dialog title says „Odemknout „Rozložení tipů ostatních"".
- [ ] The locked „Rozložení tipů" paywall on a match card (`compact=true`) and on match detail (`compact=false`) both name the boost „Rozložení tipů ostatních" in their confirm dialog.
- [ ] Buying `others_tips` still says it includes the distribution bar, now under the new name.
- [ ] The admin global-competition monetization copy says „rozložení tipů".
- [ ] `.docs/DOMAIN.md` uses one name and carries the dated decision-log row.
- [ ] **Nothing about pricing, entitlement or the Premium-XOR-boosts rule changed** — `PricingConfig` diff is empty, `BoostType` enum *values* are unchanged, no migration was generated.
- [ ] No CSS file was touched.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Service
docker compose exec web vendor/bin/phpunit tests/Integration/Public
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk by subdirectory. Strip ANSI codes before
grepping PHPUnit output.

`composer quality` **cannot see a Twig error or a wrong string in a template** — it passes on a page
that throws at render time. So load these and confirm 200 + the expected copy:

- `/` — the marketing bullet
- `/souteze/{id}` of a **`boosts`** competition, as a member who owns no boost — the `Boost:Panel`
  sidebar and the locked strip inside a match card
- `/zapasy/{id}?soutez={id}` of the same competition — the full „Rozložení tipů" card, locked
- `/souteze/{id}` of a **premium** competition — the paywall must say „Rozložení tipů" too, and must
  not offer a boost purchase (Premium XOR boosts)
- `/admin/souteze` → create/edit a global competition — the monetization copy

Use `DevFixtures` (`docker compose exec web composer db:reset`, then **`docker compose restart web`**
— skipping the restart makes every page 500 on stale FrankenPHP worker connections). `.docs/FIXTURES.md`
documents which dev world has a locked vs. unlocked „Rozložení tipů" (World D is the canonical locked
one, World A/B unlocked).

## Git discipline

- **Never `git add -A`, `git add .` or `git commit -a`.** Two other agents are working in this same
  checkout right now, and a third session has been committing to this repo. Stage your own files by
  explicit path only, and verify with `git diff --cached --stat` before committing that the index
  contains nothing but them.
- One commit: `UI: one name for the tip split — „Rozložení tipů"`, with the grep audit in the body.
- Push to `main`.
- Do **not** edit `.docs/ui-nav/PLAN.md` or `UI-MAP.md` (orchestrator-owned this round). Report your
  sha instead.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
