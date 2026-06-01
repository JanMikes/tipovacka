# 07 — Design-system parity: audit-and-fix prompt

Full autonomous prompt for driving the live app to complete Wtips design-system parity:
walk every DS page/subpage/component, validate the app against it, and **fix every gap it
finds** (not just report). Runs as a lean **orchestrator** that delegates the heavy
reading/editing/reviewing to subagents — parallel read-only **scouts** for discovery, then
**strictly sequential** fixers, each gated by a **focused independent review** before commit —
so context stays clean across a long run. Commits per screen directly to `main`.

> ⚠️ `main` deploys to **wtips.cz** (prod). The prompt enforces "only push when the full gate is
> green" — keep that guardrail. No worktrees, no parallel writes: one screen at a time.

## Quick launch (paste this into a fresh Claude Code session in this repo)

```
Read .docs/redesign/07-ds-parity-prompt.md in full and follow it EXACTLY to drive Wtips
design-system parity autonomously. Audit every DS page/component AND fix every gap you find —
don't stop at the matrix. Orchestrate via subagents per that doc (parallel read-only scouts →
strictly sequential fixers → focused review before each commit); keep your own context lean.
Commit per screen directly to main and push only when the full gate is green (main = prod).
Keep .docs/redesign/07-ds-parity-audit.md + 02-components.md + 05-backlog.md current as you go.
Don't ask permission; run long until the matrix has no ⚠️/❌ left, then summarize.
```

---

## The prompt

