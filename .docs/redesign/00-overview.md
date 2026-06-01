# 00 — Overview, scope & decisions

## Vision

Turn the current **light navy/cyan** tipovačka into **Wtips**: a dark,
cinematic, glass-morphism prediction-league product. Deep navy canvas
(`#0f1726`), one reserved electric-blue accent (`#4699d0`), Montserrat
everywhere, gradient headlines, ambient glows, tabular numerics, Lucide icons.
Tone: sports studio × Linear changelog. Confident, Czech, vykání, no emoji,
"bez sázek — jen pro radost a vychloubání".

The product is for **organizers** (who run prediction pools around big
tournaments) **and players** (who tip). The same person is often both.

## Scope

### In scope (this redesign)
- Full CSS rebuild from the design system (new dark theme, new component CSS).
- Self-hosted Montserrat.
- Redesigned information architecture + navigation + key user flows.
- Re-skin of **every** template (public, auth, invitation, player portal,
  organizer portal, admin, errors, emails).
- A reusable Twig component library matching the design system.
- A focused set of **feature improvements** that the existing backend data
  already supports (see `04-features.md`).

### Explicitly OUT of scope (deferred or cut)
These are decisions already made — do **not** build them in this redesign:

| Item | Decision | Why |
|---|---|---|
| **Social login (Google/Apple)** | **CUT entirely.** Remove the OAuth buttons + "nebo e-mailem" divider from login/register. Auth stays email/password only. | User directive. No OAuth backend; not wanted now. |
| **Live match** (live scoreboard, in-progress score, minute, live pulse in-product) | **CUT as a product feature.** It appears **only as marketing decoration** on the landing hero. | User directive. No live infrastructure. In-product, matches are Brzy → Uzamčeno → Ukončeno (no live state surfaced). |
| **Premium paywall + contributions/pricing backend** ("Pozvete nás na pivo", 10 Kč/hráč, 50/100/200 Kč tiers, `wtips:open-premium`) | **Deferred** to a future monetization phase. No payment/entitlement backend. | Whole commerce subsystem. Not needed for a visual redesign. |
| **Payouts / Výplaty / prize bank** | **Skip.** Drop the "Výplaty" nav item. | Design intent walked back in chat; "bez sázek, nic se nesází". |
| **Goalscorers / match timeline / "Trefený střelec" rule** | **Deferred.** | New entities + new scoring rule. |
| **Bracket/pavouk visualization, group-stage standings tables** | **Skip.** Only round/stage **labels** (text) are added. | The design never actually draws a bracket or a standings table. |
| **Notifications feed** | **Deferred.** Hide the bell icon until there's a feature behind it. | No notification backend beyond email. |
| **Δ rank-change column** | **Deferred** (or omit) — needs historical rank snapshots. | No rank-history storage exists; don't build snapshot infra now. |
| **Trash-talk feed, funny badges (Smolař/Šťastlivec)** | **Deferred.** | Gamification, future. |
| **Tweaks panel (Vibe/Density/Type pressure)** | **Never build.** | It's a design-authoring tool, not a product feature. |

## Decisions already made (do not re-litigate)

1. **CSS strategy = hybrid.** Keep Tailwind v4 + AssetMapper + `tailwind-bundle`
   (no Node build). Rewrite the `@theme` block to Wtips **dark** tokens, and add
   an `@layer components` "wtips" layer for composite components ported from the
   design system (`.wtnav`, `.btn-*`, `.pill`/`.chip-*`, `.card-glass`,
   `.tip-card`, `.pin-inputs`, `.lb-table`, `.podium`, score inputs, scoring
   fields, modal). Utilities stay for one-off layout. Details: `01-foundation.md`.
2. **Fonts = self-hosted Montserrat (woff2)** via AssetMapper + `@font-face`
   (`font-display: swap`). **Not** the Google CDN (the DS only used the CDN
   because of a preview sandbox limitation). Subset to weights
   300/400/500/600/700/800/900.
3. **Components = anonymous Twig components** for high-reuse primitives, wrapping
   the `@layer components` classes. PHP-backed Live Components keep their
   behavior; only their templates get re-skinned.
4. **Phasing** (full detail in `03-phases.md`): Foundation → Errors+Emails →
   Public+Auth+Invitation → Player portal → Organizer portal → Admin.
5. **Pick distribution is shown FREE** (no paywall now) and only **after a
   match's tip deadline / once locked** (respecting
   `Group.hideOthersTipsBeforeDeadline`). The premium gate is a future phase.
6. **Brand name is config-driven**, not hard-coded, because of dual deployment
   (`main`→wtips.cz shows "Wtips"; the `tipovacka` branch→tipovacka.thedevs.cz
   may keep "Tipovačka"). See `01-foundation.md` §Brand.

> If any of these conflict with reality discovered during implementation, prefer
> the **simpler, shippable** option and leave a `// TODO(redesign):` note + a
> line in `03-phases.md` "Deviations" — do not block.

## Glossary & terminology mapping (CRITICAL)

The design system collapses a two-level domain into user-facing words. The
current backend keeps both levels. Map them **consistently** everywhere:

