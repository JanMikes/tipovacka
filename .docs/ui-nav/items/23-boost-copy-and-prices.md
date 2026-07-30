# Item 23 — one canonical copy and price per booster, everywhere

**Status:** TODO
**Filed:** 2026-07-30, from the product owner.

## The instruction (verbatim)

> There are 3 boosters and anywhere used (in the panel, in the locked panel under match etc), always
> use exactly this copy:
>
> Booster: `Jak tipují ostatní?`
> Text: `Odemkněte procentuální rozložení tipů 1 / X / 2 ostatních hráčů ve vaší soutěži. Konkrétní
> tipy zůstávají skryté.`
> Credits: 15
>
> Booster: `Přesné tipy soupeřů`
> Text: `Chcete vědět, jak tipuje váš soupeř? Odemkněte si přesné tipy ostatních hráčů ve vaší
> soutěži.`
> Credits: 35
>
> Booster: `Počkejte si na sestavy`
> Text: `Chcete si počkat na soupisky? Odemkněte si možnost upravit své tipy až 1 hodinu před
> začátkem zápasu.`
> Credits: 50
>
> In the panel of the competition with boosters change there and then at every CTA/match detail, list
> of matches -> everywhere always use this copy

**This copy is exact and is not to be adapted, shortened, re-cased or re-punctuated** — including the
question marks and „Odemkněte" / „Odemkněte si" as written. Where a surface has room for only a
name, use the booster name verbatim; where it has room for the sentence, use the sentence verbatim.

## The mapping (established from the code — do not re-derive)

| Booster copy | `BoostType` case | `PricingConfig` constant | Price today → wanted |
|---|---|---|---|
| „Jak tipují ostatní?" | `TipDistribution` (`tip_distribution`) | `BOOST_TIP_DISTRIBUTION` | **10 → 15** |
| „Přesné tipy soupeřů" | `OthersTips` (`others_tips`) | `BOOST_OTHERS_TIPS` | **20 → 35** |
| „Počkejte si na sestavy" | `TipChange` (`tip_change`) | `BOOST_TIP_CHANGE` | **40 → 50** |

`PREMIUM_PER_PLAYER` (10) is **not** part of this item and does not change.

**The three prices are real changes, not a restatement of the current values.** They are single
constants in `PricingConfig` and every surface derives from them (`BoostType::price()`,
`Twig/PricingExtension` for `/cenik`, `CreateWizard`, `DesignStyleguideController`, and
`DevFixtures::DEV_USER_CREDIT_BALANCE`, which is computed as
`BOOST_OTHERS_TIPS + BOOST_TIP_DISTRIBUTION + 5`). So the code change is three integers — but
**absolute-number assertions in tests and any doc that quotes a price will break**, and that is the
bulk of the work. Find them all.

## Product-owner decisions (2026-07-30) — these reverse an earlier call, deliberately

### 1. The new copy replaces `BoostType::label()` itself — literally everywhere

Round 2 (`295b47c`) introduced these three headlines in `Boost/Panel.html.twig` **only as headlines
above** the canonical `BoostType::label()` names, and the component's own comment says so: „a label
above the product, never a rename". The canonical names were „Rozložení tipů ostatních" /
„Konkrétní tipy kolegů" / „Měnit tip během turnaje".

**That distinction is now abolished.** `BoostType::label()` returns the new copy, so there is exactly
ONE name per booster and no surface can drift again. This includes the surfaces round 2 deliberately
left on the canonical name:

- the confirm dialog („Koupit „Jak tipují ostatní?"" / „Odemknout „…""),
- the purchase CTA („Odemknout „Jak tipují ostatní?" za 15 kr."),
- the locked paywall under a match (`Match:TipStats`),
- the boost-intro modal (`portal/competition/_boost_intro_modal.html.twig`),
- the create-competition wizard (`Twig/Components/Competition/CreateWizard.php`),
- `/cenik`,
- **the credit ledger** (`Query/ListAllCreditTransactions`, `AdminCreditTransactionItem`) — a past
  purchase's row now reads „Jak tipují ostatní?" too,
- the refund notification (`Event/NotifyBoostRefundedHandler`),
- `Exception/BoostNotAvailable` messages,
- the panel's superset note (today: „Obsahuje i Rozložení tipů ostatních" — it must name the
  included booster by its new name).

Delete the now-redundant headline map in `Boost/Panel.html.twig` (lines ~32–34) rather than leaving
it duplicating `label()`, and delete the comment that describes the abolished distinction.

`BoostType::description()` becomes the three „Text:" sentences above, verbatim. Check whether the
panel and the intro modal render `description()` or their own prose — **any hand-written boost prose
anywhere is replaced by `description()`**; that is what „everywhere always use this copy" means.

**Enum values, CSS classes, constants and every other identifier stay English and unchanged** —
`tip_distribution`, `others_tips`, `tip_change`, `PricingConfig::BOOST_*`, `.dist-*`,
`TipStats`/`TipStatsProvider`. **No migration.** If you think one is needed, stop and say why.

### 2. The unlocked section is renamed to match: „Jak tipují ostatní?"

