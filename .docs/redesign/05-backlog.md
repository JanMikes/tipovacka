# 05 — Backlog (post-migration follow-ups)

The dark visual migration is complete and green. This is the prioritized backlog
of features the design system shows but that were **deferred** to keep the
migration a clean, green, pure-visual re-skin. Each item: what the DS shows, what
already exists, what to add (with file hints), effort, and a test hint.

Effort: **S** ≤½ day · **M** 1–2 days · **L** multi-day / new subsystem.

Conventions reminder (CLAUDE.md): queries = `QueryMessage` + `…Query` handler +
Result DTO via `QueryBus`; entity changes → `doctrine:migrations:diff` (never
hand-write); new icons → `ux:icons:import`; `composer quality` must stay green.

---

## P1 — Build next (data already exists, high DS-fidelity value)

### 1. Leaderboard stats: úspěšnost / přesné / trefa / streak ✅ DONE
> Implemented: `GetGroupLeaderboardQuery` extended with evaluated/scored/exact
> aggregates + a streak pass (trailing scoring run by kickoff); `LeaderboardRow`
> gained `accuracyPercent/exactCount/partialCount/streak`; columns added to the
> leaderboard component (hidden on mobile). Test asserts the fixture member's stats.
> **Dashboard stat cards ✅ DONE** (see below): `GetMemberGroupStats` query
> (`src/Query/GetMemberGroupStats/`) reuses `GetGroupLeaderboard` and picks the
> current user's row; the dashboard renders a soutěž-scoped "Moje výsledky" section
> (Pořadí / Body / Úspěšnost / Přesné / Streak) with the switcher + an empty-state
> hint when the member has no evaluated tips. Tests: `GetMemberGroupStatsQueryTest`,
> `DashboardStatsFlowTest`.

### 1b. (original combined item — see #1)
- **DS shows:** leaderboard columns Úspěšnost / Přesné / Trefa / Streak; dashboard
  stat cards (Přesné tipy 9/38, 23,7 % úspěšnost, Streak).
- **Exists:** `GuessEvaluation` (totalPoints per guess) + `GuessEvaluationRulePoints`
  (which rule fired). Accuracy = scored/total; Přesné = `exact_score` rule fired;
  Trefa = any rule fired but not exact; Streak = consecutive non-zero evaluations
  by match kickoff.
- **Add:** extend `App\Query\GetGroupLeaderboard\GetGroupLeaderboardQuery` + its
  `LeaderboardRow`/Result DTO with `accuracyPercent`, `exactCount`, `partialCount`,
  `scoredCount`, `evaluatedCount`, `streak`. Surface in
  `components/Leaderboard/GroupLeaderboard.html.twig` (the `.lb-table` already has
  column slots) + dashboard StatCards. A separate `GetMemberStats` query can feed
  the member-breakdown header.
- **Effort: M.** **Test:** extend `GetGroupLeaderboardQueryTest` with a fixture
  member who has 1 exact + 1 partial + 1 miss → assert the computed columns.

### 2. Pick distribution (1 / X / 2) on the single-match page ✅ DONE
> Implemented: `GetMatchPickDistribution` query + `Match/PickDistribution` component,
> wired into `SportMatchGuessesController`/`guess/detail.html.twig`, shown only when
> `can_see_all_tips`. Test: `GetMatchPickDistributionQueryTest`.
- **DS shows:** home-win / draw / away-win split with counts + % + `.dist-bar`.
- **Exists:** `Guess` rows per (user, match, group). `.dist-bar` + a
  `PickDistribution` component slot are styled. NO query yet.
- **Add:** `App\Query\GetMatchPickDistribution\{GetMatchPickDistribution,
  …Query, Result}` — group a match's guesses (within a Group) by outcome sign
  (home>away=1, ==X, away>home=2) → counts + percent. Render on
  `portal/guess/detail.html.twig` **only after the tip deadline / once locked**
  and respect `Group.hideOthersTipsBeforeDeadline`. **No premium gate** (that's
  P3). Build a `components/Match/PickDistribution.html.twig` wrapping `.dist-bar`.
- **Effort: M.** **Test:** new query test — 3 guesses (1/X/2 mix) → assert
  buckets + percentages; controller test that bars are hidden before deadline.

### 3. Soutěž switcher (dashboard / leaderboard) ✅ DONE
> Implemented: `templates/components/SoutezSwitcher.html.twig` — a server-side
> `<details>` dropdown of the user's soutěže; each option links to the same route
> for the chosen group via `path(route, {(param): groupId})` (path segment when the
> route has the placeholder, else a `?param=<id>` query string). Renders only with
> ≥2 groups; defaults to the most recently joined membership. Wired into the
> leaderboard header (`param="groupId"`) and the dashboard "Moje výsledky" section
> (`param="soutez"`). Tests: `SoutezSwitcherFlowTest` (lists groups + links resolve
> + hidden for a single group).