| User-facing (Czech, Wtips) | Backend entity | Meaning |
|---|---|---|
| **soutěž** (pl. soutěže) | **`Group`** | The pool a player joins to tip a tournament. "Moje soutěže", "Vytvořit soutěž", "Žebříček soutěže". This is the primary unit users interact with. |
| **turnaj** ("zdroj zápasů") | **`Tournament`** | The match-source / template (MS 2026, NHL, UCL). A turnaj has many soutěže (groups). When creating a soutěž you pick a turnaj as its zdroj zápasů (or create one from scratch). |
| **zápas** | `SportMatch` | A match to tip. |
| **tip** (tipnout, tipováno) | `Guess` | A player's score prediction. |
| **organizátor** | `Group` owner / `ROLE` via voter | Runs the soutěž. |
| **hráč / tipující** | `Membership` / `User` | A participant who tips. |
| **přezdívka / @handle** | `User.nickname` | Username shown as `@nickname`. |
| **žebříček** | leaderboard query | Ranking within a soutěž. |
| **body** | `GuessEvaluation.totalPoints` | Points. |
| **přesný tip** | `exact_score` rule fired | Exact score hit (+5). |
| **trefa / částečná** | any non-exact rule fired | Partial hit (+1..+3). |
| **úspěšnost** | derived | Accuracy % = scored tips / total. |
| **streak** | derived | Consecutive scoring tips. |
| **výkop** | `SportMatch.kickoffAt` | Kick-off. |
| **uzávěrka** | group/match tip deadline | When tips lock. |
| **kolo / fáze** | `SportMatch.round` (NEW label field) | Round/stage label ("Skupina A", "Čtvrtfinále"). |
| **pavouk** | — | Bracket (referenced in copy only; not drawn). |

> The single most common mistake will be conflating **soutěž (Group)** with
> **turnaj (Tournament)**. When in doubt: the thing a player is *in* and sees a
> *žebříček* for is a **soutěž = Group**. The thing that *supplies the matches*
> is a **turnaj = Tournament**.

## Information architecture & navigation

### Public (logged-out) — marketing chrome
Sticky glass `.wtnav`:
- Brand: gradient "W" mark + "Wtips" wordmark → `app_home`.
- Links: **Funkce** · **Ceník** · **Pro firmy** · **FAQ** (new marketing pages).
- Actions: **Přihlásit se** (ghost) · **Vytvořit soutěž zdarma** (primary CTA → register).
- Mobile: hamburger (reuse `mobile-nav` Stimulus controller).

### Authenticated — app chrome
Sticky glass `.wtnav`:
- Brand → `portal_dashboard`.
- Primary links (active = white + 600 + 2px accent underline):
  - **Soutěže** → `portal_dashboard` (player's pools + discovery + join-by-PIN).
  - **Zápasy** → **NEW** `portal_matches` (my upcoming/open matches across all my
    soutěže, filterable). See `04-features.md` §Zápasy.
  - **Žebříček** → `portal_leaderboard` for the **selected/primary soutěž**, with
    an in-page soutěž switcher. If the user is in 0 soutěže, route to discovery.
- Actions:
  - **Vytvořit soutěž** primary CTA → create-soutěž flow (create group; if no
    suitable turnaj, the flow offers create-private-tournament).
  - Avatar (gradient initials) → dropdown: **Profil**, **Admin** (if
    `ROLE_ADMIN`), **Odhlásit se**.
  - Bell/notifications: **omitted** for now (deferred).
- Admin keeps its own sidebar shell (`admin/layout.html.twig`), reached via the
  avatar dropdown "Admin".

> Dropped vs current nav: "Turnaje" as a top item is folded into **Soutěže**
> (discovery of joinable competitions lives on the dashboard) and the public
> **Funkce/Ceník** marketing. "Profil" moves into the avatar dropdown.
> Dropped vs design: **Výplaty** (no payouts).

### Soutěž switcher
Dashboard, Zápasy, and Žebříček are **soutěž-scoped** with a switcher
(`.pool-switcher`). Implement as a **server-side** switcher: a `<select>`/menu
of the user's groups that navigates to the same route for the chosen group
(`?soutez=<groupId>` or a path segment). No client-side SPA re-render. The
"active soutěž" can default to the most recently active membership; persist the
last choice in the session for convenience (optional).

## Core user flows (redesigned)

1. **Discover → join (player).** Landing → "Vytvořit soutěž zdarma" or
   "Přihlásit". After login → **Soutěže** dashboard. Join via **8-box PIN card**
   (top of dashboard + on login/register left rail) or via an invite link/email.
   Joining lands on the soutěž dashboard.
2. **Tip a match (player).** From **Soutěže** dashboard "Tvé zápasy" or **Zápasy**
   page → a match row (3-state **tip card**). Inline score steppers →
   **Odeslat tip** / **Upravit tip**. After deadline the card locks (**Uzamčeno**),
   after result it shows **Ukončeno** + points. Detail link → single-match page
   (all members' tips once revealed + pick distribution + per-match ranking).
3. **Track standing (player).** **Žebříček** (podium top-3 + rich table with
   úspěšnost/přesné/trefa/streak + sticky "TY" row) and per-soutěž rank cards on
   the dashboard.
4. **Create & run a soutěž (organizer).** "Vytvořit soutěž" → name + pick turnaj
   (zdroj zápasů) or create from scratch → scoring rules → invite (email chips +
   link + PIN). Manage from the **soutěž detail** (members, invites, PIN/link,
   rules, deadlines, set results, tip-on-behalf-of-members).
5. **Configure scoring (organizer).** Rule configuration screen (presets
   Standardní / Vlastní; the "+střelec" preset is deferred).

## Brand & legal copy (use verbatim where shown)
- Tagline: **„Tipovací soutěže pro firmy, partičky i klubové komunity. Bez sázek,
  jen pro radost a vychloubání."**
- Legal: **„© 2026 Wtips. Vše hraje, nic se nesází."** · **„Vyrobeno v Praze."**
- Reassurances: „Bez sázek a peněz", „Hotovo za 2 minuty", „5 hráčů zdarma navždy".
- Never use „sázka"/„sázkař" (regulatory/casino connotation) — it's **tipy**.