Item 12 (`c3c052e`) made „Rozložení tipů" the one name for the tip split, and PLAN.md decision 1
records it. The product owner has now chosen to rename the **section** as well, so the booster and
the thing it unlocks share one name. Both user-visible occurrences live in
`templates/components/Match/TipStats.html.twig`:

- **line ~55** — `<h2 class="eyebrow">Rozložení tipů</h2>`, the full card (`compact=false`) on match
  detail → „Jak tipují ostatní?"
- **line ~174** — `<p class="tip-stats-eyebrow">Rozložení tipů</p>`, the compact strip inside every
  match card → „Jak tipují ostatní?" (the product owner explicitly chose the same string here, not a
  terser one, having been shown the 288-px phone case)

Also update, as user-visible copy: `templates/home.html.twig:342` („Rozložení tipů · 248 hráčů" in
the hero mock) and the `/_design` gallery's own captions/labels (`templates/design/styleguide.html.twig`
around lines 378, 466, 471, 492–499, 718–729 — read them; some are prose about the component and
some are the component's sample copy).

**Code comments and docblocks that say „Rozložení tipů" are documentation, not UI.** Update them
where they would now mislead a reader (they name a heading that no longer exists), but this is not a
mechanical find-and-replace over comments — read each one.

### Consequences you must record, not just implement

`.docs/DOMAIN.md` — the §Monetization vocabulary and the **2026-07-30 decision-log row** that
established „Rozložení tipů" and „Rozložení tipů ostatních" are now partly superseded. Do **not**
rewrite history: add a **new dated decision-log row** for 2026-07-30 stating the rename (booster
names, section name, the three prices), and update the §Monetization prose so the current rules are
correct. `CLAUDE.md` mentions „Rozložení tipů" in the `TipStatsProvider` bullet — update it.

Anything that claims a price (`.docs/DOMAIN.md`, `.docs/features/*.md`, `/cenik`'s template if it
hard-codes anything) must agree with 15 / 35 / 50.

## What must NOT change

- **Premium XOR boosts** — one `monetization` column, never both funding models at once.
- **The superset rule**: `OthersTips` entitles its buyer to the distribution bar too, and the
  `TipDistribution` offer is hidden once `OthersTips` is owned. Copy changes must not disturb it.
- **Managers and admins get no free entitlement pass** (`CompetitionEntitlements`).
- **`TipStatsProvider` stays batched per page**, never one query per row.
- **A boost cannot be bought for a fully-over competition** (B6) — that message stays.
- Prices come only from `PricingConfig`. **Do not introduce a literal 15 / 35 / 50 anywhere.**
- Czech in the UI, English in code and identifiers. **Never „sázka" or any of its forms**, no
  gambling framing, no payouts.
- **Files owned by other agents this round — do not touch:** `assets/styles/app.css`,
  `.docs/ui-nav/PLAN.md`, `.docs/ui-nav/BUGS.md`, `fixtures/DevFixtures.php`, `.docs/FIXTURES.md`.
  If you conclude one of them genuinely must change (e.g. a seeded wallet can no longer afford a
  booster at the new price), **stop and report it** — do not edit it. The orchestrator will
  sequence it.
  You should not need any CSS: this is copy. If a longer string breaks a layout, report the geometry
  rather than patching `app.css`.

## Acceptance criteria

1. `BoostType::label()` and `::description()` return the six strings above, byte-for-byte.
2. `PricingConfig::BOOST_TIP_DISTRIBUTION = 15`, `BOOST_OTHERS_TIPS = 35`, `BOOST_TIP_CHANGE = 50`.
3. `grep` finds **no** occurrence of „Rozložení tipů ostatních", „Konkrétní tipy kolegů" or
   „Měnit tip během turnaje" in `templates/` or `src/` (outside a decision-log entry recording the
   rename).
4. Every one of these renders the new name and price: the boost panel (owned + unowned rows), the
   inline paywall, the locked `Match:TipStats` under a match and inside a match card, the confirm
   dialog, the boost-intro modal, the create wizard's boost list, `/cenik`, `/kredity`'s ledger,
   `/admin/kredity/transakce`, and the refund notification.
5. The section heading reads „Jak tipují ostatní?" on match detail **and** in the compact strip
   inside a match card.
6. `/_design` renders the new copy in half A and stays inert —
   `DesignStyleguideFlowTest::testNothingOnThePageCanAct` green.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load every page in criterion 4.** `composer quality` passes on a template that throws at render
  time, and most of these are Twig-only changes.
- Integration chunks that will feel this: `tests/Integration/Portal/Competition` (`BoostFlowTest`,
  `CompetitionDetailPassTest`), `tests/Integration/Portal` (`TipStatsSurfacesTest`,
  `PremiumTeaserFlowTest`), `tests/Integration/Command` (purchase/refund handlers),
  `tests/Integration/Query` (credit transaction lists), `tests/Integration/Admin`,
  `tests/Integration/DesignStyleguideFlowTest`, plus `tests/Unit`. **Never run `phpunit tests/`
  whole — it OOMs (exit 137).** Chunk by subdirectory; strip ANSI before grepping.
