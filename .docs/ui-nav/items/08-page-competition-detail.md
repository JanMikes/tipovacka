# Item 08 — Competition detail: a playing surface, with everything else behind „Nastavení"

**Status:** TODO
**Depends on:** item 05 (Žebříček) is a natural pairing but not a hard dependency.

---

## Why

`templates/portal/competition/detail.html.twig` is a **576-line monolith**: members, my tips,
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
- Pravidla bodování (the read-only card **and** the link to `portal_competition_rules`)
- Upravit soutěž · Výběr zápasů / Týmy soutěže · Prémium (enable / switch to boosts)
- Rychlé pozvánky (PIN + shareable link, with regenerate/revoke)
- Opustit soutěž · Smazat soutěž

**Do not delete any route, command or voter.** Every existing action keeps working; this is a
relocation of entry points, not a removal of capability. `portal_competition_rules`,
`portal_competition_edit`, `portal_competition_match_selection`, `portal_competition_premium`,
`portal_competition_add_anonymous_member`, `portal_competition_promote_anonymous_member`, the PIN/link
POSTs, leave and delete all stay.

Where „Nastavení" lives is your call — the conservative reading is a new page at
`/portal/souteze/{id}/nastaveni` that sections the above, linking out to the existing dedicated pages
rather than absorbing their forms. Record the choice in `## Assumptions made`.

## The new top bar

Header: back link, eyebrow („MS VE FOTBALE 2026 · MATCHDAY 3 Z 14"), the competition name with a
LIVE pill, and the role badges (ORGANIZÁTOR / HRÁČ). On the right, four actions:

| Action | Target | Visible when |
|---|---|---|
| **Nastavení** | the new settings destination | `competition_edit` |
| **Pozvat** | invitations (currently the l. 219-306 block) | `competition_manage_join_mechanics` |
| **Tipovat za členy** | `portal_competition_manage_member_tips` | `competition_manage_members and not competition.isGlobal` |
| **Uzamknout tipy** | `portal_competition_lock_tips` | organizer, tips not yet locked |

„Uzamknout tipy" keeps its `confirm` modal and CSRF token id (`competition_lock_tips_<uuid>`), and
flips to „Odemknout tipy" (`portal_competition_unlock_tips`) while `can_unlock_tips` holds — the
controller already computes that. **See `BUGS.md` B2**: this button is also getting an „Ihned / V
určený čas" choice. If B2 has not landed yet, leave the immediate behaviour untouched and do not
pre-empt it.

Everything a non-organizer sees must degrade cleanly — a plain member sees the page with no action
bar rather than an empty toolbar.

## Body layout

**Left / main column**
1. The **„Tipněte si všechny zápasy najednou"** banner (`img10`) → `portal_competition_my_tips_batch`.
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
