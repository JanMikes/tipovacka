# 17 — App chrome: credits in the bar, a simpler footer, and two overflow bugs

> **Status:** DONE — `09c9d21` (A + B18 + B20), `90f7fb8` (C, the footer)
> **Depends on:** B14/B15 (`26462b4`), which added `{% block navigation %}` / `{% block page_footer %}`
> to `base.html.twig` and made the airlock chrome-free. Do not undo that.
> **Owner decision date:** 2026-07-30

## Why

Four product-owner requests that all land on the same two files — the top bar and the footer — so they
are one item. Doing them separately would mean measuring the same crowded bar three times.

They are, verbatim:

> in the header i want to see my credits and clicking on it takes me to the credit buying page
> malformed notifications overflow on mobile *(B18)*
> In the footer i need to remove the links (when unauthenticated) „Časté otázky", „Funkce", „Ceník",
> „Pro firmy" — make just simpler version of footer. Not „V Praze" but „Ve Frýdku-Místku", really simple

plus **B20**, found independently by two agents while measuring other things: the nav overflows the
viewport at 320 px on **every** page.

## What changes

### A. Credits in the top bar → the buying page

The viewer's credit balance becomes visible in the bar itself and links to `credits_buy`
(`/kredity/koupit`). `UI-MAP.md` §6 has carried this as a known IA pain point since the map was written:
*„**Kredity is hidden** — reachable only from the avatar dropdown / mobile menu."* This closes it.

**The bar is already crowded and it is sticky glass, so it is on screen at all times.** The logged-in
variant holds brand · bell · „Vytvořit soutěž" CTA · avatar · (mobile) hamburger. Adding a sixth element
needs a deliberate answer, not a squeeze — and see B20 below, which says the bar *already* does not fit
at 320 px. Decide what gives way on narrow screens and say so.

`CreditBalance` is an existing Live Component rendered in the avatar dropdown and the mobile menu. Reuse
it or its query; **do not add a second source of truth for the balance**, and do not introduce a
per-request query on a component that renders on every page — check what it costs before placing it.

Prices and amounts come from `Credits/PricingConfig`; the balance comes from the wallet. Never a literal.

### B. B18 — the notification dropdown is positioned off-screen

Opening the bell on a phone renders the panel **past the left edge of the viewport** — the heading
„Oznámení" is clipped to „ení" — while its right edge stops mid-screen. The content renders correctly;
only the position is wrong. **It reproduces in the admin shell too**, so the fix belongs to the shared
`Notification:Bell` component (or its CSS), not to one layout.

**Diagnose; do not pattern-match.** The likely mechanism is a panel right-aligned to a trigger sitting
near the middle of a narrow bar, so it overflows the opposite edge. That is a **different** mechanism
from **B3** (a dropdown cropped by a clipping ancestor, fixed by re-parenting to `<body>`) even though
the symptom rhymes — read B3 so you can tell them apart. Verify at 320/360/390/430 px, in **both**
shells, with the panel empty and with several notifications.

### C. A much simpler public footer

