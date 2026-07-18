# S10 — Premium & boosts commerce (+ scheduler infrastructure)

**Goal**: make monetization real — premium per-player charging with reconciliation,
boost purchases, entitlement gating in the UI. Brings symfony/scheduler (first consumer).

## Scheduler infrastructure (prerequisite within this stage)

- Add `symfony/scheduler`; `src/Scheduler/MainSchedule.php` (`#[AsSchedule('default')]`).
- Dev/prod: worker consumes `scheduler_default` alongside async. Update
  `~/www/lily.srv/apps/wtips/compose.yaml` messenger-consumer command to
  `messenger:consume async scheduler_default …` (commit in the infra repo, note in PR/commit
  message that lily redeploy picks it up). Dev compose gains an optional `worker` service
  (profile `worker`) so scheduled/delayed paths are testable locally; tests trigger
  scheduled handlers directly (dispatch their messages), never sleep.

## Domain — premium

- New entity `CompetitionPremiumCharge` — `id`, `competition` FK, `member` FK (User),
  `amount` int, `status: PremiumChargeStatus { Charged='charged', Uncovered='uncovered',
  Refunded='refunded' }`, `chargedAt`/`refundedAt` nullable, `createdAt`; unique
  `(competition_id, member_id)` for active rows (partial, status != refunded… simplest:
  full unique (competition, member) + rejoin reuses/reactivates the row).
- Join hook (`MemberJoinedCompetition` handler, monetization=premium, non-owner members):
  try `spend(PremiumCharge, PricingConfig::PREMIUM_PER_PLAYER, competition, relatedUser:
  member)` → row Charged; on `InsufficientCredits` → row Uncovered + event
  `PremiumChargeUncovered`. After any charge: if manager balance <
  `LOW_BALANCE_WARNING_THRESHOLD` → event `PremiumBalanceLow` (dedup per competition/day
  left to S11's notification layer).
- Top-up retry: on `CreditsPurchased` (manager) → `SettleUncoveredPremiumChargesCommand`
  retries Uncovered rows across the manager's premium competitions (oldest first).
- **Reconciliation**: scheduler task every 5 min → `ReconcilePremiumCompetitionsCommand`:
  for premium competitions whose first kickoff ≤ now and not yet reconciled
  (`Competition.premiumReconciledAt` nullable): all charges Charged ⇒ mark reconciled
  (event `PremiumConfirmed`); any Uncovered ⇒ refund every Charged row
  (`refund(PremiumRefund)`), mark rows Refunded, `monetization → boosts`, event
  `PremiumDowngraded`. Late joins after reconciliation still charge-at-join
  (uncovered late charge ⇒ notify manager, but no more auto-downgrade — competition
  already started; manager sees debt in settings).
- `EnablePremiumCommand` (manager, anytime): atomically charge PREMIUM_PER_PLAYER × current
  active non-owner members (single `spend` of the total, one ledger row, per-member charge
  rows Charged) — all-or-nothing (`InsufficientCredits` ⇒ friendly error with exact amount);
  refund ALL active boost purchases in the competition (`BoostRefund` to each buyer,
  event per buyer); set monetization=premium. `SwitchToBoostsCommand` (manager, anytime):
  refund all Charged premium rows, monetization=boosts.
- Premium settings (manager, only when premium): toggles `premiumShowDistribution`,
  `premiumShowOthersTips`, `premiumAllowTipChanges` (columns on Competition, default
  false) + `tipChangeOffsetMinutes` (S07 column). Settings page section „Prémium".

## Domain — boosts

- `Enum\BoostType { TipDistribution='tip_distribution', OthersTips='others_tips',
  TipChange='tip_change' }` + `price()` reading `PricingConfig`.
- New entity `BoostPurchase` — `id`, `user` FK, `competition` FK, `type`, `pricePaid`,
  `purchasedAt`, `refundedAt` nullable; partial unique `(user, competition, type)
  WHERE refunded_at IS NULL`.
- `PurchaseBoostCommand`: monetization must be `boosts`; spend + row; `OthersTips`
  includes `TipDistribution` (entitlement-level superset — no double purchase needed;
  buying OthersTips while owning TipDistribution charges the difference? NO — keep dumb:
  full price, UI communicates „obsahuje Lištu tipů"; owning OthersTips hides the
  TipDistribution offer).

## Entitlements

- `Service\Competition\CompetitionEntitlements` — the single gate:
  `canSeeDistribution(C,U)`, `canSeeOthersTips(C,U)`, `canChangeTips(C,U)` — true via
  premium toggles (everyone) or own active boost; manager/admin always entitled;
  everything true after the match's effective deadline (existing post-deadline openness).
  Replace ad-hoc checks in pick-distribution, guesses list, matrix; S07's resolver stub
  now calls `canChangeTips`.
- UI paywalls (screenshot 3): locked distribution bar (hatched + „Uvidíš, jak tipuje X
  hráčů → Odemknout") and locked others-tips list with boost-purchase modal (price,
  balance, one-click buy, insufficient → top-up link). Premium competitions show
  „✓ PRÉMIUM" pill instead. Boost management section in competition detail sidebar
  („Tvoje vylepšení" + owned/available states).

## Tests

- Handlers: join-charge (charged/uncovered/low-balance event), settle-on-topup,
  reconciliation both branches (+ idempotency via premiumReconciledAt, late-join behavior),
  enable-premium atomicity + boost refunds, switch-to-boosts refunds, purchase-boost
  (dup guard, wrong monetization, superset visibility), entitlements unit matrix.
- Flow: boost purchase from paywall, premium wizard-intent → first join charges,
  downgrade end-to-end (fixture time via MockClock).
- Ledger invariants: every commerce action = exactly one typed ledger row with refs.

## Acceptance

- [ ] DOMAIN.md §Monetization implemented verbatim incl. refund symmetry; scheduler runs
      reconciliation; infra repo updated.
- [ ] All tip-visibility surfaces go through `CompetitionEntitlements`.
- [ ] Quality gate green.
