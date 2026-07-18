# S08 — Create-competition wizard

**Goal**: the guided 4-step „Vytvořit soutěž" flow (reference screenshots: steps 1, 2, 4)
replacing today's fragmented pages. Live Component modal-style wizard.

## Flow (single Live Component `Competition:CreateWizard`, stepper dots on top)

**Krok 1 — Základy** („Vytvořit novou soutěž / Nastavte základy. Hráče pozvete v dalším
kroku."): Název soutěže; Zdroj zápasů select (curated sources w/ meta „{name} · {sport} ·
{start}–{end}"); checkbox „Vytvořit soutěž od začátku — Bez šablony, zápasy, týmy a
termíny doplníte ručně" (disables the select, reveals a sport select); when a source is
picked: radio „Všechny zápasy" / „Vybrat jen některé zápasy" — subset choice reveals a
grouped match checklist (by round/day, playoff-flagged marked; live-filterable). When the
source can contain playoffs (any playoff match exists or admin marked source as having
them — simply: always show), toggle „Zahrnout playoff" (default on, only for mode All).

**Krok 2 — Vyber pravidla** (screenshot 5/8): preset tiles Standardní / Standard + střelec /
Vlastní; per-rule point steppers (sectioned by rule category); „Tipovat také
poločasy/třetiny zápasu?" ANO/NE revealing period rule points; overtime toggle
(„Tipovat výsledek po prodloužení při remíze?"). All wired to the S04/S06 rule identifiers.

**Krok 3 — Pozvánky**: PIN toggle (+ regenerable preview), shareable link (auto),
email invite textarea (bulk emails, reuses bulk-invitation machinery), all skippable
(„Přeskočit — pozvat můžete kdykoli později").

**Krok 4 — Pozvete nás na pivo?** (screenshot 7): two selectable cards —
„Zaplatím za celou skupinu" (10 kreditů × hráč, DOPORUČUJEME, benefit checklist) vs
„Nechám příspěvek na jednotlivcích" (boost price list 10/20/40). Sets
`monetization = premium | boosts` (default boosts). No charging happens yet (S10);
premium selection just stores intent + shows the manager's current credit balance and a
top-up hint.

Submit → single `CreateCompetitionCommand` (name, sourceId | fromScratch+sportId,
selectionMode + selectedMatchIds, includePlayoff, ruleChanges, withPin, inviteEmails,
monetization) → handler composes existing building blocks in ONE transaction: private
source creation (from scratch), competition + owner membership, selection rows, rule rows
(provision defaults then apply changes), invitations. Redirect: from-scratch → match
management page with an empty-state „Přidejte zápasy" (manual + import CTAs); otherwise →
competition detail with a success onboarding flash.

## Cleanup

- Nav CTA „Vytvořit soutěž" opens the wizard (route `/portal/souteze/nova`). Dashboard
  CTAs likewise.
- Delete: standalone create-competition page (S02 interim), standalone
  create-private-source page (from-scratch replaces it), `CreatePrivateMatchSourceCommand`
  folds into the wizard handler. Admin curated-source creation stays (admin area).
- Port the DS stepper styles (`.step-num`/`.step-bar` — re-derive cleanly, the DS variant
  is flagged malformed) into `app.css`; import any missing Lucide icons.

## Tests

- Integration (component tests via `createLiveComponent()` or flow-level POSTs): each step
  validation (empty name, no source+no scratch, subset with zero matches ⇒ 422 with Czech
  errors), full happy path from-scratch, full happy path curated+subset+rules+premium
  intent — assert composed aggregate (source kind, selection rows, rule rows, monetization,
  invitations sent).
- Handler integration: single-transaction atomicity (failure in invitation email
  validation rolls everything back).

## Acceptance

- [ ] One guided entry point; old fragmented pages gone; both scratch and curated paths
      deliver a fully configured competition.
- [ ] Quality gate green.
