# S09 — Global competitions & entry fees

**Goal**: admin-run public competitions with credit entry fees; public discovery re-scoped
to them; join-request flow retired.

## Domain changes

- `Competition`: `bool $isGlobal` (default false), `int $entryFeeCredits` (default 0,
  ≥ 0; meaningful only when global). Global competitions: always joinable by any verified
  user (no PIN/link/invite needed), owner = the creating admin, monetization
  `none | premium | boosts` (admin's choice — `none` allowed here; user wizard offers only
  premium/boosts), on-behalf tipping + anonymous members disabled (voter-level).
- `CreateGlobalCompetitionCommand` (adminId, matchSourceId, name, entryFeeCredits,
  monetization, ruleChanges) — admin area flow; also offered as an optional checkbox step
  („Rovnou vytvořit globální soutěž") when the admin creates a curated source.
- `JoinGlobalCompetitionCommand` (userId, competitionId): guards — global, source not
  finished, not already member; charges `entryFeeCredits` via `CreditWallet::spend`
  (`EntryFee`, competition ref) when > 0 (`InsufficientCredits` surfaces as a friendly
  redirect to `/portal/kredity` with flash „Na vstupné potřebujete ještě X kreditů" +
  return-after-topup intent in session); creates membership. Rejoin after leave = new fee.
  Records `MemberJoinedCompetition` (existing event) — premium charging hooks in S10
  ignore global-with-`none`.
- Retire the join-request flow entirely: delete `CompetitionJoinRequest` entity + commands
  + queries + controllers + templates + voter attributes + tests (+ drop table migration).
  Joining paths afterwards: global (fee) | PIN | link | email invite | anonymous member.

## UX changes

- Public discovery: `/turnaje` becomes `/souteze` — lists **global competitions** (card:
  name, sport, source period, entry fee „Vstup: X kreditů"/„Zdarma", player count, CTA
  Připojit se / Přihlásit se). Curated-source browsing disappears from public web
  (sources are wizard-internal); public source detail pages removed. Marketing pages
  updated (Funkce/Ceník copy referencing real prices from `PricingConfig` via a Twig
  global/extension — no hardcoded prices in templates).
- Portal dashboard „Objev další" section now shows global competitions (join CTA inline
  incl. fee; insufficient-credits state visible upfront: „Máš 20/50 kreditů — dokoupit").
- Admin: global competitions list (under Soutěže) + create/edit (rules via the S04 page,
  fee + monetization editable until first member joins — after that fee locked).
- Competition detail for global members: identical to normal competitions minus
  member-management tools (no invites/PIN/anonymous/on-behalf; admin retains removal for
  moderation).

## Tests

- Handler: join with fee (ledger entry w/ competition ref + balance), free join,
  insufficient credits (no membership, no ledger row), double join, rejoin charges again,
  finished source blocks, fee lock after first join.
- Flow: discovery page renders states (anonymous/member/insufficient), join happy path,
  top-up-return intent completes the join? — NO: keep simple, user clicks join again after
  top-up (intent = redirect back to the competition, not auto-join; assert that).
- Migration/regression: join-request removal (fixtures + ~20 affected tests deleted/
  rewritten to remaining join paths).

## Acceptance

- [ ] Admin can stand up a global competition (fee 0 or paid) in minutes; users join
      exactly as DOMAIN.md describes; discovery shows only global competitions.
- [ ] Join-request machinery fully gone.
- [ ] Quality gate green.
