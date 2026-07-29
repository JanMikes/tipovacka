# Item 01 — Navigation slim-down + noindex on de-linked marketing pages

**Status:** DONE
**Depends on:** nothing
**Blocks:** items 02–05 (they assume the new nav)

---

## Why

The top bar currently advertises five marketing pages (Funkce, Ceník, Pro firmy, FAQ) to logged-out
visitors and three portal destinations to logged-in users, one of which („Soutěže") points at the
dashboard — label and destination disagree. The product owner wants the bar cut to the three things
that matter, and the marketing pages kept in the app but de-emphasised until they are rewritten.

## Scope

### A. Logged-in nav

Primary links become **exactly** these three, in this order:

| Label | Route | Notes |
|---|---|---|
| Nástěnka hráče | `portal_dashboard` (`/nastenka`) | only rendered when `app.user` |
| Soutěže | `public_competitions_list` (`/souteze`) | becomes the context-aware Soutěže page in item 04 |
| Žebříček | `portal_leaderboard` (`/portal/zebricek`) | stays the redirector for now; item 02 replaces it |

**Remove „Zápasy" from the nav.** The route `portal_matches` (`/zapasy`) and its page **stay in the
app** and stay reachable by URL — only the nav entry goes. Do not delete the controller or template.

Right-hand actions are unchanged: Administrace (ROLE_ADMIN, desktop only) · `<twig:Notification:Bell />`
· the „Vytvořit soutěž" CTA → `portal_competition_create` · avatar dropdown. **Keep the CTA exactly
as it is today** — same label („Vytvořit soutěž"), same styling.

### B. Logged-out nav

Primary links become **exactly**:

| Label | Route |
|---|---|
| Soutěže | `public_competitions_list` (`/souteze`) |

„Žebříček" is deliberately **not** added for logged-out users in this item — the public leaderboard
page does not exist yet. Item 02 creates it and adds the link. Do not add a dead link now.

Remove the „Funkce", „Ceník", „Pro firmy" and „FAQ" entries from the bar.

Right-hand actions:
- „Přihlásit se" — unchanged.
- The primary CTA changes from **„Vytvořit soutěž zdarma" → „Registrace zdarma"**, pointing at
  `app_register` (`/registrace`). Keep the identical button styling — only the label and the target
  change.

### C. Mobile panel

`assets/controllers/mobile_nav_controller.js` toggles `.wt-mobile`, which repeats the primary links.
Mirror the same slim-down there: the mobile panel shows the same three (logged in) / one (logged out)
primary links, plus the existing Profil / Kredity / Administrace / Odhlásit se block for logged-in
users, and Přihlásit se / Registrace zdarma for logged-out users.

### D. Keep the marketing pages, de-index them

`/funkce`, `/cenik`, `/pro-firmy`, `/faq` keep their routes, controllers and templates — they are
being kept for later reuse. They must now emit:

```html
<meta name="robots" content="noindex, nofollow">
```

Implement this the cleanest way the existing shell allows: if `templates/base.html.twig` already has
a head/meta block, override it in those four templates; if it does not, **add** a
`{% block meta_robots %}{% endblock %}` to the head of `base.html.twig` and fill it in the four
templates. Do not hard-code the tag into base for every page — only these four are de-indexed.

Also confirm nothing else still links to them in a way that contradicts de-indexing. The **footer may
keep its links** (they are useful to humans); this item is only about the top bar and robots meta.

## Explicitly out of scope

- Building the new Soutěže / Žebříček / Nástěnka pages (items 02–05).
- Changing what `/souteze`, `/nastenka` or `/portal/zebricek` render.
- Touching the admin shell nav.

## Acceptance criteria

1. Logged out, `/` shows a top bar with exactly one primary link („Soutěže"), „Přihlásit se", and a
   „Registrace zdarma" CTA that lands on `/registrace`.
2. Logged in, the top bar shows exactly „Nástěnka hráče", „Soutěže", „Žebříček" plus the unchanged
   right-hand actions, and „Vytvořit soutěž" still opens the create wizard.
3. `/zapasy` still returns 200 when visited directly.
4. `curl -s localhost:58080/funkce | grep -i robots` shows `noindex, nofollow`; same for `/cenik`,
   `/pro-firmy`, `/faq`. A page that is **not** in that list (e.g. `/`) does **not** emit it.
5. The mobile panel matches the desktop link set.

## Definition of done

Per `.docs/ui-nav/PLAN.md`: `cs:fix` → `quality` → the relevant
`tests/Integration/{Public,Portal}` chunks → load every touched page and confirm 200 + expected
markup. Update `UI-MAP.md` §1 (the nav variants table) and §2 in the same commit. Update the status
board row for item 01 to DONE + sha. Commit `UI: navigation slim-down + noindex marketing pages`,
push to `main`.

## Assumptions made

- **The homepage keeps its two „Funkce" CTAs** (`templates/home.html.twig`, hero secondary button
  + closing section). The item scopes the de-linking to the top bar and explicitly allows the
  footer to keep its links, so internal links from `/` were left alone — `noindex, nofollow` on the
  target already keeps the pages out of the index. Revisit when the homepage is rewritten.
- **The logged-out CTA keeps its narrow-screen fallback span** (`<span class="md:hidden">Registrace</span>`
  next to `<span class="cta-label">Registrace zdarma</span>`) — „identical button styling, only the
  label and target change" was read as *do not touch the markup structure*.
- **Active-state matching for „Soutěže" stays route-prefix based** (`public_competitions_list`), so
  no `portal_competition_*` page highlights it. Making the bar aware of soutěž sub-pages is item 04's
  business, not a silent extension here.
- **„Zápasy" was removed from both the desktop bar and the mobile panel**, and no replacement entry
  point was invented — the item says the page stays reachable *by URL*.

## What landed

- `templates/components/Layout/Nav.html.twig` — new link sets (desktop + mobile), CTA label.
- `templates/base.html.twig` — added `{% block meta_robots %}{% endblock %}` (empty by default).
- `templates/public/{features,pricing,for_business,faq}.html.twig` — fill it with
  `<meta name="robots" content="noindex, nofollow">`.
- `tests/Integration/Auth/NavigationTest.php` — locks the exact link sets (both variants), the
  registration CTA, `/zapasy` still 200, noindex on the four pages and *not* on `/`.
