# Wtips redesign — implementation plan

A complete visual + UX redesign of the tipovačka app onto the **Wtips dark design
system** (`~/www/wtips-design-system`). The backend (CQRS domain engine) stays;
the frontend is rebuilt from the design system, the IA/navigation is redesigned,
and a small set of feature gaps is filled.

This folder is the **single source of truth** for the redesign. It is written so
that subagents can execute it autonomously, overnight, without further questions.

## Read in this order

1. [`00-overview.md`](00-overview.md) — vision, scope (in/out), the decisions
   already made, glossary + terminology mapping, information architecture,
   user flows.
2. [`01-foundation.md`](01-foundation.md) — design tokens, CSS architecture
   (Tailwind v4 `@theme` rewrite + `@layer components`), font delivery,
   dead-code cleanup. **This is Phase 0/1 — everything depends on it.**
3. [`02-components.md`](02-components.md) — the Twig component library
   (every reusable primitive: nav, footer, button, pill, card, tip-card,
   leaderboard row, podium, stat card, avatar, team flag, PIN input, …).
4. [`03-phases.md`](03-phases.md) — the phased execution plan, the per-screen
   migration checklist (all 69 templates), acceptance criteria, and the
   subagent orchestration model.
5. [`04-features.md`](04-features.md) — feature improvements to **build now**
   (data already exists) and the explicitly **deferred** backlog.

## Reference catalogs (read-only background)

[`analysis/`](analysis/) holds the deep catalogs produced during discovery.
They are exhaustive references, not instructions — consult them for exact markup,
class names, token values, route names and copy:

- `ds_tokens-css.md` — every design-system token + the old→new token map.
- `ds_components.md` — every DS component with exact markup/CSS (incl. the
  3-state tip card).
- `ds_pages.md` — every DS page (landing, login, register, leaderboard,
  dashboard) section-by-section.
- `ds_organizer-kit.md` — the organizer SPA (pools, pool detail, create wizard,
  match views) + every implied new feature.
- `ds_chat-intent.md` — the design conversation: decisions, glossary, copy/tone
  rules, the requested page set.
- `cur_domain-routes.md` — all 79 routes + the domain model.
- `cur_templates.md` — all 69 current templates, what each renders.
- `cur_frontend-infra.md` — Tailwind theme, Stimulus controllers, JS deps,
  what's coupled to the light theme.
- `_gap.md` — the screen-by-screen gap analysis + migration strategy.

## Golden rules for every implementing agent

- **Run `docker compose exec web composer quality` before declaring any phase
  done.** It must stay green (phpstan L8 + cs-fixer + tests + migrations-up-to-date
  + schema:validate). See `06` in `03-phases.md`.
- **Migrations are generated, never hand-written** (`bin/console
  doctrine:migrations:diff` after entity changes).
- **New Lucide icons must be imported** (`bin/console ux:icons:import lucide:<name>`)
  before use, or dev render throws (`ignore_not_found: false`).
- **No emoji anywhere** — use Lucide icons (the design system forbids emoji;
  the prototype's 🔥/✓ are placeholders, replace with `lucide:flame`/`lucide:check`).
- **Czech copy rules:** vykání, sentence-case headings, UPPERCASE only in
  eyebrows/labels, decimal comma (`1,85`), Czech quotes `„…"`, correct numerals
  (`1 tip / 2 tipy / 5 tipů`).
- **Keep PHP Live Component behavior intact** — re-skin templates, don't break
  `#[LiveProp]`/`#[LiveAction]` wiring.
