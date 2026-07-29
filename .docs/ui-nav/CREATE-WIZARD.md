# Create-competition wizard + copy backlog

Reported by the product owner on 2026-07-29, against
`/souteze/nova?zdroj=…` (`Competition:CreateWizard`, see `.docs/features/create-wizard.md`).

Legend: `TODO` · `IN PROGRESS` · `DONE` · `BLOCKED`

| # | Title | Status | Commit |
|---|-------|--------|--------|
| W1 | Rules step: Standardní / Maxi / Vlastní presets + renamed rules | TODO | — |
| W2 | Playoff option moves from step 1 to step 2 | DONE | `f69937d` |
| W3 | Step 3: drop the duplicated hint, leave only „Přeskočit" | DONE | `f69937d` |
| W4 | Step 4: new Premium copy („Pozvete nás na pivo?") | DONE | `f69937d` |
| W5 | No success flash after sign-up | DONE | `7b3f010` |
| W6 | Step 1 „Zápasy soutěže" is missing the „Podle týmu" mode | DONE | `f69937d` (was already shipped — stale report, now pinned by tests) |

---

## W1 — Rules step: presets and renamed rules

Three presets. **„Vlastní" is shown but disabled — label it „Vlastní (připravujeme)".**

### Standardní

Contains exactly these four scoring rules. Two are renamed — the *rule identity stays the same*, only
its user-facing title and description change:

| Current title | New title | New description |
|---|---|---|
| DOBRÝ TIP SKÓRE HOSTŮ | **Tip hosté** | Správný tip hostujícího týmu |
| DOBRÝ TIP SKÓRE DOMÁCÍCH | **Tip domácí** | Správný tip domácího týmu |
| DOBRÝ TIP VÝSLEDKU | *(unchanged)* | *(unchanged)* |
| PŘESNÝ TIP VÝSLEDKU | **Přesný tip výsledku** | bonus za obě uhodnutá skóre |

Plus three optional yes/no radio groups:
- „Chcete tipovat také střelce utkání?" — Ano / Ne
- „Budete hrát také fantasy?" — Ano / Ne
- „Dohrávat turnaj?" — Ano / Ne

### Maxi

Everything in Standardní, **plus**:

- **Tipování části zápasu** — points for: Přesný tip výsledku · skóre hostů · skóre domácích.
  (The existing „TIPOVAT TAKÉ POLOČASY ZÁPASU?" block — „Přesný tip části zápasu" / „Tendence části
  zápasu" — is the current implementation of this; see `screenshots/`. Reconcile the two rather than
  building a parallel mechanism.)
- **Výsledek po základní hrací době**
- **Celkové skóre po PP** (prodloužení)
- **Celkové skóre po PEN** (penalty)

Plus the same three optional radio groups as Standardní.

### Vlastní

Visible, disabled, labelled „Vlastní (připravujeme)".

**Open question for the product owner — do not guess:** whether the three optional radio groups and
the Maxi extras map onto existing `#[AsRule]` rules / `CompetitionRuleConfiguration` fields, or need
new domain concepts (fantasy and „dohrávat turnaj" in particular look new). Inventory
`src/Rule/` first and report the gap before implementing.

### Product-owner decisions (2026-07-29)

Answers to the recon below. **These are settled — implement them as written.**

1. **„Budete hrát také fantasy?" — DEFERRED.** Product owner: *„Fantasy → later, deferred."*
   Do **not** add the radio group, do **not** add a flag, do **not** add a disabled
   „připravujeme" tile for it. It leaves the wizard entirely in round 1 and comes back as its own
   domain item once the feature is designed. Recorded below under „Deferred".

2. **„Dohrávat turnaj?" IS the playoff toggle.** Product owner: *„'Dohrávat turnaj?' is the playoff
   and should be mentioned in the text."*
   So there is **no new concept and no second control**. W2 already moved the existing „Zahrnout
   playoff zápasy" checkbox onto step 2; that single control is the answer to this question. What
   W1 must do is **reword it so a reader recognises it as „dohrávat turnaj"** — the label and/or
   help text should say so in as many words. Do not add a duplicate radio group to the rules list;
   two controls writing one `includePlayoff` flag is exactly the bug this decision prevents.

   Mind the visibility conditions W2 deliberately preserved: the checkbox only shows for a private
   competition with a source chosen and match scope `all`, because `CompetitionMatchProvider` only
   honours `includePlayoff` in mode `all` (`subset` keeps what was ticked, `teams` always includes
   playoff) and `CreateCompetitionHandler` forces it `true` otherwise. Rewording must not widen
   that — showing the toggle in the other modes would promise a choice the domain does not offer.

3. **„Chcete tipovat také střelce utkání?"** — unchanged from the recon: it maps cleanly onto
   `scorer_hit` enablement, so implement it as a rule toggle, not as new schema.

4. **PP vs PEN — keep ONE combined overtime score, just relabel it.** Product owner chose „Keep one
   combined score, relabel" over splitting.
   So `SportMatch` and `Guess` keep their single overtime score pair meaning „after prolongation *or*
   shootout", scored by the existing `overtime_exact` rule. **No new columns, no migration, no
   change to the score-entry or guess forms.** W1's job is only to word it clearly in the Maxi
   preset — one „Celkové skóre po prodloužení / penaltách" entry, not two. Separating PP from PEN is
   deferred to its own item if the distinction is ever needed.

5. **Periods („Tipování části zápasu") — BOTH partial-credit styles available.** Product owner chose
   „Both styles available".

   Today periods reward only the *winner* style. The full match rewards both styles. Maxi must offer
   both for periods too:

   | Style | Full-match rule (exists) | Period rule |
   |---|---|---|
   | exact score | `exact_score` (5 b) | `period_exact` (5 b) — **exists, keep** |
   | right winner | `correct_outcome` (3 b) | `period_tendency` (2 b) — **exists, KEEP** |
   | each team's goals | `correct_home_goals` / `correct_away_goals` (1 b each) | **ADD two new rules** |

   So: **add two new rules** — per-period home goals and per-period away goals — and **keep
   `period_tendency`**. Nothing is retired. The organiser enables whichever they want and sets the
   points, exactly like every other rule.

   Worked example the decision was made against — half-time finishes **2:1**, player tipped **2:0**:
   `period_exact` misses; `period_tendency` hits (both home wins); the new per-period home-goals rule
   hits (2 = 2); the new away-goals rule misses (0 ≠ 1).

   Follow the existing `#[AsRule]` pattern — registration comes from implementing `Rule`, metadata
   lives in property hooks. `CompetitionRuleConfiguration` stores `(rule identifier, enabled,
   points)`, so two new rule classes need **no schema change**. Both default to **disabled**, like
   every other non-`base` rule, so no existing competition's scoring changes.

**W1 is now fully unblocked.** Nothing in it is waiting on the product owner.

### Deferred out of W1

- **Fantasy** — a whole feature, not a toggle. Zero occurrences in the codebase today. Needs a
  product definition before any schema or UI work.

### Rule inventory (recon)

Recon only — **nothing below was changed**; the renames wait on the product owner.

#### How a rule is defined

`#[AsRule]` (`src/Rule/AsRule.php`) is a **bare marker attribute that declares nothing**. Registration
happens because the class implements `App\Rule\Rule`, which `config/services.php` matches with an
`_instanceof` block tagging it `app.rule`; `RuleRegistry` indexes by identifier. All metadata
(`identifier`, `label`, `description`, `defaultPoints`, `enabledByDefault`, `category`) are **property
hooks on the rule class**. There is no binary/count flag — `evaluate()` returns an `int`: binary rules
return 0/1, counting rules return a hit count.

#### The 8 rules that exist today

| identifier | category | pts | on by default | Wizard title (`rule_copy`) | Wizard description | Kind |
|---|---|---|---|---|---|---|
| `correct_home_goals` | `base` | 1 | yes | Dobrý tip skóre domácích | Trefený počet gólů domácího týmu | binary |
| `correct_away_goals` | `base` | 1 | yes | Dobrý tip skóre hostů | Trefený počet gólů hostujícího týmu | binary |
| `correct_outcome` | `base` | 3 | yes | Dobrý tip výsledku | Výhra / remíza / prohra | binary |
| `exact_score` | `base` | 5 | yes | Přesný tip výsledku | Trefená obě skóre současně | binary |
| `period_exact` | `periods` | 5 | **no** | Přesný tip části zápasu | Trefené přesné skóre poločasu či třetiny | **count** (per period) |
| `period_tendency` | `periods` | 2 | **no** | Tendence části zápasu | Správný vítěz nebo remíza části (bez přesného skóre) | **count**; per period mutually exclusive with `period_exact` |
| `scorer_hit` | `scorers` | 2 | **no** | Trefený střelec | Body za každého správně tipnutého střelce | **count** |
| `overtime_exact` | `overtime` | 3 | **no** | Přesný tip po prodloužení | Trefený konečný stav po prodloužení či nájezdech | binary |

Classes live one-per-file in `src/Rule/`. The rule classes carry their own PHP `label`/`description`
(e.g. `correct_home_goals` → „Počet gólů domácí"), but those are surfaced **only** in the admin
read-only table `templates/admin/rule/list.html.twig` and in the leaderboard breakdown; every
configuration UI overrides them with the `rule_copy` map above.

⚠️ **`rule_copy` is duplicated verbatim in two templates** —
`templates/components/Competition/CreateWizard.html.twig:10-19` and
`templates/components/Scoring/RuleFields.html.twig:27-36`. **Any rename in W1 must edit both**, or the
wizard and the post-creation rules screen will disagree. Extracting it to one shared source is the
obvious prerequisite refactor.

#### Section headings

`RulePresetProvider::SECTION_HEADINGS` = `base` → „Základní bodování", `periods` → „Části zápasu",
`scorers` → „Střelci", `overtime` → „Prodloužení". The wizard **shadows** them with a local
`section_headings` map (`CreateWizard.html.twig:20-25`) that interpolates the sport:
`periods` → „Tipovat také {poločasy|třetiny} zápasu?", `overtime` → „Tipovat výsledek po prodloužení
při remíze?". `RuleFields.html.twig` uses the un-interpolated PHP headings — a second place the two
surfaces already diverge.

#### Existing preset mechanism — yes, there is one, but it has only two presets

`RulePresetProvider::presets()` returns exactly **two**, both computed (nothing hardcoded):
`standard` = every `base` identifier; `scorer` = base + `scorer_hit`. There is **no „Maxi"** and **no
„Vlastní"** in PHP. The `.variant-card` tiles + `scoring_preset` Stimulus controller
(`assets/controllers/scoring_preset_controller.js`) expose three actions: `standard()` and `scorer()`
apply a preset (each **re-sets every points field to its default** and ticks/unticks enablement);
`custom()` is a **pure no-op that only highlights the tile**. So:

- Adding „Maxi" = one new entry in `presets()` + one tile — cheap, *if* the rules it needs exist.
- „Vlastní (připravujeme)" = relabel the existing no-op tile and add `disabled` (`.variant-card`
  already has `[disabled]` styling). Note the wizard currently ships with **„Vlastní" pre-selected**,
  while `RuleFields` pre-selects „Standardní" — disabling it means moving the wizard's default.

#### What can be persisted per competition

`CompetitionRuleConfiguration` (unique on `competition_id` + `rule_identifier`) stores **only**:
`competition`, `ruleIdentifier`, `enabled` (bool), `points` (int), `updatedAt`. That is the entire
per-competition rule state. There is **nowhere** to store a radio-group answer, a fantasy flag, or a
„dohrávat turnaj" flag without new schema.

Corollary (`src/Service/Competition/GuessFeatures.php`): **feature toggles ARE rule enablement** —
periods ⇔ `period_exact` OR `period_tendency`, scorers ⇔ `scorer_hit`, overtime ⇔ `overtime_exact`.
There are deliberately no duplicate flags.

#### Item-by-item verdict on what W1 asks for

**The four Standardní rules** — all four are exactly the `base` category, i.e. exactly what the
existing `standard` preset already returns. All four asks are **pure copy changes, no domain work**:

| Asked | identifier | Verdict |
|---|---|---|
| DOBRÝ TIP SKÓRE HOSTŮ → **Tip hosté** / „Správný tip hostujícího týmu" | `correct_away_goals` | rename only (both `rule_copy` maps) |
| DOBRÝ TIP SKÓRE DOMÁCÍCH → **Tip domácí** / „Správný tip domácího týmu" | `correct_home_goals` | rename only (both maps) |
| DOBRÝ TIP VÝSLEDKU *(unchanged)* | `correct_outcome` | no change |
| PŘESNÝ TIP VÝSLEDKU → desc „bonus za obě uhodnutá skóre" | `exact_score` | description only |

**The Maxi extras:**

| Asked | Verdict |
|---|---|
| **Tipování části zápasu** — points for *přesný tip · skóre hostů · skóre domácích* | **PARTIAL.** `period_exact` covers „přesný tip". **Per-period home-goals and away-goals rules DO NOT EXIST** — they would be two new `#[AsRule]` classes in category `periods`. See the reconciliation note below. |
| **Výsledek po základní hrací době** | **ALREADY EXISTS, unnamed.** Regulation time *is* the primary result — `correct_outcome` / `exact_score` already score exactly this. Likely a relabel, not a new rule. Needs PO confirmation that nothing else is meant. |
| **Celkové skóre po PP** (prodloužení) | **NEW SCHEMA.** Today `SportMatch` and `Guess` each carry ONE combined pair `overtimeHomeScore`/`overtimeAwayScore` documented as „final score AFTER prolongation **or** shootout", scored by the single `overtime_exact`. PP and PEN are **not distinguished anywhere.** |
| **Celkové skóre po PEN** (penalty) | **NEW SCHEMA**, same reason. Splitting PP from PEN needs new columns on **both** `SportMatch` and `Guess`, a migration, new rules, and changes to the score-entry + guess forms and their validation invariants. This is the single biggest item in W1. |

**The three optional yes/no groups:**

| Asked | Verdict |
|---|---|
| „Chcete tipovat také střelce utkání?" | **MAPS CLEANLY** — it is exactly `scorer_hit` enablement (`GuessFeatures` says feature toggles *are* rule enablement). Pure UI presentation of an existing toggle. |
| „Budete hrát také fantasy?" | **DOES NOT EXIST — brand-new domain concept.** Zero occurrences anywhere in `src/`, `templates/`, `migrations/`, `tests/`; the only trace is an aspirational comment in `src/Entity/MatchEvent.php` and a deferred idea in DOMAIN.md. Needs a full domain design, not a wizard toggle. |
| „Dohrávat turnaj?" | **AMBIGUOUS — needs the PO.** No such concept exists. The closest existing thing is `Competition::$includePlayoff`, which **W2 just moved onto step 2** — so if „dohrávat turnaj" means „include playoff", W1 would duplicate the W2 toggle and the two must be merged. If it means something else (e.g. keep scoring after the group stage ends), it is new. |

#### „TIPOVAT TAKÉ POLOČASY ZÁPASU?" vs the requested „Tipování části zápasu"

**Related but NOT the same thing.** The existing block is the `periods` section, heading
sport-interpolated („Tipovat také poločasy/třetiny zápasu?"), containing **two** rules that are
mutually exclusive per period: `period_exact` (exact period score) and `period_tendency` (winner of
the period only). W1 asks for **three** point values — *přesný tip · skóre hostů · skóre domácích* —
which is the whole-match `exact_score`/`correct_away_goals`/`correct_home_goals` trio applied per
period. Overlap is only `period_exact` ↔ „přesný tip".

So reconciling them means deciding, with the PO:
1. do per-period **home-goals / away-goals** rules get added (2 new rule classes), and
2. what happens to **`period_tendency`**, which W1's list does not mention at all — keep it as a
   fourth option, or drop it (it is off by default, so dropping is cheap but is a domain decision).

---

## W2 — Playoff option moves from step 1 to step 2

„Zrušit možnost hrát playoff, v bodě 1, bude v bodě 2." Remove the playoff choice from wizard step 1;
it belongs in step 2 instead.

### Implementation

The „Zahrnout playoff zápasy" checkbox moved out of step 1's „Zápasy soutěže" block and onto step 2
(„Pravidla"), under its own „Playoff" sub-heading below the scoring fields (outside the
`scoring-preset` controller — it is not a scoring rule). Same `data-model="includePlayoff"`, same
copy, **same visibility conditions as before**: private (non-global) competition, a curated source
chosen, and match scope `all`. That condition is not cosmetic —
`CompetitionMatchProvider` only honours `includePlayoff` in mode `all` (`subset` keeps whatever was
ticked, `teams` always includes playoff), and `CreateCompetitionHandler` forces it to `true` for any
other mode. Step 1 is now purely the match-scope choice.

---

## W3 — Step 3: remove the duplicated hint

The explanatory text at the bottom of step 3 repeats what is already written directly under the
heading. Remove the bottom copy and leave only the „Přeskočit" action.

### Implementation

The duplicated copy was the **suffix on the skip button** in the wizard footer: „Přeskočit — pozvat
můžete kdykoli později", which repeated the step heading's „Vše je nepovinné — pozvat můžete kdykoli
později z detailu soutěže." The button now reads just „Přeskočit"; the heading is untouched.

---

## W4 — Step 4: new Premium copy

Replace the step-4 copy verbatim with:

> 🍺 **Pozvete nás na pivo?**
>
> Tipovačka je kompletně zdarma. Výsledky se zapisují automaticky, tabulka se počítá sama a vy si
> můžete užívat soutěž bez zbytečné administrativy.
>
> Vytvořili jsme ji, protože nás baví sport stejně jako vás. Neustále ji rozvíjíme, přidáváme nové
> funkce a chceme, aby byla nejlepší tipovačkou na trhu.
>
> Teď už jen rozhodněte, jak budou ve vaší soutěži fungovat prémiové funkce.

Then the two monetization choices (this is the existing **Premium XOR boosts** decision — one
`monetization` column, per `.docs/DOMAIN.md`; do not invent a third state):

> 🟡 **Férová soutěž** ⭐ Doporučujeme
>
> Nikdo nebude moci získat individuální výhodu zakoupením Premium funkcí. Pokud se později rozhodnete
> Premium aktivovat, budou dostupné všem hráčům za stejných podmínek.
> V případě aktivace Premium rozhodujete o podpoře aplikace vy jako administrátor soutěže.
>
> ○ Chci hrát Férovou soutěž

> ⚪ **Volná volba Premium**
>
> Každý hráč se během soutěže rozhodne sám, zda si chce aktivovat Premium funkce a podpořit tak další
> vývoj aplikace.
>
> ○ Chci ponechat rozhodnutí na hráčích

Map „Férová soutěž" → the competition-wide premium option, „Volná volba Premium" → the per-player
boost option. Verify the mapping against `CompetitionMonetization` before wiring, and keep
„Doporučujeme" on Férová soutěž as the pre-selected default.

### Implementation

**Mapping verified in code before wiring** (`src/Enum/CompetitionMonetization.php`), and it holds:

| New copy | Enum case | Why it matches |
|---|---|---|
| **Férová soutěž** | `CompetitionMonetization::Premium` | Premium is the *competition-wide* option — the organizer buys for the whole group, so no individual can buy an edge, exactly what the copy promises. |
| **Volná volba Premium** | `CompetitionMonetization::Boosts` | Boosts are the *per-player* purchases — each player decides for themselves. |

No third state was invented: it stays one `monetization` column, Premium XOR boosts. The enum's
third case `None` is **not offered in the private wizard** (it never was) — it remains reachable only
in the admin global-competition branch, whose copy W4 does not touch.

Other changes this forced:

- **The default flipped from `Boosts` to `Premium`** so „Férová soutěž ⭐ Doporučujeme" is genuinely
  pre-selected — both the `$monetization` LiveProp initialiser and the `usePrivateKind()` reset.
- **Step 4 is now branched** global vs private. The two option cards used to be shared markup; the
  new private copy differs enough (prose instead of feature/price bullets, a visible radio row) that
  the admin global branch keeps its own untouched cards.
- The submit CTA no longer switches to „Vytvořit a přispět" when Premium is selected — see
  Assumptions.

---

## W5 — No success flash after sign-up

„After sign up the flash messages are waste because I land already on the page with nice context info
that I need to confirm the email — so if everything is successful I do not need a flash message."

Drop the success flash on the post-registration redirect to `/overeni-ceka`; that page already
explains the next step. **Error flashes stay.** Related to `BUGS.md` B1, which hardens that same
airlock.

### Implementation

`Auth\RegistrationForm::register()` no longer adds „Registrace proběhla úspěšně…". That alone was
not enough: `LoginSubscriber` also queues a „Nejprve ověřte svou e-mailovou adresu" warning for
every unverified login, and its „skip during registration" check tested `_route === 'app_register'`
— but sign-up runs through the `Auth:RegistrationForm` **Live Component**, whose route is the
shared `ux_live_component`, so the warning slipped through. The check now also matches the
component name, so a successful sign-up lands on `/overeni-ceka` with **no flash at all**.
Inline form errors and the error flashes are untouched. Pinned by
`RegistrationFlowTest::testSuccessfulRegistrationShowsNoFlash`.

The invitation sign-up (`Auth:InvitationForm`) keeps its own flash — it carries extra information
about the pending join, which the airlock page does not show. Out of W5's scope.

---

## W6 — Step 1 „Zápasy soutěže" is missing the „Podle týmu" mode

**Report.** On step 1 the „ZÁPASY SOUTĚŽE" choice offers only **Všechny zápasy** and **Vybrat jen
některé zápasy**. „Tady by bylo dobré mít možnost: vybrat podle týmu, vybrat ručně…"

`CLAUDE.md` documents three match-scope modes and `CompetitionMatchProvider` implements them:
`all` (Všechny) · `teams` (Podle týmu — dynamic, every source match where a `CompetitionTeamFilter`
team plays home or away, playoff always in) · `subset` (Vybrané, private sources only). The wizard is
only exposing two of the three, so **check first whether this is a regression** in
`Competition:CreateWizard` or a mode that was never wired into the wizard UI.

**Expected.** Three options on step 1:
- **Všechny zápasy** — `all`
- **Podle týmu** — `teams`; reveals the multi-team `team-filter` tom-select (see
  `.docs/features/team-picker.md`). This is the mode a user picks to follow one club through a season.
- **Vybrat jen některé zápasy** — `subset`; reveals the existing grouped match checkbox list.

Do not add a fourth mode and do not teach the provider anything new — the modes exist; this is a
wizard-UI gap. Beware the note in `CLAUDE.md`: a mode must be understood by **both**
`applyCompetitionMatchFilter` and `applyRowLevelCompetitionMatchFilter`, so if `teams` turns out to
be half-wired, fix both.

Note the interaction with **W2** — the playoff option moves from step 1 to step 2, so step 1 keeps
only the match-scope choice.

### Finding — neither a regression nor a gap: the report was stale

**„Podle týmu" was already shipped and already rendering.** It landed in `fd053b9` („Competition team
filter: scope a competition to specific teams"), merged via PR #3 (`71c8e45`), which is an ancestor of
`main`. No code change was needed to make the option appear. Verified against the running dev app:
step 1 with a curated source renders all three radios („Všechny zápasy" / „Podle týmu" / „Vybrat jen
některé zápasy") and picking „Podle týmu" reveals the `team-filter` tom-select island. The product
owner was presumably looking at a build from before that merge.

The `CLAUDE.md` both-methods warning was checked explicitly and `teams` **is** fully wired in
`CompetitionMatchProvider`: `applyCompetitionMatchFilter`, `applyRowLevelCompetitionMatchFilter`
**and** `includesIgnoringDeletion` all branch on `CompetitionMatchSelectionMode::Teams`. Nothing was
half-wired, so nothing was changed there.

What *was* missing was **test coverage** — the wizard had zero tests for the mode, which is exactly
how a UI option can silently disappear and produce a report like this one. Now pinned in
`tests/Integration/Portal/Competition/CreateWizardComponentTest.php`:

- `testStepOneOffersThreeMatchScopeModesWithoutPlayoffToggle` — all three radios present (and, for
  W2, the playoff toggle absent);
- `testTeamsModeHappyPath` — creates a Sparta-scoped competition and asserts the `CompetitionTeamFilter`
  row plus that `CompetitionMatchProvider::matchesFor()` returns exactly the one fixture match Sparta plays;
- `testTeamsModeWithZeroTeamsBlocksAdvancing` — empty team pick cannot leave step 1.

---

## Assumptions made

Recorded per the orchestration protocol — conservative readings of things the backlog did not settle.

1. **W2 — the playoff toggle keeps its original visibility conditions.** It shows on step 2 only for a
   private competition with a source and match scope `all`, exactly as it did on step 1. Showing it
   unconditionally would be a lie in the other modes, since `CompetitionMatchProvider` ignores
   `includePlayoff` for `subset`/`teams` and the handler forces it to `true` there.
2. **W4 — decorative emoji render as the design system's lucide icons.** 🍺 → `lucide:beer` (already
   how the heading was built), 🟡 → `lucide:crown`, ⚪ → `lucide:hand-coins`, ⭐ → `lucide:star` in the
   „Doporučujeme" badge. All Czech wording is verbatim; only the bullets use the established icon
   vocabulary that `CLAUDE.md` mandates for this project. Flip these to literal emoji if the PO wants.
3. **W4 — the private step 4 now shows only the specified copy.** The old credit-balance strip,
   „Dokoupit kredity" link, per-boost price list, the „{X} kreditů × počet hráčů" line and the
   „Teď se nic nestrhává…" footnote were **dropped from the private branch**, because the replacement
   copy was given as the whole of step 4 and mentions no prices. They are all **kept unchanged in the
   admin global branch**, which W4 does not cover.
4. **W4 — the submit button no longer reads „Vytvořit a přispět".** It previously switched to that
   label whenever Premium was selected. With Premium now the pre-selected default and the new copy
   explicitly deferring the decision („Pokud se **později** rozhodnete Premium aktivovat"), that label
   would promise a payment that creation does not make. The private CTA is now always „Vytvořit
   soutěž". The global CTA („Vytvořit globální soutěž") is unchanged.
5. **W4 — `CompetitionMonetization::None` stays out of the private wizard.** The two given choices map
   onto Premium and Boosts; `None` was never offered privately and adding it would be the forbidden
   third state. It remains available in the admin global branch only.
