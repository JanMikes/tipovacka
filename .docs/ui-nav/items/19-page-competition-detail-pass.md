# 19 — Competition detail: description, invite CTA, reordered page, 5 matches, first-visit credits modal

> **Status:** TODO
> **Depends on:** item 08 (which made this page a playing surface), item 11 + item 18 (the match card and
> its `variant` prop), B6 (a fully-over competition sells nothing), B10 (which this item closes).
> **Owner decision date:** 2026-07-30

## Why (the requirement, in the product owner's terms)

`ROUND2.md` batch 2, verbatim:

> zde musíme dostat popis soutěže (nové pole v administraci a při zakládání)
> Nad banner tipněte všechny soutěže bude tlačítko pozvat kamaráda
> Pod ním bude napsáno tabulka souteze
> Zápasy musí fungovat na proklik
> Zobrazit pouze 5 zápasů, pod tím umístit tlačítko načíst všechny zápasy
> Pod tim bude tabulka s hodnocením a posléze s premiovými funkcemi
> Když najedu do soutěže poprvíé, chtěl bych aby vyskočil modal s tou tabulkou kreditů, s možností
> vypnout, křížkem, nebo tlačítkem, pochopil jsem, již nezobrazovat .

## What changes

### A. The vertical order — settled by the product owner

Chosen from three options; this is the one they picked (`ROUND2.md` decision 6):

```
┌─ header (název, pilulky, role, akce)
├─ popis soutěže                     ← new (B below)
├─ [ Pozvat kamaráda ]
├─ banner: Tipněte si všechny zápasy najednou
├─ Tabulka soutěže                   ← heading
├─ zápas × 5
│    [ Načíst všechny zápasy ]
├─ Žebříček (tabulka s hodnocením)
└─ Prémiové funkce / vylepšení
```

