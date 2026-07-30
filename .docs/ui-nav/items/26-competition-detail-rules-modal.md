# Item 26 — competition detail: „Pravidla" modal, no boost card, no team pills

**Status:** TODO — **blocked on items 22 and 23**, which own the files this touches. Do not start it
before both have landed.
**Filed:** 2026-07-30, from the product owner, on
`/souteze/019fae50-f5af-70b7-a767-ff28a08b2ef1`.

## The instruction (verbatim)

> „On competition detail (/souteze/019fae50-…) you can delete the card „Získej výhody" completely
>
> add button „Pravidla" that will show scoring rules for the competition in modal
>
> Completely remove the info about source: „TÝMY SOUTĚŽE" (do not want to see it, makes no sense)"

## 1. Delete the „Získej výhody" card

„Získej výhody" is **`Boost:Panel` in its default shape** (`feature = null` — see the component's own
props docblock), called at `templates/portal/competition/detail.html.twig:428` inside
`<div id="vylepseni">`. Remove the call site and the wrapper.

**`Boost:Panel` itself stays** — its other shapes are load-bearing: `feature="others"` renders the
locked paywall, and `shape="bare"` is the CTA under a match. Only this page's sidebar shape goes.

**This leaves three dangling references. All three must be fixed in the same commit** (`PLAN.md`:
nothing inside the app may 404 or 500, and an anchor to a deleted element is just as broken):

1. **`#vylepseni` is a jump target.** `Boost:Panel` renders a jump link „to what they unlocked" for an
   owned boost, and the locked surfaces link players here to buy. `grep` for `vylepseni` across
   `templates/` and `src/` and resolve **every** hit — an anchor, a `path(..., {_fragment: …})`, or a
   redirect.
2. **`templates/portal/competition/_boost_intro_modal.html.twig:52`** tells a new member „Koupit je
   můžete kdykoli níže na téhle stránce v sekci „Získej výhody"". After this change that sentence is
   **false**. Rewrite it to point at where a boost is actually bought now (the paywall on the match
   itself). Keep it to one sentence and keep „1 kredit = 1 Kč".
3. **`UI-MAP.md`** lists `Boost:Panel` (`#vylepseni`) as the last section of competition detail.

**Confirm a boost is still purchasable** before you finish — the inline paywalls and the bare CTA on
match detail are meant to cover it. If after removing the card there is any state in which a player
who wants a boost cannot buy one, **stop and report it**; do not invent a replacement entry point.

## 2. Add a „Pravidla" button opening a read-only modal

The scoring rules of a competition are configured per competition (`CompetitionRuleConfiguration`)
and today a player can only see them on `/souteze/{id}/nastaveni`, mixed in with organizer controls.

- **Reuse `templates/_partials/competition_rules.html.twig`.** It already renders the read-only list
  and is used by `templates/portal/competition/settings.html.twig:350` as
  `{{ include('_partials/competition_rules.html.twig', { items: rule_items }) }}`, fed by
  `$ruleConfiguration->items` (`CompetitionSettingsController:124`). **Include it — do not hand-copy
  its markup.** If it needs a tweak to sit in a modal, change the partial so both call sites benefit.
- The detail controller must load the same rule configuration and pass the same `items`.
- **Do not confuse this with the existing `competition_rules` route** (`/souteze/{id}/pravidla`,
  `rule_configuration.html.twig`) — that is the organizer's **edit form** and it stays exactly as it
  is. This is a read-only view of the same data.