### 4. "Zápasy" page (cross-soutěž matches) + nav item
- **DS shows:** a top-level Zápasy page — all my open/upcoming matches across
  soutěže, filter chips (Vše/Dnes/Tipovatelné/Ukončené — no Live).
- **Exists:** the dashboard already computes per-group upcoming matches + guess
  status; generalize it.
- **Add:** `App\Controller\Portal\MatchesController` (`portal_matches`) + a query
  for the current user's matches across all memberships; `portal/matches/index.html.twig`
  using the dashboard's match-row markup; add "Zápasy" to the app nav
  (`components/Layout/Nav.html.twig`, between Soutěže and Žebříček).
- **Effort: M.** **Test:** WebTestCase — page lists matches from ≥2 of the user's
  groups; filter chips work (if server-side) or are inert links.

### 5. `SportMatch.round` / stage label
- **DS shows:** tip-card header line 1 = round ("Skupina A", "Čtvrtfinále").
- **Exists:** flat match list; the untracked `ms-hokej-2026-{qf,sf,final}.csv`
  imply round data is imported as flat matches.
- **Add:** nullable `?string $round` on `SportMatch` (+ a small `updateRound`/
  constructor wiring), generate the migration, surface it in the create form +
  CSV import (header column) + the tip-card header (fallback = tournament name).
- **Effort: S** (one generated migration). **Test:** migrations-up-to-date +
  schema:validate must stay green; an import test asserting round is stored.

### 6. Per-match ranking ("Pořadí za zápas")
- **DS shows:** on the single-match page, top scorers for that match.
- **Exists:** `GuessEvaluation` per match within a group.
- **Add:** `GetMatchRanking` query (evaluations filtered to one match+group,
  sorted by points) → table on `portal/guess/detail.html.twig`.
- **Effort: S–M.** **Test:** query test.

---

## P2 — Product/asset decisions or moderate backend

### 7. Premium teaser UI (visual only, feature-flagged)
- The DS gates pick distribution behind a gold "PRÉMIUM" teaser
  (`wtips:open-premium`). Until the commerce backend (P3) exists, P1#2 ships
  distribution **free**. If a teaser is wanted as a marketing hook, build it
  behind a feature flag with no real payment. **Effort: S** (frontend only).

### 8. Δ (rank change) column
- Needs historical rank storage. **Add:** a lightweight periodic leaderboard-rank
  snapshot entity + a scheduled command, then a `Δ` column. **Effort: M.** Omit
  until there's somewhere to snapshot from.

### 9. Brand assets: favicons + `og-default.png`
- Still the old brand (binary). Regenerate from `assets/images/logo/logo-mark.svg`
  (the gradient "W") → favicon set + a 1200×630 OG image; drop into `public/`.
  `theme-color` + all text already rebranded. **Effort: S** (design/export).

### 10. Profile / tip-history page (cross-group)
- The member-breakdown is per-group. A true cross-group tip history needs a new
  query. **Effort: M.** Re-skinned profile + per-group breakdown already cover the
  basics.

---

## P3 — Deferred (large new subsystems / explicitly cut)

| Feature | Why deferred | Needs |
|---|---|---|
| **Premium paywall + contributions/pricing** (10 Kč/hráč, 50/100/200 Kč tiers) | Whole commerce subsystem | Payment provider (Stripe/GoPay) + entitlement entities + pricing + webhooks + billing-after-lock |
| **Payouts / Výplaty / prize bank** | Design walked back ("bez sázek") | Pot/escrow tracking on Group + settlement; reinstate a nav item only if pursued |
| **Goalscorers + match timeline + "Trefený střelec" rule** | New entities + new scoring rule | `MatchEvent`/scorer entity + scorer guess + a `Rule` impl + organizer scorer editor |
| **Bracket / pavouk viz + group-stage tables** | DS never draws them (labels only) | Round/stage + advancement linkage + rendering (use P1#5 label first) |
| **Notifications feed + nav bell** | No backend beyond email | Notification model + feed (bell is intentionally omitted from nav) |
| **Social login (Google/Apple)** | Cut by user | OAuth bundle + provider config + user-linking + callbacks |
| **Trash-talk feed, funny badges (Smolař/Šťastlivec)** | Gamification | Comments model / badge rules |
| **Live match** (real-time score/minute) | Cut by user; marketing decoration only | Live data source + Mercure/polling + match-event model |

---

## Suggested order to tackle P1
1. **Stats (#1)** — biggest DS-fidelity win; data exists; touches leaderboard +
   dashboard which are the most-seen player screens.
2. **Pick distribution (#2)** — signature element; clean read query.
3. **Soutěž switcher (#3)** + **Zápasy page (#4)** — round out the IA the DS nav
   implies.
4. **Round label (#5)** — the only schema change; do it carefully (generated
   migration), unlocks proper tip-card headers.
5. **Per-match ranking (#6)** — nice-to-have on the single-match page.

Keep each as its own commit on a feature branch off `redesign/wtips` (or
`redesign/wtips` directly), run `composer quality` per item, and update the
"build now / deferred" lists in `04-features.md` as items land.