- **Expect the price change to break balance assertions**, not just copy assertions. A test that
  bought a booster for 20 and asserted a remaining balance now sees 35. Fix them to derive from
  `PricingConfig` where that is the honest fix, rather than hard-coding a new number.
- **Check nothing overflows** now that the longest booster name is a question and the descriptions
  are two sentences: the panel rows, the confirm dialog title, the compact strip label inside a
  288-px card, and the ledger's table cell. Measure — element box `width` vs `scrollWidth` for
  truncation (a `Range` measures text ink, and ink is not clipped by `overflow`); bounding-box
  intersection across painted leaves for overlap. Report numbers.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. The index is shared mutable state and verifying it proves nothing. Push to
`main`. Do not update the status board — report your sha and the orchestrator records it.

---

## Assumptions made

Decisions the item file did not answer, taken the conservative way and recorded here.

1. **The compact strip's label was renamed in all FIVE of its states, not the two lines the
   item names.** The item points at `TipStats.html.twig` lines ~55 and ~174; the file actually
   held **six** user-visible copies of „Rozložení tipů" — the `h2.eyebrow` of the full card plus
   **five** `.tip-stats-eyebrow` spans (unlocked, locked-buyable, locked-no-credits, premium,
   „zobrazí se po odehrání"). Renaming only the two the item cites would have left the *locked*
   strip — the one a non-paying player actually sees — advertising the retired name. All six now
   read „Jak tipují ostatní?".

2. **`BoostType::description()` is verbatim; the panel keeps ONE dynamic branch.** The canonical
   `TipChange` sentence promises „až 1 hodinu před začátkem zápasu", but the window is the
   competition's own `tipChangeOffsetMinutes` (default 60, editable on the prémium settings page
   and consumed by `EffectiveTipDeadlineResolver` for the BOOST path too). So `description()`
   returns the string byte-for-byte (criterion 1), and `Boost/Panel.html.twig` renders it unless
   the offset is **not** 60, in which case it renders the same sentence with the real offset
   substituted. Replacing a false promise with a canonical one is not what „one copy everywhere"
   is for.

3. **The `Match:TipStats` confirm MESSAGE is `description()` + the transactional facts.** It used
   to hand-write „Uvidíte anonymní rozložení tipů (1 / X / 2) u všech zápasů v soutěži „X"." The
   descriptive half is now `description()` verbatim; the scope („Platí pro všechny zápasy v
   soutěži „X"") and the price stay, because a booster is bought per competition and the enum
   cannot know which one.

4. **Names that can end in „?" are always quoted in generated sentences.** „Jak tipují ostatní?"
   inside a sentence otherwise swallows the following clause. So `BoostNotAvailable::becauseSupersededByOthersTips`
   became „Vylepšení „%s“ je už součástí vašeho vylepšení „%s“.", the panel's superset note became
   „Obsahuje i „%s“." and the refund notification title became „Vylepšení vráceno: „%s“".

5. **The prémium toggles carry the booster names** (`PremiumSettingsFormType`). They switch on
   exactly these three boosters for everyone, and acceptance criterion 3 forbids the old names
   anywhere in `src/`. The help texts (what changes for the MEMBERS) are unchanged.

6. **`/kredity`'s own ledger now names the booster.** Criterion 4 lists it, but the row rendered
   only „Nákup vylepšení" — `CreditTransactionItem::$boostType` was a `?string` nobody read. It is
   now a `?BoostType` and the row renders „· {{ label }}", mirroring `/admin/kredity/transakce`.

7. **Boost prose OUTSIDE the boost surfaces was updated too, from `BoostType::label()`:** the
   admin monetization picker (`_monetization_choices.html.twig`, which listed „rozložení tipů,
   konkrétní tipy nebo změna tipu během turnaje") and the create wizard's PRÉMIUM card bullets
   (which said „Možnost měnit tip během turnaje"). Both are sales copy for the same three products.

8. **Left alone deliberately:** the „Zobrazit rozložení tipů" jump CTA in the owned-boost panel row
   and the lock-overlay teaser („Uvidíš, jak tipuje konkurence" + „Detailní rozpad tipů…"). The
   first is a navigation verb, not a product name; the second varies per monetization
   (boosts / prémium / none), so it cannot be one canonical sentence. „rozložení tipů" survives as
   the lowercase descriptive phrase — it is inside the product owner's own sentence.

   > **REVERSED 2026-07-30 by the product owner**, who found „Uvidíš, jak tipují 4 hráči" still on
   > `/souteze/{id}` and read it as exactly the drift this item was filed to end. The teaser half of
   > the assumption does not hold: those strings varied by **monetization**, not by booster, so the
   > varying part belongs in its own constants (`BoostType::LOCKED_PREMIUM_*` / `LOCKED_OVER_*` /
   > `LOCKED_AFTER_MATCH_*`) and the invariant part was `description()` all along. Every lock
   > overlay now renders `label()` + `description()`; the veiled „Pořadí za zápas" card on match
   > detail gained the booster's name, which it had never carried. See the 2026-07-30 decision-log
   > row in `.docs/DOMAIN.md`. The jump-CTA half of this assumption stands.