- **Every viewer who can see the page must be able to open it.** Scoring rules are what a player
  needs to understand their points, so this button is **not** gated by an organizer voter.
  ⚠️ Today the 4-item action bar renders only for organizers („A plain member sees **no** action
  bar" — UI-MAP). **Orchestrator's judgement, cheap to reverse:** put „Pravidla" in that bar and let
  the bar render for **everyone**, holding just „Pravidla" for a plain member while Nastavení /
  Pozvat / Tipovat za členy / Uzamknout tipy stay behind their own voters. If that looks wrong once
  built, say so in your report with what you would do instead.
- **Modal mechanics:** there is no generic „open a dialog" controller today. The two `<dialog>`
  precedents are `confirm_controller.js` (destructive form submits — the wrong shape here, it wraps a
  form) and `boost_intro_controller.js` (a dialog shown once per member, dismissed with a POST — also
  the wrong shape). So add a **small new Stimulus controller** that does nothing but `showModal()` /
  `close()` a native `<dialog>`. A separate controller file is low-collision by design (`PLAN.md`).
  Reuse the existing modal chrome — `.modal-panel` / `.modal-backdrop` and whatever the boost intro
  uses — rather than inventing a second dialog skin. Keep it keyboard-closable (Esc, and a visible
  close control), and give the dialog an accessible name.
- If it needs a style, follow CSS discipline: new rules at the END of the section they belong to under
  a `/* --- item 26: pravidla modal --- */` comment, never interleaved, never reordering existing
  rules. Prefer a Tailwind utility in the template for a one-off.

## 3. Remove „Týmy soutěže" completely

`templates/portal/competition/detail.html.twig:169–180` — the `{% if filter_teams is not empty %}`
block rendering the eyebrow „Týmy soutěže" (with a `lucide:shield-half` icon) and one `TeamFlag`
pill per filter team. Delete the whole block, not just the label.

Then `filter_teams` is unused: remove it from `CompetitionDetailController` too, along with any
repository call and `use` import that existed only to build it (phpstan level 8 will find the unused
dependency).

**What must NOT be touched:** B4's „Proč tu nejsou všechny vaše soutěže" panel on the **match** page
also spells out a Teams-mode competition's filter teams, and that is a different surface answering a
different question („why is this match not in my soutěž?"). It stays. So does
`CompetitionTeamFilterRepository::teamViewsFor` and the team filter itself — this removes a **display**,
not the feature. The competition's match scope is still `teams`, `CompetitionMatchProvider` still
resolves it, and `/souteze/{id}/zapasy-vyber` still edits it.

## What must NOT change

- The rest of competition detail's order (item 19): header → popis → „Pozvat kamaráda" → batch banner
  → „Tabulka soutěže" + match list (5 rows + „Načíst všechny zápasy") → Žebříček (`#zebricek`).
- **Premium XOR boosts** — one `monetization` column. Removing the card changes no funding model.
- **Managers and admins get no free entitlement pass** (`CompetitionEntitlements`). A rules modal
  reveals scoring configuration, never anybody's tips.
- **B6**: a boost cannot be bought for a fully-over competition. If any purchase surface survives on
  this page, that guard survives with it.
- Czech in the UI, English in code, identifiers and comments. No „sázka" in any form. Prices only
  from `Credits/PricingConfig`. Points/rules are per competition.

## Acceptance criteria

1. `/souteze/{id}` renders 200 with **no** „Získej výhody" card and **no** „Týmy soutěže" row, for an
   organizer, a plain member and (where applicable) an admin.
2. `grep` finds no dangling `#vylepseni` reference anywhere, and the boost-intro modal's sentence is
   true again.
3. A boost is still purchasable — name where, in your report.
4. „Pravidla" appears for **every** viewer of the page and opens a modal listing that competition's
   configured rules and their points, rendered by the shared partial. Esc and a close control both
   dismiss it.
5. A Teams-mode competition still filters its matches correctly (the display is gone, the scope is
   not).
6. `composer quality` clean — including no unused controller dependency left behind by §3.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load the page as all three roles.** `composer quality` does not catch a Twig error; it passes on
  a page that throws at render time. Check the browser console too — since JS-off support is deferred
  (`PLAN.md` decision 0), a Stimulus controller that fails to connect is only caught there, and this
  item adds one.
- Open the modal, close it with Esc **and** with the control, re-open it. Confirm it traps nothing it
  should not and that the page behind it does not scroll away.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Portal/Competition` plus
  `tests/Integration/Portal`. Several tests assert competition detail's sections — expect
  `CompetitionDetailPassTest` and the boost tests to need updating. **Never run `phpunit tests/`
  whole — it OOMs (exit 137).** Strip ANSI before grepping.
- Add coverage for criterion 4 (a plain member can open the rules) — it is the one that would rot.
- Measure the header's spacing after §3 removes a row, at desktop and 320 px, rather than eyeballing.
- After `composer db:reset` you **must** `docker compose restart web`. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.

## Assumptions made (implementer, 2026-07-30)

### 0. §1 was NOT implemented — the stop condition in §1 fired

**„Získej výhody" is still on the page, and it has to stay until a boost shop exists
somewhere else.** The item's own instruction — *„If after removing the card there is any
state in which a player who wants a boost cannot buy one, **stop and report it**; do not
invent a replacement entry point"* — is exactly what happened.

`Boost:Panel` has **three** call sites and only one of them is the sidebar shape:

| Call site | Props | Sells |
|---|---|---|
| `portal/competition/detail.html.twig` (the card §1 deletes) | `feature = null` | **all three boosts** |
| `portal/leaderboard/matrix.html.twig` | `feature="others"` | `others_tips` |
| `portal/competition/match_detail.html.twig` | `feature="others" shape="bare"` | `others_tips` |

Plus `Match:TipStats`, which sells `tip_distribution` (a hidden `<input name="type"
value="tip_distribution">` in both its compact strip and its full card).

So after the deletion: `tip_distribution` ✅ (TipStats, everywhere a match renders),
`others_tips` ✅ (matrix + match detail), and **`tip_change` („Počkejte si na sestavy") has
no purchase surface left anywhere in the app** — `grep -rn 'name="type"' templates/` returns
five hits and not one of them can post `tip_change`. It is also not auto-granted
(`PurchaseBoostHandler` explicitly excludes managers from it), so nobody would get it any
other way. Deleting the card silently withdraws a boost the pricing page still advertises
(`public/pricing.html.twig` names it and quotes `pricing.boostTipChange`).

That needs a product decision this item does not contain — either a home for the boost shop
(a „Vylepšení" block under Nastavení? a third inline paywall on the batch-tips page, where
„Měnit tip" is the thing the player is being denied?) or an explicit decision to retire
`BoostType::TipChange`. **Consequences of not doing §1:** the `#vylepseni` anchor, the
boost-intro sentence and the UI-MAP line all stay TRUE, so none of the three dangling
references had to be fixed. §2 and §3 are fully independent of §1 and are done.

### 1. „Pravidla" for everyone — the orchestrator's call held up

The 4-item action bar now renders for **every** viewer, holding just „Pravidla" for a plain
member. Nothing else changed about it: Nastavení / Pozvat / Tipovat za členy / Uzamknout
tipy each stay behind their own voter. The wrapper `<div>` was always rendered
unconditionally (only its contents were gated), so a plain member went from an empty flex
box to a one-button bar — no layout work, no new breakpoint. Measured: the bar is 34 px
tall at 1440 px and at 320 px, no horizontal overflow at either.

### 2. The shared partial gained a `bare` mode rather than being hand-copied

`_partials/competition_rules.html.twig` renders its own `.card-glass` section with a
„Pravidla bodování" header — a card inside a `.modal-panel` would be a double border. It now
takes an optional `bare` (default `false`) that drops the chrome and returns just the list,
with the `<li>` markup captured once in a `{% set rows %}` so neither shape can drift.
`settings.html.twig` passes nothing and is byte-identical in output.

**Bare mode also renders an empty state** („Pro tuhle soutěž zatím nejsou nastavená žádná
bodovací pravidla."). The card shape deliberately renders *nothing* when no rule is enabled —
right for a page section, wrong for a dialog the viewer opened on purpose.

### 3. One new generic Stimulus controller: `modal`

`assets/controllers/modal_controller.js` — `open()` / `close()` on a server-rendered
`<dialog>`, plus a backdrop click wired by hand (a native modal `<dialog>` does not close on
one), exactly as `confirm_controller.js` does it. No state, no form, no content generation,
so any future „open this panel" button can reuse it. Chrome is `.confirm-dialog .modal-panel`
— **`assets/styles/app.css` is untouched by this item**, so there is no `/* --- item 26 --- */`
block to review.

**One deliberate divergence from the other two dialogs:** the controller freezes the document
while the dialog is open (`overflow: hidden` on `<html>`, plus scrollbar-width padding so
nothing jumps) and releases it on the `close` event — the single funnel every dismissal ends
in, so the freeze cannot outlive the dialog; `disconnect()` releases it too. Measured before
adding it: with an open `<dialog>`, `window.scrollY` moved 0 → 400 on a wheel event, i.e.
the page behind scrolls under every dialog in this app today. Fixing that for `confirm` and
`boost-intro` as well would be a change to shared behaviour that this item did not ask for —
**worth a follow-up row** if one dialog vocabulary is meant to include one scroll behaviour.

### 4. No icon import was needed

The button uses `lucide:activity`, which the rules partial's own header already uses — the
button and the panel it opens now carry the same mark. `assets/icons/lucide/` is unchanged.

### 5. The two boost-intro tests needed their `dialog` selector narrowed

Competition detail now has **two** `<dialog>`s, and `CompetitionDetailPassTest` /
`BoostIntroJoinLandingTest` asserted against a bare `dialog` selector, which after this item
matched the rules modal first. Both were re-pointed at
`dialog[data-boost-intro-target="dialog"]` (a new `INTRO_DIALOG` constant in the former; the
existing `DIALOG` constant in the latter). No assertion was weakened — they got more specific.
