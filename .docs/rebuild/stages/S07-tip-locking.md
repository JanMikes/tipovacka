# S07 — Tip locking model

**Goal**: replace kickoff-based locking with the competition-start model (+ late-match
deadlines + manual lock + the „Měnit tip" entitlement hook). The single most
domain-sensitive stage — resolver logic must be exhaustively unit-tested.

## Model

Effective tip deadline for (competition C, match M, user U), evaluated by the rewritten
**`EffectiveTipDeadlineResolver`** (all rules also respect `SportMatch::isOpenForGuesses`):

1. **Change entitlement** (premium toggle `allowTipChanges` ON, or user owns the
   `tip_change` boost — resolved via `CompetitionEntitlements`, stubbed in this stage to
   "always false" until S10): deadline = (kickoff of the **day's first C-match**, Prague
   day of M) − `C.tipChangeOffsetMinutes` (default 60; manager-editable only on premium).
   Never later than M's kickoff.
2. **Manager per-match override** (`CompetitionMatchSetting.deadline`) — capped at kickoff.
3. **Late-added match**: M entered C after C's lock moment (mode All: match created after
   lock; mode Subset: selection row added after lock) ⇒ deadline = M's kickoff.
4. **Default**: C's lock moment = `Competition.tipsLockedAt` if manually locked, else the
   earliest kickoff among C's matches (computed live via `CompetitionMatchProvider`).

Additions:
- `Competition.tipsLockedAt` (nullable) + commands `LockCompetitionTips` /
  `UnlockCompetitionTips` (manager/admin; unlock only before first kickoff) — the
  „Uzamknout tipy" button (screenshot 2) on competition detail; records events.
- `Competition.tipChangeOffsetMinutes` int default 60 (editable in premium settings, S10).
- Drop `Competition.tipsDeadline` (the old group-wide default deadline) — superseded by
  the lock moment; migration: existing values become `tipsLockedAt` when earlier than
  first kickoff. Keep `CompetitionMatchSetting` per-match overrides.
- `hideOthersTipsBeforeDeadline` semantics: keep, now "before the match's effective
  deadline" (unchanged mechanics, S10 layers entitlements on top).

## Refactor

- All deadline call sites go through the resolver (fix the two documented bypasses:
  `ManageMemberTipsController` open-matches list, `/zapasy` „Tipovatelné" filter — the
  filter becomes per-competition-aware via the user's memberships).
- Guess handlers (submit/update/void/on-behalf) use the resolver; `GuessDeadlinePassed`
  message mentions the actual deadline.
- UI: tip cards + match rows show „Uzávěrka: {datetime}" whenever the effective deadline
  differs from kickoff (which is now: almost always). The competition detail hero shows
  lock state („Tipy uzamčeny" pill / „Uzávěrka tipů {datetime}").
- Dashboard/match-list "Chybí tip" pills respect the resolver.

## Tests

- Resolver unit matrix (the core deliverable): pre-lock, post-lock, manual lock, unlock,
  late-added match (both modes, via selection `addedAt` vs match `createdAt`), per-match
  override beats default but not kickoff, entitlement branch with day-first-match
  computation (Prague timezone day boundary!, incl. two matches same day / different days),
  offset variants, postponed matches (kickoff moved after lock ⇒ still locked unless
  late-added — postponement does NOT reopen tipping; document in test name).
- Integration: submit blocked after competition start but allowed for late playoff match;
  manual lock blocks immediately; flow test for the lock button.

## Acceptance

- [ ] Locking matches DOMAIN.md §Tip locking exactly; resolver is the only authority.
- [ ] No surface computes deadlines ad hoc (grep for `kickoffAt` comparisons outside the
      resolver/state machine).
- [ ] Quality gate green.
