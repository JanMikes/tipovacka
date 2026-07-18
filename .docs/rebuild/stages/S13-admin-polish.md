# S13 — Admin consolidation, docs, e2e, final review

**Goal**: coherent admin area for the new domain, documentation truth-up, end-to-end
proof, and a final adversarial review sweep. The "ship it" stage.

## Admin area

- Sidebar: Zdroje zápasů (curated list: sport, kind badge, match count, completed flag,
  „+ Globální soutěž" quick action), Soutěže (all competitions: global pill, monetization,
  members, source), Uživatelé, Kredity (purchases + full ledger view with new types +
  filters by type/competition), Pravidla (registry read-only, now with categories),
  Oznámení? (no — user-scoped only).
- Consolidate the S02-interim relabels: admin source detail = matches management (list w/
  playoff pills, score-entry links, import, complete/reopen source toggle), sport shown.
- Admin global-competition management polished (S09 basics): edit rules/monetization/fee,
  member moderation (remove), premium/boost state visibility (charges list for premium).
- Kill remaining admin/portal duplicate controllers where the portal page + voter suffices
  (admin deep-links, keeping the pattern the codebase already uses for matches).

## Docs truth-up

- `CLAUDE.md`: architecture section reflects final domain (entity list, bus wiring,
  scheduler); commands section notes chunked test runs; remove stale claims.
- `.docs/DOMAIN.md`: reconcile with as-built reality; decision log completed.
- `.docs/FIXTURES.md`: regenerate fully (S01 started; finalize).
- Delete/mark legacy: `.docs/design/` (obsolete light-brand) → delete;
  `docs/DEPLOYMENT.md` → rewrite to current lily-webhook reality incl. scheduler worker;
  `docs/future-notifications.md` → replaced by implemented reality (delete, point to
  DOMAIN.md); `.docs/SPEC.md` → prepend "superseded by DOMAIN.md" banner;
  `.docs/redesign/` → prepend pointer banner in README.
- `docs/stripe.md`: extend with spend/refund ledger types + PricingConfig.

## End-to-end proof (rewrite `FullHappyPathTest` + siblings)

1. **From-scratch journey**: wizard scratch (hockey) → add matches (manual + import) →
   invite via PIN → members tip (periods + scorers) → manager enters live score → final
   score with events + „poslední zápas" → evaluations → leaderboard + delta snapshot →
   competition-ended notifications.
2. **Global + commerce journey**: admin creates curated source (football) → global
   competition with 50-credit fee + boosts → user buys credits (fake gateway) → pays
   entry → buys OthersTips boost → sees concrete tips; second competition premium:
   joins charge the manager, insufficient → uncovered → reconciliation downgrade →
   refunds — asserting the ledger end-state exactly.
3. Notification + preference assertions woven into both.

## Final review sweep

- Run the standard stage review at maximum breadth over the whole rebuild diff
  (S01..S13): correctness, authz (every new command/controller voter-checked), ledger
  invariants, timezone handling, Czech copy consistency (vykání, „soutěž/zdroj zápasů"
  glossary, no „sázka"), dead code from retired features (join requests, creationPin,
  tipsDeadline, visibility enum), missing-icon check, template lint.
- `composer quality` + full chunked suite + `schema:validate` + fresh `db:reset` +
  migrations-from-zero run.

## Acceptance

- [ ] Both e2e journeys green; admin coherent; docs truthful; no legacy leftovers.
- [ ] Final review findings fixed; CI green on main; production deploy verified healthy
      (`/-/health-check/liveness` + smoke via public pages).
