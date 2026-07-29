# Create-competition wizard + copy backlog

Reported by the product owner on 2026-07-29, against
`/portal/souteze/nova?zdroj=…` (`Competition:CreateWizard`, see `.docs/features/create-wizard.md`).

Legend: `TODO` · `IN PROGRESS` · `DONE` · `BLOCKED`

| # | Title | Status | Commit |
|---|-------|--------|--------|
| W1 | Rules step: Standardní / Maxi / Vlastní presets + renamed rules | TODO | — |
| W2 | Playoff option moves from step 1 to step 2 | TODO | — |
| W3 | Step 3: drop the duplicated hint, leave only „Přeskočit" | TODO | — |
| W4 | Step 4: new Premium copy („Pozvete nás na pivo?") | TODO | — |
| W5 | No success flash after sign-up | DONE | `7b3f010` |
| W6 | Step 1 „Zápasy soutěže" is missing the „Podle týmu" mode | TODO | — |

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

---

## W2 — Playoff option moves from step 1 to step 2

„Zrušit možnost hrát playoff, v bodě 1, bude v bodě 2." Remove the playoff choice from wizard step 1;
it belongs in step 2 instead.

---

## W3 — Step 3: remove the duplicated hint

The explanatory text at the bottom of step 3 repeats what is already written directly under the
heading. Remove the bottom copy and leave only the „Přeskočit" action.

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
