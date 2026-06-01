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

### 4. "Zápasy" page (cross-soutěž matches) + nav item ✅ DONE
> Implemented: `ListUserMatches` query (`src/Query/ListUserMatches/`) +
> `SportMatchRepository::listAllForUser` (all non-cancelled, non-deleted matches
> across the user's soutěže, any state; ordered upcoming-soonest-first then
> past-newest-first). `Portal\MatchesController` at `/zapasy` (route
> `portal_matches`) does **server-side** filtering — chips Vše / Dnes /
> Tipovatelné / Ukončené (no Live; Live maps to "Uzamčeno") via `?filtr=<key>`,
> with per-chip counts; "Dnes" uses Europe/Prague. `portal/matches/index.html.twig`
> renders the rows (finished shows score, others a state pill + tip-status pill).
> Added a `^/zapasy → ROLE_USER` access_control rule and a "Zápasy" nav item
> (Soutěže · **Zápasy** · Turnaje). Test: `MatchesFlowTest` (anon redirect,
> cross-soutěž aggregation, ukončené + tipovatelné filters).
> **Note:** round/stage label on the rows lands with #5 (schema) below.

### 5. `SportMatch.round` / stage label ✅ DONE
> Implemented: nullable `public private(set) ?string $round` on `SportMatch`
> (declared next to `$venue`; threaded as an **optional last** constructor +
> `updateDetails` param so the ~14 existing call sites compile unchanged).
> Generated migration `Version20260601165752` (single `ALTER TABLE … ADD round`).
> Surfaced end-to-end: create/edit form (`SportMatchFormData/Type` + form.twig),
> CSV import (optional `Kolo (nepovinné)` column — backward compatible, older CSVs
> without it still import; `SportMatchImporter` + `SportMatchImportRow` +
> `SportMatchImportSession` store/consume + import preview table + template CSV),
> and headers: match-detail hero `.round` (fallback = tournament name), Zápasy +
> dashboard match-row meta lines. Fixtures set round on two matches (Čtvrtfinále /
> Základní skupina). DTOs `UpcomingMatchItem` + `UserMatchItem` carry round.
> Tests: `SportMatchEntityTest` (construct/update/clear round), `BulkImportFlowTest`
> (round column stored). `schema:validate` + `schema:update --dump-sql` clean.

### 6. Per-match ranking ("Pořadí za zápas") ✅ DONE
> Implemented: `GetMatchRanking` query (`src/Query/GetMatchRanking/`) — evaluated
> guesses for one match within a group, sorted by points (competition ranking,
> same tiebreak as the leaderboard); rows carry the tip score so the page can show
> a „Přesně" chip when it matches the result. Rendered on
> `portal/guess/detail.html.twig` as a `.lb-table` section, gated on
> `can_see_all_tips` **and** the match being finished (no evaluations otherwise).
> Tests: `GetMatchRankingQueryTest` (finished match → 1 row rank 1, +3; scheduled
> → empty) + `SportMatchGuessesFlowTest::testShowsPerMatchRankingForFinishedMatch`.

---

### 7. Podium top-3 on the leaderboard page ✅ DONE
> Implemented: `components/Leaderboard/Podium.html.twig` (anonymous component, an
> internal `{% macro %}` per pod) rendering the existing `.podium`/`.pod` DS grid —
> silver · gold (raised, larger avatar) · bronze — with medal label, Avatar (medal
> gradient), name/@handle, big points + micro-stats (Přesné / Úspěšnost / Streak).
> `GroupLeaderboardController` now fetches `GetGroupLeaderboard` once (also reused
> for the winner banner) and passes the top-3 to the page **only when ≥3 players and
> the top has > 0 points**. Rendered above the live leaderboard table. Tests:
> `PodiumFlowTest` (renders with 3 players incl. a scorer; hidden with < 3).

---

## P2 — Product/asset decisions or moderate backend

### 7b. Premium teaser UI (visual only, feature-flagged) ✅ DONE
> Implemented: `components/PremiumTeaser.html.twig` (gold „PRÉMIUM připravujeme"
> card — **no payments, no `wtips:open-premium` JS, no entitlements**). Gated by a
> new twig global `premium_enabled` ← env `APP_PREMIUM_TEASER_ENABLED` (default
> `0` in `.env`; flip to `1` to surface it). Rendered on the single-match page
> below the tips list. Pick distribution stays **free** regardless. Test:
> `PremiumTeaserFlowTest` (hidden by default; shown when the env flag is on).
> The real paywall/pricing remains **deferred** (P3 — needs a payment provider).

### 8. Δ (rank change) column
- Needs historical rank storage. **Add:** a lightweight periodic leaderboard-rank
  snapshot entity + a scheduled command, then a `Δ` column. **Effort: M.** Omit
  until there's somewhere to snapshot from.

### 9. Brand assets: favicons + `og-default.png` ✅ DONE
> Regenerated the whole icon/OG set from `assets/images/logo/logo-mark.svg`
> (gradient "W") + `logo-wtips.svg` (wordmark) into `public/`: `favicon.svg`
> (self-contained dark rounded badge + gradient W), `favicon-96x96.png`,
> `favicon.ico` (16/32/48/64), `apple-touch-icon.png` (180, full-bleed),
> `web-app-manifest-192/512` (full-bleed — OS applies the squircle mask),
> `og-default.png` (1200×630) + `og-default-square.png` (1200×1200) on the dark
> brand canvas with the wordmark + Czech tagline. `site.webmanifest` updated to
> name "Wtips" + dark `theme/background_color` `#0f1726`. Reproducible via
> `tools/brand/generate-brand-assets.sh` (macOS `sips` + ImageMagick 7; **`sips`
> is the reliable SVG rasteriser — ImageMagick's built-in MSVG renderer mangles
> the gradient and librsvg isn't installed**). Visually verified each output.
> **Full rebrand finalised alongside:** dropped the `APP_BRAND_NAME` env var —
> `brand_name` is now a hardcoded `'Wtips'` Twig global (`config/packages/twig.php`);
> removed it from `.env`; `MAILER_FROM_NAME` → "Wtips"; README H1 → Wtips.
> (Per user: no transition phase, the app is fully Wtips.)
> **Follow-up (not blocking):** `MAILER_FROM_EMAIL` is still `noreply@tipovacka.cz`
> — flip to `@wtips.cz` once SPF/DKIM/DMARC exist for wtips.cz (deliverability).

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
