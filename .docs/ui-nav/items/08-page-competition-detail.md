# Item 08 — Competition detail: a playing surface, with everything else behind „Nastavení"

**Status:** DONE
**Depends on:** item 05 (Žebříček) is a natural pairing but not a hard dependency.

---

## Why

`templates/competition/detail.html.twig` is a **576-line monolith**: members, my tips,
invitations, a leaderboard CTA, boosts, PIN/link sharing and a „Správa" block all stacked vertically
with no hierarchy. The product owner wants the page to be about *playing* — matches and standings —
with organizer machinery moved out of the way.

## Reference design

- `.docs/ui-nav/screenshots/img09-soutez-detail.png` — the target page
- `.docs/ui-nav/screenshots/img10-above-matches.png` — the „Tipněte si všechny zápasy najednou"
  banner, which sits **above the match list** and which the product owner explicitly wants kept

## The decision — „Everything → Nastavení"

Product owner's answer, verbatim option: **Everything → Nastavení.**

So the detail page loses the „Členové", „Pravidla" and „Správa" blocks entirely, and a **„Nastavení"
destination** gains them. Concretely, everything currently in the Správa block (l. 452-572) plus the
members list (l. 82-150), the read-only rules card (l. 332) and the invitation/PIN/link machinery
(l. 219-306 and l. 334-450) moves there:

- Členové (list, ranks, promote anonymous → e-mail, remove member)
- Pravidla bodování (the read-only card **and** the link to `competition_rules`)
- Upravit soutěž · Výběr zápasů / Týmy soutěže · Prémium (enable / switch to boosts)
- Rychlé pozvánky (PIN + shareable link, with regenerate/revoke)
- Opustit soutěž · Smazat soutěž

**No capability may be lost — but routes are free to move.** There are no users yet and the stream
owes no backwards compatibility (see `PLAN.md` conventions), so you may rename, merge or delete
routes to get a clean settings area; you may **not** drop a command, a voter or an ability the
organizer has today.

That means: if it reads better as one sectioned `/souteze/{id}/nastaveni` page that absorbs
the smaller forms (rules, member management, PIN/link) and links out only to the genuinely large ones
(match selection, premium), do that and **delete** the routes you absorbed. Do not leave a
half-migrated settings area with some things inline and some behind links for no reason.

Whatever you delete: `grep -rn` the route name first and fix every `path()` call, test and doc in the
same commit. Nothing inside the app may 404. Record the resulting structure in `## Assumptions made`.

## The new top bar

