# Item 29 — the dev wallet can no longer demonstrate „nemáte dost kreditů"

**Status:** TODO
**Filed:** 2026-07-30, by the item 23 implementer, as a consequence of its own change (`2578def`).

## What happened

Item 23 raised the boost prices from 10 / 20 / 40 to **15 / 35 / 50**. `DevFixtures` derives the dev
user's wallet from those constants, so the number moved with them — but the **intent** did not
survive:

```php
// fixtures/DevFixtures.php:192–199
public const int DEV_USER_CREDIT_BALANCE = PricingConfig::BOOST_OTHERS_TIPS
    + PricingConfig::BOOST_TIP_DISTRIBUTION + 5;
```

- **Before:** 20 + 10 + 5 = **35**, against a most-expensive boost of 40 → the dev user could afford
  the two cheaper boosts and **not** the third.
- **Now:** 35 + 15 + 5 = **55**, against a most-expensive boost of **50** → the dev user can afford
  **all three**.

Its docblock still promises the old behaviour — *„enough for either cheaper boost, deliberately short
of „Měnit tip", so the insufficient-credits branch is one click away"* — and that is now false. It
also still names the **retired** boost („Měnit tip"), which item 23 renamed to „Počkejte si na
sestavy".

**So the „nemáte dost kreditů" branch of the boost paywall is unreachable after a plain
`composer db:reset`.** That is the B11 / B23 / B31 family exactly — a state the UI can render but the
dev world cannot demonstrate — which has now cost this stream diagnostic time **four** times, and is
why `.docs/ui-nav/ORCHESTRATOR-PROMPT.md` carries a standing warning to check reachability before
asking anyone to verify a state.

## What to do

1. **Restore the intent, expressed from `PricingConfig` — never a literal.** After the change the
   dev user's balance must:
   - afford **„Jak tipují ostatní?"** (`BOOST_TIP_DISTRIBUTION`, 15) and **„Přesné tipy soupeřů"**
     (`BOOST_OTHERS_TIPS`, 35);
   - **not** afford **„Počkejte si na sestavy"** (`BOOST_TIP_CHANGE`, 50).

   `max(BOOST_OTHERS_TIPS, BOOST_TIP_DISTRIBUTION) + 5` = 40 satisfies both today. Whatever you
   choose, **write the expression so a future price change cannot silently break it again** — that is
   the actual defect here, not the number. If PHP's `const` context will not take the expression you
   want, say so and use the clearest thing that works.
2. **Rewrite the docblock** to state the invariant („affords the two cheaper boosts, deliberately
   short of the most expensive one, so the insufficient-credits branch is one click away") and to use
   the **current** boost names.
3. **Update `.docs/FIXTURES.md`** — lines ~457 and ~491 still say „Konkrétní tipy kolegů", which item
   23 renamed to „Přesné tipy soupeřů". Read the surrounding prose; anything else quoting a retired
   boost name or an old price (10 / 20 / 40) must go too.
4. **Add a row to `.docs/FIXTURES.md`'s „Which world demonstrates which state" table** for the
   insufficient-credits paywall — which competition and which user reach it after a plain
   `db:reset`. That table exists precisely so this class of gap is visible without reading PHP, and
   this item is the fourth instance of the gap.

## What must NOT change

- **Prices.** 15 / 35 / 50 are the product owner's decision (item 23); this item adjusts the *wallet*
  so the fixture world can still show both sides of the paywall.
- **`AppFixtures`.** Item 03 assumption 1: many integration tests assert exact counts over whole
  tables, so this stays in `DevFixtures` (group `dev`) — **never** move it.
- Anything else in the dev worlds: the seeded ledger, the premium charges, the boost purchases that
  already exist. If a seeded purchase's historical amount now looks odd next to the new prices, that
  is a **historical record** and is correct — say so in your report rather than rewriting it.
- Prices come only from `PricingConfig`. **Do not introduce a literal 15 / 35 / 50 / 40 anywhere.**
- Czech in the UI, English in code and comments. No „sázka" in any form.

## Acceptance criteria

1. After `composer db:reset` + `docker compose restart web`, the dev user can buy the two cheaper
   boosts and is refused the most expensive one, **with the „nemáte dost kreditů" UI actually
   rendering** — not merely arithmetic that says it should.
2. `DEV_USER_CREDIT_BALANCE` is derived from `PricingConfig`, and its docblock is true.
3. `.docs/FIXTURES.md` names no retired boost and quotes no retired price, and its state table points
   at the insufficient-credits case.
4. `composer quality` clean.

## Verification

`PLAN.md`'s Definition of Done, plus:

- **Load the boost panel as the dev user and see both branches** — the affordable CTA on one boost
  and the insufficient-credits state on „Počkejte si na sestavy". `composer quality` cannot see
  either. Name the competition you used.
- `docker compose exec web vendor/bin/phpunit tests/Integration/Fixtures` and
  `tests/Integration/Portal/Competition`. **Never run `phpunit tests/` whole — it OOMs (exit 137).**
  Strip ANSI before grepping.
- After `composer db:reset` you **must** `docker compose restart web`, or every page 500s on stale
  FrankenPHP worker connections. Never run `asset-map:compile`.

## Commit

`git commit -o <path> [<path>…]` (`--only`) — **never** `git add` + `git commit`, never `git add -A`
/ `.` / `commit -a`. Push to `main`. Do not update the status board; report your sha.