**The aside stops being an aside** — the žebříček and `Boost:Panel` move into the single column, in that
order, after the matches. Item 08 put them in a sidebar; this supersedes that. Keep everything they
contain (real žebříček rows, „Celý žebříček" → `/zebricek?soutez=`, per-row links to `leaderboard_member`,
the owned-boost jump links, B6's „soutěž už skončila" state).

**„Pozvat kamaráda"** points at `/souteze/{id}/nastaveni#pozvanky`, which is where item 08 put every
invitation mechanism (e-mail, bulk, bez e-mailu, PIN, shareable link). Do not build a second invite
surface. It is shown to whoever passes the same voter the existing „Pozvat" action uses.

### B. „popis soutěže" — a new field (the only schema change in round 2)

A free-text description on `Competition`, shown on this page and editable where a competition is created
or edited:

- **Entity**: a nullable text column on `Competition`. Follow the project's entity conventions —
  `public private(set)`, a behaviour method rather than a setter, no trivial accessor.
- **Migration**: generated with `doctrine:migrations:diff`, **never hand-written**, and committed as
  produced. Nullable, so existing rows need no backfill.
- **Where it is set**: the create wizard (`Competition:CreateWizard`), `competition_edit`, and the admin
  global-competition create/edit forms. **All of them** — „nové pole v administraci a při zakládání".
- **Where it is shown**: this page, under the header. Render nothing at all when it is empty — no empty
  box, no placeholder prose.
- **Length**: pick a sane cap, validate it in the FormData with `#[Assert\Length]`, and say what you
  chose. It is a description, not an article.
- **Escaping**: it is user input rendered on a page other members read. Twig autoescapes; do **not**
  reach for `|raw`. If you want line breaks to survive, `|nl2br` on the *escaped* value is the only
  acceptable route.

### C. Matches: clickable, five at a time

- **„Zápasy musí fungovat na proklik"** — the card is already one link on the Nástěnka
  (`variant="dashboard"`, item 18). **Decide and report** whether competition detail adopts that variant
  or keeps `variant="default"` and gains clickability another way. Read item 18's „Match:MatchRow is
  shared" section first: the product owner scoped the *visual* redesign to the Nástěnka, and item 18
  proved `/zapasy` and this page still render byte-for-byte. **Do not silently restyle this page**;
  „works on click" is the requirement here, the new look is not.
- **Five matches, then „Načíst všechny zápasy"** — the `reveal` Stimulus controller already does exactly
  this on the Nástěnka with `matches_visible = 5`. Reuse it; do not write a second one.
- ⚠ **B25 applies the moment you do.** With JavaScript off, `reveal` leaves the 6th and later matches
  **unreachable** — not by scrolling, not by any URL. Every other control in this stream is deliberately
  JS-free, so shipping a second instance of that hole makes it worse. **Fix B25 as part of this item**:
  render all rows and let the button only *collapse* (so „hidden" is the enhanced state), or make it a
  real server-rendered link. Read B25 and say which you chose. Do not remove the truncation.

### D. The first-visit credits modal

> Když najedu do soutěže poprvé, chtěl bych aby vyskočil modal s tou tabulkou kreditů, s možností
> vypnout, křížkem, nebo tlačítkem, pochopil jsem, již nezobrazovat.

**Settled** (`ROUND2.md` decision 7): **once per user per competition**, showing **the boost prices**.

- **This closes `BUGS.md` B10**, which is currently `BLOCKED` for exactly this gap — a player choosing
  „Volná volba Premium" is never told what the boosts cost. Read B10 and mark it closed in your report.
  **Prices come from `Credits/PricingConfig`.** Never a literal.
- **Dismissal state**: per user per competition. The natural home is the viewer's `Membership` — a
  nullable „seen" timestamp is enough and is more useful than a boolean. Generated migration.
- **Dismissible three ways**, all equivalent: the ✕, a „Pochopil jsem, již nezobrazovat" button, and the
  usual dialog dismissal (Esc / backdrop). All three must persist the dismissal — a modal you can close
  without it sticking is worse than no modal.
- **It must not appear where it makes no sense**: not for a non-member, not on a **fully over**
  competition (B6's definition: ≥1 match and none `Scheduled`/`Live`/`Postponed`), and — think about
  this one — not on a **premium** competition, where the organizer pays and individual boosts are not
  the funding model. **Premium XOR boosts**; a modal selling boosts on a premium competition would
  contradict the single `monetization` column. Say what you decided.
- **Without JavaScript** the page must still work: the modal is an enhancement. A `<dialog>` that never
  opens is acceptable; a page that becomes unusable is not.
- Reuse the existing modal vocabulary (`.modal-backdrop` is at `z-index: 200`, and B3 put body-level
  dropdowns at 300 — check what you sit above). Do **not** invent a second dialog pattern; see
  `.docs/features/confirm-modal.md`.

## Out of scope

- **The invitation surfaces themselves** — „Pozvat kamaráda" links to the existing block.
- **`/souteze/{id}/nastaveni`** and the large form pages behind it.
- The Nástěnka, `/zapasy`, the žebříček page — all separately specced and shipped.
- **Do not touch `templates/portal/competition/settings.html.twig`.**
- B21, B22, B24 — other rows.

## Acceptance criteria

- [ ] The page renders in the exact order in A, in **one column**; the aside is gone.
- [ ] A description set in the wizard, in `competition_edit`, and in the admin global form all show on this page; an empty description renders **nothing**.
- [ ] A description containing `<script>` or `"` renders **escaped** (assert it).
- [ ] `doctrine:migrations:diff` produces **no further changes** after your migration, and `schema:validate` is clean.
- [ ] Only 5 matches render initially; „Načíst všechny zápasy" reveals the rest; **with JavaScript disabled every match is still reachable** (B25).
- [ ] A match card navigates to the match on click; `/zapasy` and the Nástěnka are **unaffected** (prove it).
- [ ] The modal appears on a member's **first** visit to a `boosts` competition, shows prices from `PricingConfig`, and **never appears again** after any of the three dismissals — including in a fresh session.
- [ ] It does **not** appear for a non-member, on a fully-over competition, or (per your decision) on a premium one.
- [ ] `Boost:Panel` and the žebříček keep every behaviour item 08 and B6 gave them.
- [ ] Nothing 404s; every route still declared in `tests/Integration/Security/AnonymousReachabilityTest`.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web bin/console doctrine:schema:validate
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Admin
docker compose exec web vendor/bin/phpunit tests/Integration/Command
docker compose exec web vendor/bin/phpunit tests/Integration/Query
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk; strip ANSI codes before grepping.

**`composer quality` renders no templates and opens no dialogs.** In a real browser:

- Walk the modal: first visit → appears; ✕ → gone; reload → **still gone**; new session → still gone. Then repeat for the button and for Esc.
- Load the page as: the owner, a plain member, a member of a **premium** competition, a member of a
  **fully over** competition, and a non-member.
- Measure at **1600 / 1440 / 1024 / 430 / 320 px** — zero overlaps, zero horizontal overflow. Note the
  card is **container-relative** (B7): this page's column changes width when the aside disappears, so
  measure the **card's own width**, and re-measure after the reorder. `getClientRects().length` on a
  block element is always 1 — use a `Range` over the contents if you count wrapped lines.
- Disable JavaScript genuinely (not simulated) and confirm: all matches reachable, the page usable, the
  invite CTA and the banner still work.
- After `composer db:reset` you **must** `docker compose restart web`. **You will need a reset** for the
  migration; nothing else is in flight on this database, but coordinate via your report if you see other
  changes appear. Never run `asset-map:compile`; if assets look frozen, `rm -rf public/assets` then
  restart `web`.

## Domain guard rails (binding)

- **Premium XOR boosts** — one `monetization` column, never both, never a third state.
- **Never „sázka"** or its verb forms. No gambling mechanics, no payouts, no prize-pool language; an
  entry fee is burned credits.
- **Prices from `Credits/PricingConfig`.**
- **`TipStatsProvider` batched per page, never per row.**
- **`CompetitionMatchProvider` is the only answer to „what is in this competition"** — do not re-derive
  the match list.
- **Managers and admins get no free entitlement pass** (`CompetitionEntitlements`).
- **Migrations are generated, never hand-written.** Two migrations (description, modal-seen) are fine;
  hand-editing either is not.
- Czech in the UI, English in code and comments.

## CSS discipline

You own **`assets/styles/app.css`**. Reuse first — `.card-glass`, `.bg-inset`, the modal classes, and
item 18's `.card-clickable` / `.card-stretch` / `.card-raise` (the documented way to make a card
clickable **without nesting interactive elements** — read §5 of `UI-MAP.md`). New rules at the **end of
the section they belong to** under `/* --- item 19: competition detail --- */`. Never reorder or reformat
existing rules. **Do not touch item 17's `.bell-panel` / `.credit-chip` or item 18's card rules.**

## Git

**Commit with `git commit -o <path> [<path>…] -m …`** (`-o` = `--only`) — index-independent. Do not use
`git add` + `git commit`: an agent staged explicit paths, verified `git diff --cached --stat`, and still
swept in another agent's `app.css`, because a sibling staged into the index between the two commands.
(`-o` only accepts *tracked* paths; new files need `git add -- <file>` first.)

Never `git add -A` / `git add .` / `git commit -a`. Another session also commits here — `git pull --rebase`
if a push is rejected, never force-push. **An API outage killed five agents mid-task today: commit early
and often** (the field + migration; the reorder; the modal), and if you sense trouble commit the verified
part and say what is unverified.

## Files other agents own right now — do not touch

- `templates/portal/leaderboard/matrix.html.twig`, `src/Service/Competition/TipVisibilityGate.php` and
  `.docs/DOMAIN.md` — the tip-matrix agent (item 20).
- `fixtures/DevFixtures.php`, `.docs/FIXTURES.md` — the fixtures agent (B11 + B23). **This matters to
  you**: you need a `boosts` competition you are a fresh member of to test the modal, and B23 records
  that `db:reset` currently leaves no competition able to reach the lock modal. Do not edit fixtures —
  create what you need at runtime or in a test, and report anything the fixtures could not give you.
- `.docs/ui-nav/PLAN.md`, `UI-MAP.md`, `BUGS.md`, `ROUND2.md` — the orchestrator's. Report deltas as
  exact text; do not edit. **`DOMAIN.md` is the tip-matrix agent's this round** — if the description
  field or the modal deserves a domain note, give me the text instead of writing it.

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