Header: back link, eyebrow („MS VE FOTBALE 2026 · MATCHDAY 3 Z 14"), the competition name with a
LIVE pill, and the role badges (ORGANIZÁTOR / HRÁČ). On the right, four actions:

| Action | Target | Visible when |
|---|---|---|
| **Nastavení** | the new settings destination | `competition_edit` |
| **Pozvat** | invitations (currently the l. 219-306 block) | `competition_manage_join_mechanics` |
| **Tipovat za členy** | `competition_manage_member_tips` | `competition_manage_members and not competition.isGlobal` |
| **Uzamknout tipy** | `competition_lock_tips` | organizer, tips not yet locked |

„Uzamknout tipy" keeps its `confirm` modal and CSRF token id (`competition_lock_tips_<uuid>`), and
flips to „Odemknout tipy" (`competition_unlock_tips`) while `can_unlock_tips` holds — the
controller already computes that. **See `BUGS.md` B2**: this button is also getting an „Ihned / V
určený čas" choice. If B2 has not landed yet, leave the immediate behaviour untouched and do not
pre-empt it.

Everything a non-organizer sees must degrade cleanly — a plain member sees the page with no action
bar rather than an empty toolbar.

## Body layout

**Left / main column**
1. The **„Tipněte si všechny zápasy najednou"** banner (`img10`) → `competition_my_tips_batch`.
   It exists today as the l. 66-80 `.surface-accent` CTA — restyle to the design, keep the target.
2. The match list: tip cards with MŮJ TIP, per-match „Rozložení tipů", and per-match deadline state.

**Right / aside column**
1. **Žebříček panel — with actual rows.** Today (l. 312-325) this is a pure CTA card with no data.
   The design shows a real mini table (rank, avatar, name, @nick, points, delta chip) plus a
   „Všichni · N" / „Přátelé" toggle and „Celý žebříček →". The `.lb-row` markup on the current
   dashboard (l. 92-146) is the right starting point; `Leaderboard/Delta variant="chip"` already
   exists.
2. **Below the žebříček: benefits (boosters).** Product owner: *„in right menu under the žebříček put
   benefits (boosters) — if bought it should be a link to the section to see it, for example see
   guesses of others, or a CTA to buy them."*
   So each benefit renders in one of two states: **owned** → a link that jumps to the thing it
   unlocked (e.g. the others'-tips surface); **not owned** → the purchase CTA. `Boost:Panel`
   (`src/Twig/Components/Boost/BoostPanel.php`) already models owned / superseded / auto-entitled
   states and only renders for `monetization == 'boosts'` — extend it, do not replace it.
   **See `BUGS.md` B6**: no purchase once the competition is over.

## Guard rails

- **Premium XOR boosts** is a single `monetization` column — never render both funding models at once.
- Managers and admins get **no free entitlement pass** (`CompetitionEntitlements`, revertible via
  `$managersSeeTipsForFree`). An organizer viewing the page buys like anyone else.
- On-behalf tipping shows only *whether* a member's tip is filled, never the scores.
- `TipStatsProvider` batched per page, never per row.
- The competition's match set comes from `CompetitionMatchProvider` — do not re-derive it.

## Acceptance criteria

1. The detail page renders header + action bar + banner + matches + žebříček (with rows) + boosts,
   and nothing else.
2. „Členové", „Pravidla" and „Správa" no longer appear on it; every one of their actions is reachable
   from „Nastavení" and still works end to end (invite, remove member, promote anonymous, regenerate
   PIN, revoke link, edit, rules, match selection, premium, leave, delete).
3. A plain member, an organizer, and an admin each see an appropriate action bar.
4. A global competition hides „Tipovat za členy".
5. Boost benefits show a jump link when owned and a purchase CTA when not.
6. Page renders correctly for a premium competition and for a boosts competition.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → `tests/Integration/{Portal,Command,Query}` chunks
(never `phpunit tests/` whole — OOM) → render checks as member / organizer / admin, on a live, a
locked and a finished competition, and on both monetization modes. Update `UI-MAP.md` §2/§3 and §6
(pain point 2). Update the status board row to DONE + sha. Commit
`UI: competition detail — playing surface + Nastavení`, push to `main`.

---

## Assumptions made

**Route structure.** One new route, none deleted — `competition_settings`
(`GET /souteze/{id}/nastaveni`, `CompetitionSettingsController`, declared in
`AnonymousReachabilityTest`). It absorbs the small forms **inline** (členové + promote/remove,
pozvánky e-mailem + hromadně, PIN, sdílený odkaz, read-only pravidla, opustit/smazat) and links
out to the four genuinely large form pages that keep their own controller, template and tests:
`competition_edit`, `competition_rules`, `competition_match_selection`, `competition_premium`.
Nothing was absorbed *from* a route, so nothing had to be deleted — every POST endpoint the old
detail page posted to (`…/pozvanky/odeslat`, `…/pin/novy`, `…/clenove/{id}/odebrat`, …) is
unchanged and still hit from the same markup, only from a different page.

**„Pozvat" is an anchor, not a second page.** The product owner's decision was
*everything → Nastavení*, so a separate invitations page would have re-split what the decision
merged. „Pozvat" points at `/souteze/{id}/nastaveni#pozvanky`, the block that holds e-mail,
bulk, „bez e-mailu", PIN and the shareable link.

**Who may open Nastavení.** The page itself requires `competition_view` and gates every block
by its own voter (so a plain member reaching it by URL sees the roster + pravidla and „Opustit
soutěž", nothing else). The *button* follows the item's table and appears only for
`competition_edit`, so a plain member is never offered it.

**Return-to-origin redirects.** Every action reached from Nastavení now redirects back to
Nastavení instead of the detail page (invite, bulk invite, revoke invitation, remove member,
add/promote anonymous member, PIN + link regenerate/revoke, upravit, výběr zápasů, switch to
boosts, premium-settings guard). Lock/unlock tips, join and leave/delete still land on the
detail page — that is where the user was. Accepting an invitation still lands a **new member**
on the detail page (the playing surface), not on Nastavení.

**Žebříček panel.** Top 7 rows from the already-loaded `GetCompetitionLeaderboard`, plus a
„…"-separated own row when the viewer ranks below that. Rows carry rank, avatar, name, @nick,
points and the `Leaderboard/Delta variant="chip"`. „Celý žebříček" links to
`/zebricek?soutez=<id>` (route `leaderboard`) and each row to `leaderboard_member`; item 05 (the
standalone Žebříček page) landed while this item was in flight, so those are its route names.

**Match list.** All matches of the competition (`CompetitionMatchProvider`, never re-derived),
each with its `EffectiveTipDeadlineResolver` deadline, my tip, the batch `TipStatsProvider`
row, and the points scored (new batch read
`GuessEvaluationRepository::pointsByMatchForUserInCompetition` — one query per page). The old
„Moje tipy" collapsible listed only matches I had already tipped, so an untipped match was
invisible; the new list shows every match with a „+ Zadat tip" prompt
(`Match:MatchRow` gained an optional `tipPrompt` prop that renders the already-existing but
unused `.my-tip.empty` style). No new CSS was needed for this item.

**B6 — „fully over".** Defined as *the competition includes ≥1 match and none of its included
matches is `Scheduled`, `Live` or `Postponed`* — `Finished` (has a result) and `Cancelled`
(never will) both count as settled, an empty scope is not over. Deliberately not „past the last
kickoff": a kicked-off match without an entered result can still move the standings. Implemented
as `CompetitionMatchProvider::isFullyOver()` (so scope always comes from the one authority),
enforced in `PurchaseBoostHandler` via `CompetitionAlreadyOver` (`#[WithHttpStatus(409)]`,
surfaced by `PurchaseBoostController` as a flash + return to origin) and mirrored in
`Boost:Panel`, which drops the price and the CTA for a one-line Czech explanation. Written into
`.docs/DOMAIN.md` §Monetization + a dated decision-log row.

**Owned-boost jump links.** „Lišta tipů ostatních" jumps to the detail page's match list
(`#zapasy`, where the distribution bar renders), „Konkrétní tipy kolegů" to the guess matrix
(`leaderboard_matrix`) and „Měnit tip" to `competition_my_tips_batch`.

**B2 not pre-empted.** „Uzamknout tipy" keeps its immediate behaviour, its `confirm` modal and
its `competition_lock_tips_<uuid>` CSRF token id.
