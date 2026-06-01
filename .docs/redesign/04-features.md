# 04 — Feature improvements

The backend engine stays. These are the gaps the redesign exposes. Two buckets:
**build now** (existing data supports it; mostly frontend + a query) and
**deferred** (needs new backend / cut). Backend specifics follow the project's
CQRS patterns (Query message + `…Query` handler + Result DTO; new entity fields
via `doctrine:migrations:diff`). See `analysis/cur_domain-routes.md` for the
domain and `analysis/_gap.md` §2 for the original effort analysis.

---

## BUILD NOW

### A. 3-state tip card — **frontend only**
Map the existing match/guess state to the DS `MatchCard` (Brzy / Tipováno /
Uzamčeno / Ukončeno). All data exists: `SportMatch.state`,
`SportMatch.isOpenForGuesses`, `Guess` (home/away), `GuessEvaluation.totalPoints`,
and `Guess:GuessSubmitForm` already morphs button labels. **No backend.** Spec in
`02-components.md` ⭐ and `03-phases.md` Phase 4. **No "live" state** (cut).

### B. Stats: úspěšnost / přesné / trefa / streak — **derivable, small query work**
Data exists in `GuessEvaluationRulePoints` (which rule fired per guess):
- **Přesné** = count where `exact_score` rule fired.
- **Trefa (partial)** = count where any rule fired but not exact.
- **Úspěšnost** = scored tips / total evaluated tips (%).
- **Streak** = consecutive scoring (non-zero) evaluations ordered by match kickoff.
Extend `GetGroupLeaderboardQuery` (and the member-breakdown query) to compute
these per member; surface as leaderboard columns + podium micro-stats + dashboard
`StatCard`s. **Streak uses `lucide:flame`, never the 🔥 emoji.**
> Effort: M. No schema change — pure query aggregation over existing tables.