Target the **`marketing`** variant of `templates/components/Layout/Footer.html.twig` — what an
unauthenticated visitor sees. (The `app` variant is already a single mini row with only „Ochrana
soukromí".)

- Drop **Funkce · Ceník · Pro firmy** (the „Produkt" column) and **Časté otázky** („Společnost").
- Keep: the brand block, **Soutěže**, the Účet column (Přihlášení / Registrace, or Nástěnka / Profil),
  **Ochrana soukromí**, the copyright line.
- **„Vyrobeno v Praze." → „Vyrobeno ve Frýdku-Místku."**
- „really simple" — a four-column grid is no longer justified by two surviving links. Collapse it; the
  `app` variant is the house precedent for what simple looks like here.

**State this consequence in your report, do not act on it.** Those four routes are `noindex, nofollow`
(item 01) and the footer is their main entry point. Afterwards the only path in is a chain from the
homepage's single „Funkce" CTA → `/funkce` → `/cenik` → `/pro-firmy` and `/faq`. Nothing 404s, so the
stream's hard constraint holds — but whether those four pages should exist at all is an open product
decision (`ROUND2.md` batch 15). **Do not delete them.**

### D. B20 — the nav overflows at 320 px

Two independent measurements: `HEADER.wtnav > .bar > .actions` at **373 px** in a 320 px viewport on
public pages, and **+7 px** past the viewport on `/nastenka`, `/souteze`, `/zapasy`, `/kredity`. So it
affects **both** nav variants and every page; it is fine again by 430 px.

Fix it **together with A**, since A adds an element to the same row. Measure, do not eyeball.

## Out of scope

- **Do not undo the airlock's chrome-free rendering.** `/overeni-ceka` overrides `{% block navigation %}`
  and `{% block page_footer %}` to render a brand-mark-only header and no footer (B14). Whatever you add
  to the bar must **not** reappear there — a credit balance on the verification airlock would be
  precisely the „advertising pages the guard will bounce you off" problem B14 fixed. Verify it.
- The PIN bar's presence on `/souteze`, `home.html.twig` and the Nástěnka empty state (B15) — leave it.
- `/kredity`'s own 523 px table at 320 px is **B22**, a separate row. Do not fix it here.
- No change to who may see what. If you touch `tests/Integration/Security/AnonymousReachabilityTest`,
  you have changed the security posture and gone wrong.

## Acceptance criteria

- [ ] A logged-in viewer sees their credit balance in the top bar; clicking it lands on `/kredity/koupit`.
- [ ] The balance is **absent** for an anonymous visitor and **absent on `/overeni-ceka`**.
- [ ] The bell's panel is fully within the viewport at 320/360/390/430 px, in the portal **and** admin shells, empty and populated.
- [ ] The nav produces **zero** horizontal page overflow at 320 px, in **both** variants, on `/`, `/nastenka`, `/souteze`, `/zapasy`, `/kredity`.
- [ ] The public footer has no Funkce / Ceník / Pro firmy / Časté otázky links, is visibly simpler, and says „Vyrobeno ve Frýdku-Místku."
- [ ] The four marketing routes still resolve (they are simply less linked) and nothing in the app 404s.
- [ ] Mobile menu still works and still reaches Kredity.

## Verification

```bash
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/Auth
docker compose exec web vendor/bin/phpunit tests/Integration/Public
docker compose exec web vendor/bin/phpunit tests/Integration/Portal
docker compose exec web vendor/bin/phpunit tests/Integration/Security
```

Never `phpunit tests/` whole — it OOMs (exit 137). Chunk; strip ANSI codes before grepping.
`tests/Integration/Auth/NavigationTest.php` locks the exact link sets for both variants (item 01) and
**will** need updating — update it to the new truth, never weaken it.

**`composer quality` cannot see any of this.** Measure in a real browser: bounding boxes for the bell
panel and the nav row at each width, in both shells and both variants; the footer at 1440 and 430 px.
Report numbers. If you count wrapped lines, `getClientRects().length` on a **block** element is always 1
— use a `Range` over the contents, clustered by vertical centre.

After `composer db:reset` you **must** `docker compose restart web`. Prefer not to reset — other agents
may be verifying against the same database. Never run `asset-map:compile`; if assets look frozen,
`rm -rf public/assets` then `docker compose restart web`.

## CSS discipline

You own **`assets/styles/app.css`** this round — the highest-risk file in the repo (one hand-written
`@layer components`, so two agents can redefine a class with no git conflict). Reuse first; new rules go
at the **end of the section they belong to** under `/* --- item 17: chrome pass --- */`; never reorder or
reformat existing rules. The nav rules are `.wtnav` / `.wt-mobile`.

## Git — read this, it bit an agent today

**Commit with `git commit -o <path> [<path>…] -m …` (`-o` = `--only`). Do NOT use `git add` + `git
commit`.** An agent staged explicit paths, verified with `git diff --cached --stat`, and its commit
**still swept in another agent's `assets/styles/app.css`** — a sibling staged into the index between the
`add` and the `commit`. Verifying the index proves nothing: it is shared mutable state another process
can write to after you look. `-o` is index-independent.

Never `git add -A` / `git add .` / `git commit -a`. **Do not run `composer cs:fix` repo-wide.** Another
session also commits here: `git pull --rebase` if a push is rejected, never force-push. Split into
sensible commits (chrome/credits, B18, footer) rather than one lump.

## Files another agent owns right now — do not touch

- `templates/public/leaderboard.html.twig`, `templates/public/competitions_list.html.twig`,
  `templates/design/styleguide.html.twig`, `src/Controller/DesignStyleguideController.php`,
  `LeaderboardTimeFilter` and the leaderboard/competition queries — the item-15 agent.
- **`templates/home.html.twig`** — the item-16 agent.
- `.docs/ui-nav/PLAN.md`, `UI-MAP.md`, `BUGS.md`, `ROUND2.md`, `.docs/DOMAIN.md` — the orchestrator.
  Report deltas as exact text; do not edit.

## Assumptions made

- **The chip links to `/kredity#dobit`, not to `credits_buy`.** `credits_buy`
  (`/kredity/koupit`) is **POST-only** — it is the Stripe checkout action
  (`BuyCreditsController`), so a `<a href>` to it would answer **405**, which the stream's
  „nothing may 404/500 from inside the app" constraint forbids. The buying *page* is
  `/kredity`, whose „Dobít kredity" card holds that very form; the card got `id="dobit"`
  (+ `scroll-mt-24`) so the chip lands on it. Verified by clicking: `/kredity#dobit` with
  the card in view at 320 and 1440 px.
- **What gives way in the bar, in order** (B20 + the sixth element):
  1. **≤1100 px, app bar only** — the „Administrace" and „Vytvořit soutěž" labels
     (`.wtnav.is-app .cta-label`); both keep an icon plus `title`/`aria-label`.
  2. **≤900 px** (the existing hamburger breakpoint) — the **avatar dropdown** and the
     **„Vytvořit soutěž" CTA**, because the hamburger panel repeats everything they hold.
     The panel therefore gained a spelled-out „Vytvořit soutěž" entry. Actions gap 14 → 10.
  3. **≤420 px** — the **wordmark** (`.brand-name`; the mark stays and still links home,
     the airlock's brand-mark-only header is the house precedent) and the secondary
     **„Přihlásit se"** in the public bar, which the panel also carries. Bar padding 18 → 14.
  The brand mark, the balance, the bell and the hamburger never leave.
- **The chip shows the number only** — wallet icon + balance, no „kr." / „kreditů". The unit
  would cost ~30 px in a row that had none to spare; the accessible name says it in full
  („Kredity: 35. Dobít kredity.") and `title="Kredity — dobít"` covers the mouse.
- **The balance left the avatar dropdown** rather than appearing in two places: the dropdown
  row and the hamburger entry are now plain links to `/kredity`. `CreditBalance` renders
  **exactly once** per request, so the bar costs no extra query.
- **`CreditBalance` memoizes its query.** Measured with the getter instrumented: the wallet
  was read **3×** per authenticated page BEFORE this item (Twig resolves a hooked property
  twice per `{{ this.balance }}` and `expose_public_props` reads it once more) and would have
  been 5× with the chip's two accesses. Memoized per instance it is **1×**. Safe under
  FrankenPHP worker mode because Twig components are `Shared: no` — a fresh instance per
  render, so no value can leak between requests or users.
- **B18's narrow panel is pinned to the bar's right edge, not to the bell.** Anchoring it to
  the trigger is exactly what broke it, and the trigger's position depends on what else the
  bar holds — pinning to the edge cannot break again when the bar changes. It also gets
  `max-height: calc(100dvh - 70px)`, because a `fixed` panel cannot be scrolled into view
  (checked at 320×480: 410 px tall, fully inside).
- **The footer keeps the copyright line verbatim**, „Vše hraje, nic se nesází." included —
  the item lists it as kept. The four-column grid's **marketing paragraph** („Tipovací
  soutěže pro firmy … Bez sázek, jen pro radost a vychloubání.") was dropped: „really
  simple" is the request, and the `app` variant — the stated precedent — carries no such copy.
- **The footer's brand coin was dropped too.** `.brand-mark` is styled only inside `.wtnav`,
  so in the footer it had always rendered as a stray unstyled „W" beside the wordmark.
  Duplicating the eight declarations under `.wtfoot` would have been the alternative; the
  simpler footer is the point of the item.
- **A latent bug in the same bar was fixed in passing** (measured, not reported): between
  **768 and 900 px** the public „Registrace zdarma" button rendered with **no label at all**
  — a 30 px empty pill — because the fallback span was `md:hidden` (flips at 768) while
  `.cta-label` hides at 900. Both labels now share one breakpoint via `.cta-short`, pinned by
  `NavigationTest`.