```
Work autonomously toward DESIGN-SYSTEM PARITY. Walk every page, subpage, and component in the Wtips
design system, validate the live app against it, and FIX everything you find — don't just report
gaps, close them. Reuse the DS's exact components/structure and CSS. For DS elements whose feature
isn't built yet, prepare the visual component as documented reference. You are the ORCHESTRATOR:
delegate the heavy reading/editing/reviewing to subagents to keep your own context lean, and gate
every change through a focused independent review before committing. Commit per screen/component
DIRECTLY TO main, push only when green. Do NOT ask permission. No co-author footer on commits.

⚠️ main deploys to wtips.cz (prod) — ONLY push when the full gate is green (see VERIFICATION).

═══ ORIENT (read first, in this order — do not skim) ═══
1. ~/www/wtips-design-system/README.md — how the handoff bundle is organized.
2. ~/www/wtips-design-system/chats/chat1.md — the user's intent + where they landed. Read DS HTML/CSS directly; do NOT open files in a browser or screenshot them (everything is in the source).
3. The pre-built DS catalogs (already transcribe the DS — your MAP; still diff against raw DS HTML for fidelity): .docs/redesign/analysis/{ds_pages.md, ds_components.md, ds_organizer-kit.md, ds_tokens-css.md, _gap.md}.
4. .docs/redesign/{00-overview.md (glossary soutěž=Group/turnaj=Tournament; IA/nav; SCOPE = CUT vs DEFERRED, governs what you may build), 02-components.md (Twig component spec — names components not yet built), 04-features.md, 05-backlog.md}.

═══ GROUND TRUTH — already DONE, do NOT re-skin or rebuild ═══
The dark migration is complete + green. Tokens + the "wtips" component CSS are ported into
assets/styles/app.css `@layer components` (.wtnav .btn* .pill* .card-glass .surface* .tip-card
.score-input .pin-inputs .dist-bar .lb-table .podium/.pod .avatar .stat .eyebrow). 21 Twig
components exist (Layout/Nav+Footer, Pill, Badge, Avatar, TeamFlag, StatCard, Guess/*,
Leaderboard/GroupLeaderboard+Podium, Match/PickDistribution, SoutezSwitcher, PremiumTeaser, Auth/*,
Profile/ProfileForm, EmptyState, Breadcrumbs). All P1 features + brand assets shipped. Brand is
hardcoded "Wtips". This is an AUDIT-AND-FIX: reuse what exists; touch a screen only where it
diverges from the DS or is missing a DS element. Don't reformat already-correct screens.

═══ ORCHESTRATION & CONTEXT MANAGEMENT (the core of how you work) ═══
Run as a lean orchestrator. Your durable state is .docs/redesign/07-ds-parity-audit.md (the matrix)
+ git history — keep both current so a context compaction never loses the plan. Delegate via the
Task/Agent subagent tool; ask subagents for CONCLUSIONS (gap rows, a diff summary, a pass/fail
verdict), never raw file dumps — never pull whole files into your own context that a subagent could
read for you. Three roles:
- SCOUT (discovery, read-only, PARALLEL is fine — no writes): one subagent per DS surface slice
  (tokens · component catalog · player pages · auth/public · organizer kit · admin). Each reads the
  DS source + the matching app templates + the @layer CSS and returns a STRUCTURED gap list (DS file ·
  app target · status {✅/⚠️/❌/🔮/⛔} · concrete fix). Launch the slices in ONE message. Use
  Explore/general-purpose (or feature-dev:code-explorer) agents.
- FIXER (implementation, STRICTLY SEQUENTIAL — one screen/component at a time, NO worktrees, NO
  parallel writes): hand it ONE screen/component with a SELF-CONTAINED brief (exact DS source path +
  app target path + the conventions from HARD RULES + the @layer classes to reuse). It edits
  templates/CSS, runs the local gate on the files it touched, and returns a tight diff summary.
  Subagents start fresh — put everything they need in the brief. Finish, review, commit, then move to
  the next one. Never have two fixes in flight.
- REVIEWER (focused review, after every fix, before commit): a code-review subagent (feature-dev:
  code-reviewer or general-purpose) scoped to JUST the diff. Adversarial: hunt regressions, broken
  Live/Stimulus/CSRF/voter wiring, light-theme leakage (bg-white/text-navy-900/gray/cyan), missing
  icon imports, Czech-copy/numeral errors, DS-fidelity misses. It returns only high-confidence
  issues. Commit only when it's clean (or you've fixed what it raised) AND the gate is green.
Long-run efficiency comes from a LEAN ORCHESTRATOR + the matrix as memory + frequent commits — NOT
from parallelism. Do the work one screen at a time; don't idle between screens — after each commit,
go straight to the next matrix row.

═══ METHOD ═══
STEP 1 — DISCOVER: fan out SCOUTs (read-only, parallel) → assemble their gap lists into
.docs/redesign/07-ds-parity-audit.md (one row per DS page + per DS component; columns: DS source ·
app target · status · note). Commit the matrix before fixing — coverage must be visible.
STEP 2 — FIX-LOOP (this is the job — don't stop at the matrix): walk the matrix in priority order
(most-seen player screens first). For each ⚠️/❌ row, ONE AT A TIME: FIXER closes it → REVIEWER
verifies the diff → run the gate → commit "Parity: <screen>" → push if green → flip the row to ✅ in
the same commit. Keep looping until NO ⚠️/❌ remain. Allowed end states per row: ✅ (fixed), 🔮
(reference-only, prepared+documented), ⛔ (cut — documented). Never leave a known gap unfixed and
unexplained.
STEP 3 — REFERENCE ELEMENTS for 🔮 rows (see below). STEP 4 — final completeness pass: a SCOUT
re-audits to confirm nothing was missed; fix the tail.

═══ THE WALK (DS source → app target; priority order) ═══
A. SHARED CHROME — pages/_wtnav.html + preview/components-nav.html → Layout/Nav.html.twig + .wtnav (sticky form is canonical; items Soutěže·Zápasy·Žebříček; bell omitted) · footer (.wtfoot 4-col / app mini) → Layout/Footer.html.twig · flash pills · form theme (form/_form_theme.html.twig).
B. TOKENS/FOUNDATION (validate, don't churn) — preview/colors-*/type-*/spacing-* → @theme + ds_tokens-css.md. Flag drift only.
C. COMPONENT CATALOG (preview/components-*.html) — buttons→.btn* · badges→Badge+Pill · cards→.card-glass/.surface* · inputs→form theme + .score-input/.pin-inputs (build PinInput.html.twig + Scoring/RuleFields.html.twig if missing per 02-components) · leaderboard→Leaderboard/GroupLeaderboard+.lb-table (reconcile the "Leaderboard/Table" name in 02-components) · match-card→Guess/GuessSubmitForm (3-state .tip-card) + build Match/MatchRow.html.twig if used by Zápasy/dashboard rows.
D. PLAYER PAGES — dashboard-hrac.html→portal/dashboard.html.twig · zebricek.html→portal/leaderboard/index.html.twig (you-strip, podium-wrap, lb-toolbar, gap-rows, sticky TY) · prihlaseni.html→auth/login.html.twig · registrace.html→auth/register.html.twig (OAuth is CUT — no social buttons) · Landing.html/landing-bold.html→home.html.twig + marketing subpages Funkce/Pro firmy/FAQ (Ceník = reference-only, blocked on pricing).
E. ORGANIZER KIT (ui_kits/organizer-webapp/index.html via ds_organizer-kit.md) — PoolDetail→group/detail · CreatePoolModal→group/create · PoolSettingsModal→group/edit · InvitePlayersModal→invite UI · TipForMembersScreen→manage_member_tips + my_tips_batch · LiveMatch→sport_match/detail + guess/detail · SetResultModal→sport_match/set_score · ProfileModal→profile/edit · PoolsDashboard→dashboard/tournament grids.
F. ADMIN LAST (token application only; no bespoke DS design).

═══ GAP-FILL RULES ═══
Reuse the DS's exact classes/structure — they already live in @layer components + the Twig
components. If a DS class is genuinely missing, PORT IT VERBATIM into @layer components (exact DS
values), then `tailwind:build`. Authoritative product CSS = colors_and_type.css (tokens), pages/
site.css + pages/nav.css (marketing/auth/nav), ui_kits/organizer-webapp/app.css (the app kit);
preview/base.css is preview-harness only — do NOT port it; when DS files disagree, the organizer-kit
app.css + the app's existing @layer win. High-reuse primitives = anonymous Twig components wrapping
the @layer class; one-off layout = utilities; don't over-componentize. PRESERVE all behavior on
edits: #[LiveProp]/#[LiveAction], data-model bindings, Stimulus controllers, CSRF, voters.

═══ REFERENCE ELEMENTS (DS shows it, feature not built — prepare + document, NO backend) ═══
Check 00-overview SCOPE per item. ⛔ CUT (do NOT build, even visually): OAuth/social login, in-
product live-match, Tweaks panel, payouts/Výplaty nav. 🔮 DEFERRED (prepare VISUAL-ONLY + document,
inert — no dead JS handlers): fuller premium/pricing/tier cards + create-step-4 (extend the existing
PremiumTeaser + premium_enabled flag), scorers/timeline + SetResultModal scorers editor + "Trefený
střelec" field, notifications bell+feed, Δ rank-change column. 🚫 NOT-DRAWN (document only): bracket/
pavouk + group-stage tables. Vehicle: render 🔮 elements in a DEV/ADMIN-ONLY styleguide route
(e.g. /_design, gated to ROLE_ADMIN or kernel.debug) mirroring project/preview/ — never in a prod
flow — or behind an off-by-default flag; label them "Připravujeme / reference". If a CUT item now
seems wanted, leave a one-line flag for the user — don't silently build it.

═══ DOCUMENT (keep current, commit alongside the work) ═══
07-ds-parity-audit.md (the living matrix — update each row as you fix) · 02-components.md (add
components you build; STATUS-tag every component {shipped·reference-only·deferred:why·cut}) ·
05-backlog.md (mark gaps DONE w/ file pointers; add newly found gaps). Fix any doc whose
convention/scope/env detail turns out wrong, so the next run is accurate.

═══ HARD RULES ═══
CQRS (CLAUDE.md): new read = QueryMessage + …Query handler + Result DTO via QueryBus; writes via
command.bus; repos never flush(); UUID v7. Mostly a VISUAL audit — prefer template/CSS-only;
backend only when a gap needs data that already exists. NEVER hand-write migrations (entity change →
doctrine:migrations:diff → commit generated file). A `final readonly` Result DTO must NOT use a
virtual get-hook (phpstan worker fatals). Never JOIN GuessEvaluationRulePoints into a query that SUMs
e.totalPoints. Dark only (no bg-white/text-navy-900/gray/cyan). No emoji — Lucide only; import new
icons via `ux:icons:import lucide:<name>` BEFORE use. Czech vykání, sentence-case headings, decimal
comma, „…" quotes, correct numerals. After CSS/utility changes: `bin/console tailwind:build`.

═══ VERIFICATION GATE (main → prod; ONLY push when ALL green) ═══
`composer quality` is ONLY phpstan+test:unit — NOT the full gate. Real gate:
  docker compose exec -T web composer phpstan          (L8)
  docker compose exec -T web composer cs:check
  docker compose exec -T web vendor/bin/phpunit <file>  (ALL tests, PER FILE — full run OOMs the VM)
  docker compose exec -T web bin/console lint:twig templates/
  docker compose exec -T web bin/console doctrine:schema:validate   (only if you touched entities)
Authenticated WebTestCase render-smokes (assertResponseIsSuccessful) confirm a page renders — host
curl is sandbox-blocked and there's no logged-in session for it, so you can't screenshot the running
app: diff DS-vs-app by READING, verify rendering via smokes. You MAY extend AppFixtures/DevFixtures +
tests/Support reference constants for data a screen/test needs; keep the `test` group deterministic.

═══ ENVIRONMENT GOTCHAS (memory-pressured Docker VM) ═══
If web restart-loops, dev DB missing: `docker compose exec -T postgres psql -U postgres -c 'CREATE
DATABASE wtips;'`. Before phpunit/phpstan: `docker compose stop adminer mailpit tailwind`, then
`cache:warmup --env=test`. Run integration tests ONE FILE AT A TIME; pass `</dev/null` to every
`docker compose exec` in a read-loop. Test DB rebuilds from the `test` fixture group (rm
tests/.database.cache to force). Rebuild Tailwind after any CSS change. Subagents must use the same
gate + gotchas. Brand assets (if touched) regen via tools/brand/generate-brand-assets.sh (macOS
`sips` rasterises the gradient SVG reliably; ImageMagick's MSVG mangles it).

═══ GIT ═══
Commit per screen/component directly to main: "Parity: <screen>" (or "Feature: <name>" for a new
reference component). Push after each green commit. No co-author footer. STOP only when the matrix
has no ⚠️/❌ left; then post a short summary: what reached parity, what shipped as reference-only,
what's documented-but-cut, and any product flags raised.
```