### C. Pick distribution (1/X/2) — **new query, shown free after lock**
New `GetMatchPickDistribution` query: group a match's `Guess` rows by outcome
sign (home>away = „1", equal = „X", away>home = „2") → counts + percentages,
scoped to a `Group`. Render via `PickDistribution` component on the single-match
page (and optionally `MatchRow`).
- **Visibility:** only **after** the match's tip deadline / once locked, and
  respect `Group.hideOthersTipsBeforeDeadline`. Before that, hide it (no leak).
- **No premium gate** now — show the real bars. (The gold „PRÉMIUM" teaser +
  `wtips:open-premium` + the 10 Kč / 50–200 Kč tiers are the **deferred**
  monetization phase.)
> Effort: M. New query only; no schema change.

### D. „Zápasy" page — **new player surface**
`portal_matches` route + `Portal\MatchesController` + a query for the current
user's open/upcoming matches across all their soutěže (generalize the dashboard's
existing upcoming-matches query — it already computes per-group guess status).
Filter chips (Vše / Dnes / Tipovatelné / Ukončené — **no „Live"**) + soutěž
filter. Renders `MatchRow` list. Wire into the app nav („Zápasy").
> Effort: M. Reuses existing query logic; add a controller + template + nav link
> + Integration test.

### E. Soutěž switcher — **navigation, no new backend**
Server-side switcher on dashboard/Zápasy/Žebříček: a menu of the user's groups
that navigates to the same route for the chosen group (`?soutez=<groupId>`).
Default to the most recently active membership; optionally persist last choice in
session. No SPA re-render.
> Effort: S.

### F. Round / stage label on matches — **tiny schema add (optional but recommended)**
Add a nullable `round` (string/enum label) to `SportMatch` so the tip-card header
can show „Skupina A", „Čtvrtfinále", etc. (The untracked `ms-hokej-2026-qf.csv` /
`-sf.csv` confirm bracket-stage data is being imported as flat matches.)
- Add the field on the entity → `bin/console doctrine:migrations:diff` → commit.
- Surface it in CSV import + match form. Fallback in the card: tournament name.
> Effort: S. **One generated migration.** If you'd rather avoid any schema change
> in the redesign, skip and fall back to the tournament/group name as the header
> label — but the label field is cheap and unlocks the DS header design.

### G. Per-match ranking („Pořadí za zápas") — **derivable query (optional)**
A query over `GuessEvaluation` filtered to one match within a group → top scorers
for that match (points + přesně/výsledek chip). Shown on the single-match page.
> Effort: S–M. No schema change. Optional — include if time allows in Phase 4.

### H. Profile / tip-history — **re-skin existing, optional cross-group query**
The requested "profil / historie tipů" page: re-skin `portal/profile/edit.html.twig`
+ use `portal/leaderboard/member.html.twig` (per-member breakdown) as the history
surface, with streak/accuracy header chips from (B). A true **cross-group** tip
history would need a new query (current breakdown is per-group) — optional.
> Effort: S to re-skin; M for a cross-group history page (defer the latter).

---

## DEFERRED (do NOT build in this redesign)

Tracked here so the overnight run doesn't accidentally start them. Each is a real
future feature, just out of scope now.

| Feature | Why deferred | What it would need |
|---|---|---|
| **Social login (Google/Apple)** | **Cut** by user. | OAuth bundle + provider config + user-linking + callback routes. |
| **Live match** (in-product live score/minute/pulse) | **Cut** by user. Marketing decoration only on landing. | Live data source + polling/Mercure + match-event model. |
| **Premium paywall + contributions/pricing** (10 Kč/hráč; 50/100/200 Kč tiers; `wtips:open-premium`) | Whole commerce subsystem; not needed for a visual redesign. | Payment provider (Stripe/GoPay) + entitlement entities + pricing + webhooks + billing-after-lock. **Until then, pick distribution is free (C).** |
| **Payouts / Výplaty / prize bank** | Design intent walked back; „bez sázek". Nav item dropped. | Pot/escrow tracking on `Group` + settlement. |
| **Goalscorers + match timeline + „Trefený střelec" rule** | New entities + new scoring rule. | `MatchEvent`/scorer entity + scorer guess + a `Rule` impl + UI. |
| **Bracket / pavouk visualization** | The DS never draws it (labels only). | Round/stage + advancement linkage + bracket rendering. (We add the **label** only — see F.) |
| **Group-stage standings tables** | The DS never draws it. | Round modeling + team-result aggregation. |
| **Δ rank-change column** | No rank-history storage; don't build snapshot infra now. | A periodic leaderboard-rank snapshot entity. (Leaderboard ships **without** the Δ column.) |
| **Notifications feed + bell** | No notification backend beyond email. | Notification model + feed. (Bell icon omitted from nav.) |
| **Trash-talk feed, funny badges (Smolař/Šťastlivec)** | Gamification, future. | Comments model / badge rules. |
| **Tweaks panel (Vibe/Density/Type pressure)** | Design-authoring tool, not a product feature. | — never build. |

---

## Backend change summary (what the redesign actually touches)

- **No required schema changes** for A–E, G, H. (Pure frontend + read-model
  queries.)
- **One optional generated migration** for F (`SportMatch.round` label).
- **New queries** (read-side, follow the `QueryMessage`/`…Query`/Result pattern):
  - `GetMatchPickDistribution` (C)
  - leaderboard/stat extensions for accuracy/exact/partial/streak (B)
  - the „Zápasy" cross-soutěž matches query (D) — likely a generalization of the
    existing dashboard query
  - per-match ranking query (G, optional)
- **New controllers/routes:** `portal_matches` (D); 4 public marketing pages
  (Funkce/Ceník/Pro firmy/FAQ, Phase 3).
- **Tests:** Integration tests for the new controllers + queries, using fixtures
  (`.claude/FIXTURES.md`) and `MockClock` (fixed `2025-06-15 12:00:00 UTC`).

Keep every change inside the existing CQRS conventions (commands write via the
command bus with `doctrine_transaction`; queries via `QueryBus`; no `flush()` in
repositories; UUID v7 ids via `ProvideIdentity`).
