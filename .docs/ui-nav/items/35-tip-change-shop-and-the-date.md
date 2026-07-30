# Item 35 — sell „Počkejte si na sestavy" where it bites, and name the date

**Status:** TODO
**Filed:** 2026-07-30. Finishes item 26 §1, which stopped on purpose.

## Why this exists

The product owner asked (item 26) to delete the „Získej výhody" card from competition detail. The
implementer stopped and reported instead, because the guard in the spec fired: **that card is the only
place in the app where `BoostType::TipChange` („Počkejte si na sestavy", 50 kr.) can be bought.**
`grep -rn 'name="type"' templates/` finds five purchase forms and none can post `tip_change`;
`PurchaseBoostHandler` does not auto-grant it; and `/cenik` still advertises it by name. Deleting the
card would have silently withdrawn an advertised product.

The other two boosters are already sold at the thing they unlock — `tip_distribution` from the
„Jak tipují ostatní?" strip, `others_tips` from the ranking paywall. This item does the same for the
third, then removes the card.

**Product-owner decision, 2026-07-30:** *„yes but as the textation, there must be date when it opens
again."*

## What to build

### 1. A `tip_change` purchase surface where the player feels its absence

Two call sites:

- **The tip form on the merged match page** (`Guess:GuessSubmitForm`), in its **locked** state — the
  panel that today reads „Tipování uzavřeno — uzávěrka proběhla …".
- **The batch page `/souteze/{id}/moje-tipy`**, where „Měnit tip" is exactly what is being denied.

Reuse `Boost:Panel` — it already has a `shape="bare"` inline CTA used on match detail, and it already
carries the price, the affordability check, the confirm dialog and **B6** („soutěž už skončila").
Prefer teaching it a third `feature` over writing a new paywall. **Do not introduce a second way to
buy a boost.**

Show it only where it can do something: the viewer is **not** already entitled, the competition is
`monetization: boosts`, and the match is not over. An entitled viewer sees no offer.

### 2. The copy must name the concrete moment — and the window is NOT always „1 hodinu"

This is the product owner's condition, and the trap in it: **„Měnit tip" is per match, not per day**,
and the window is the competition's own `tipChangeOffsetMinutes` (default 60, but configurable).
Item 23 already hit this — `Boost:Panel` renders `BoostType::description()` verbatim when the offset
is 60 and substitutes the real offset when it is not. **Do the same here: never hard-code „1 hodinu".**

The paywall must state the **actual moment this viewer would gain**, in Europe/Prague, formatted like
the rest of the app (`j. n. Y H:i`). Resolve it from `EffectiveTipDeadlineResolver` — do **not**
recompute „kickoff minus offset" by hand; that resolver is the single answer to „until when may this
viewer tip this match", and it is currently being modified by the product owner (see below), so read
it, do not change it.

**Assumption, stated so it is cheap to correct:** „date when it opens again" is read as *the deadline
the buyer would get back* — „S tímhle vylepšením můžeš tipovat až do **8. 8. 2026 19:00**." If the
product owner meant something else, this is one string.

### 3. Then delete the „Získej výhody" card

Once §1 ships, finish item 26 §1: remove the `<twig:Boost:Panel :competition="competition" />` call
site and its `<div id="vylepseni">` wrapper from `templates/portal/competition/detail.html.twig`, and
resolve what item 26 found still pointing at it:

- `#vylepseni` — item 31 already re-pointed the owned-boost jump link at `#zapasy`, so re-check what
  is left rather than trusting either item file.
- `templates/portal/competition/_boost_intro_modal.html.twig` — the sentence „Koupit je můžete kdykoli
  níže na téhle stránce v sekci „Získej výhody"" becomes false. Rewrite it to point at where each
  boost is now bought. One sentence; keep „1 kredit = 1 Kč".
- `CompetitionDetailPassTest` asserts `#vylepseni` in four places.
- `UI-MAP.md` line 133 lists `Boost:Panel` (`#vylepseni`) as the page's last section.

**Before you delete it, re-run item 26's check**: for each of the three boost types, name where a
player can still buy it. If any answer is „nowhere", **stop and report** — that is the same guard,
and it is the reason this item exists.

## What must NOT change

- **The product owner is building a feature in this same tree.** These paths are **theirs — read-only,
  do not edit, do not revert, do not commit**: `src/Entity/CompetitionMatchSetting.php`,
  `src/Service/EffectiveTipDeadlineResolver.php`, `src/Exception/GuessNotYetOpen.php`,
  `src/Value/TipWindow.php`, `migrations/Version20260730172923.php`,
  `tests/Unit/Entity/CompetitionMatchSettingEntityTest.php`,
  `tests/Unit/Service/EffectiveTipDeadlineResolverTest.php`. Their work adds an „opens at" moment to a
  match; **if your copy could also state that**, describe what you would write and let the owner
  decide — do not build on an unfinished feature.
- **Premium XOR boosts.** On a premium competition the player buys nothing; the organizer's toggle
  grants it. No offer there.
- **B6** — no purchase for a fully-over competition.
- **Managers and admins get no free entitlement pass.**
- Prices only from `Credits/PricingConfig`. Czech in the UI, English in code. Never „sázka".

## Acceptance criteria

1. A player without the boost, past their window on a `boosts` competition, is offered
   „Počkejte si na sestavy" **on the match page and on `/souteze/{id}/moje-tipy`**, at 50 kr., and the
   copy names the concrete moment they would gain.
2. A competition whose `tipChangeOffsetMinutes` ≠ 60 shows **its** window, not „1 hodinu".
3. An entitled viewer, a premium competition and a finished competition are offered nothing.
4. Buying it works end to end and the tip form opens.
5. „Získej výhody" is gone from competition detail, nothing points at `#vylepseni`, and each of the
   three boosts is still purchasable somewhere — say where.
6. `composer quality` clean.

## Verification

Follow `.docs/ui-nav/AGENT-BRIEF.md`. This is **copy + a control appearing** — so: load the two pages
in both states, check the console, run `tests/Integration/Portal/Competition` and the boost tests. No
multi-width geometry sweep is required unless you change a layout.

⚠️ **The dev database may 500 with `column c0_.opens_at does not exist`** — that is the product owner's
in-progress migration, not yours. Do **not** run migrations, `db:reset` or restart `web`. Verify what
you can, and say plainly what you could not verify and why.

## Commit

`git commit -o <path> [<path>…]`. Push to `main`. Report your sha; do not touch the status board.
