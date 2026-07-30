# Item 32 — CI is red: a boost refund assertion still expects the old price

**Status:** TODO — **urgent, `main` is red.**
**Filed:** 2026-07-30, from the product owner („tests are failing maybe due to fixtures change").

## What is actually failing

Run [30556039547](https://github.com/JanMikes/tipovacka/actions/runs/30556039547) on `61794ab`.
`phpstan`, `migrations-up-to-date` and `cs-check` all **pass**. Only the `tests` job fails, and it is
**one failure out of 1419**:

```
1) App\Tests\Integration\Command\EnablePremiumHandlerTest::testEnablingPremiumOnBoostsCompetitionRefundsActiveBoosts
Failed asserting that 35 is identical to 20.
```

**It is not the fixtures change (item 29).** It is item 23's price rise: `BOOST_OTHERS_TIPS` went
20 → 35, the seeded `BoostPurchase` derives its `pricePaid` from `BoostType::OthersTips->price()`, so
the refund is now 35 — and the test still expects 20.

`tests/Integration/Command/EnablePremiumHandlerTest.php`:

- **line 247** — `self::assertSame(20, $refunds[0]->amount);`
- **line 256** — `self::assertSame(20, $events[0]->amount);`
- **lines 212–213** — a comment saying „holds an active OthersTips boost (fixture, pricePaid 20)".

**Two agents looked at this file and both got it wrong**, which is why the sweep below matters more
than the two-line fix:

- item 23 reported `tests/Integration/Command` **green** after its change. CI disagrees.
- item 29 inspected line 212 and concluded *„a comment only, no assertion depends on it"*. Two
  assertions do, 35 lines below it.

## What to do

1. **Fix the two assertions to derive from `PricingConfig`**, not to carry a new literal. Item 23
   already did exactly this in `BoostPurchaseTest`, `PurchaseBoostHandlerTest` and
   `GlobalCommerceJourneyTest` — match that style. A test that hard-codes a price is the defect;
   changing 20 to 35 would just re-arm it.
2. **Fix the comment on lines 212–213** so it states the derivation rather than a number.
3. **Do NOT touch anything about the premium charge.** `PREMIUM_PER_PLAYER` is unchanged at 10, so
   the other 20s in this file (line 150's „2 × 10 = 20" group charge, line 176's `-20`, lines 186/196's
   „Needs 20, has only 15") are **correct** and must stay. Read each before you touch it.
4. **Sweep for the rest.** CI's full run is the only thing that has exercised every test since the
   price change; local runs here are chunked because the whole suite OOMs. `grep` the test suite for
   literal boost prices (10, 20, 40 as *old* prices; 15, 35, 50 as *new* ones) and for balances
   derived from them, and report what you find even where the test currently passes — a test that
   passes today on a hard-coded 35 is the next CI failure.

## Verification

- `docker compose exec web vendor/bin/phpunit tests/Integration/Command` **must** go green — that is
  the chunk which was reported green and was not.
- Then run the suite the way **CI** does: `docker compose exec web composer test`. It may OOM locally
  (exit 137) — if it does, say so and fall back to chunks covering at least `Integration/Command`,
  `Integration/Query`, `Integration/Portal`, `Integration/Event`, `Integration/Admin`,
  `Integration/Public`, `Integration/Invitation`, `Integration/Auth`, `tests/Unit`.
- `composer quality` clean.
- PHPUnit emits ANSI codes; strip them before grepping.

## Commit

`git commit -o <path> [<path>…]` (`--only`). Push to `main`. Do not update the status board; report
your sha and whether CI goes green.
