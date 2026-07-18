# S03 — Credit spending core

**Goal**: give the wallet a first-class, generic, auditable spend/refund API so later
stages (entry fees S09, premium S10, boosts S10) only add callers, never ledger mechanics.

## Domain changes

- `Enum\CreditTransactionType` — add: `EntryFee = 'entry_fee'`,
  `PremiumCharge = 'premium_charge'`, `BoostPurchase = 'boost_purchase'`,
  `PremiumRefund = 'premium_refund'`, `BoostRefund = 'boost_refund'` (+ Czech labels).
- `CreditTransaction` — add nullable references: `competition` FK (nullable),
  `relatedUser` FK (nullable — e.g. the joining member behind a premium charge),
  `boostType` (nullable string/enum — added as string now, typed enum lands in S10).
  Keep immutability; migration adds columns.
- `CreditWallet` new behavior methods (mirroring `adjustByAdmin`'s
  mutate-balance-and-mint-transaction pattern; wallet must be loaded via
  `CreditWalletProvider::getForUpdateOrCreate` by every caller):
  - `spend(Uuid $txId, positive-int $amount, CreditTransactionType $type, \DateTimeImmutable $now, ?Competition $competition = null, ?User $relatedUser = null, ?string $boostType = null, ?string $note = null): CreditTransaction`
    — throws `InsufficientCredits::forSpend(...)` (new factory, message mentions missing
    amount) when balance would go negative; only spend-types allowed.
  - `refund(Uuid $txId, positive-int $amount, CreditTransactionType $refundType, ...same refs): CreditTransaction`
    — only refund-types allowed; credits back.
  - Records domain events `CreditsSpent` / `CreditsRefunded` (payload: walletUserId,
    amount, type, competitionId?, relatedUserId?, boostType?, balanceAfter) — no handlers
    yet (S10/S11 subscribe).
- `PricingConfig` (`src/Service/Credits/PricingConfig.php`) — final class with public
  consts, the ONLY place with price literals:
  `PREMIUM_PER_PLAYER = 10`, `BOOST_TIP_DISTRIBUTION = 10`, `BOOST_OTHERS_TIPS = 20`,
  `BOOST_TIP_CHANGE = 40`, `LOW_BALANCE_WARNING_THRESHOLD = 50` (5 players' worth).
- Fix the pre-existing wart while here: `InitiateCreditPurchaseHandler`'s bare
  `\DomainException` for email-less accounts becomes named exception
  `CreditPurchaseRequiresEmail` (409).

## Tests

- Unit (`CreditWalletTest` extension): spend happy path + ledger fields (balanceAfter,
  refs), insufficient throws + balance untouched, refund, wrong-type guards, events
  recorded with correct payloads.
- Integration: a scripted spend→refund round trip through a throwaway command? Not needed —
  no production caller yet; cover via unit + repository save assertions in one small
  integration test that persists a spend transaction and reloads it (columns mapped
  correctly).

## Acceptance

- [ ] No behavior change anywhere user-visible; wallet API ready with full unit coverage.
- [ ] `PricingConfig` exists and is referenced by nothing yet (S10 wires it).
- [ ] Quality gate green.
