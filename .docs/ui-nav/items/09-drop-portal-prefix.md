# Item 09 — Drop the `/portal` URL prefix; unify the soutěž URL space

**Status:** DONE
**Depends on:** `BUGS.md` **B1 must land first** — it adds a verification guard in exactly the
security layer this item rewires. Running them concurrently will conflict.
**Should run before or alongside:** items 05–08, which all create or move routes. Doing it after them
means renaming the same routes twice.

---

## Why

Now that no backwards compatibility is owed (`PLAN.md` conventions, product owner 2026-07-29), the
biggest remaining IA wart is the `/portal` prefix:

- It is an **implementation detail leaking into the URL** — the firewall's path pattern, not a thing
  a user has a word for.
- It is the **only English segment** in an otherwise fully Czech URL space (`/souteze`, `/zapasy`,
  `/zebricek`, `/kredity`, `/nastenka`, `/pripojit`).
- Worst of all it **splits one concept across two prefixes**: the competitions list is `/souteze`
  while a competition is `/portal/souteze/{id}`. Same noun, two trees. Item 07 makes `/souteze` the
  hub for logged-in and logged-out visitors alike, which makes the split actively misleading.

## Target

```
/souteze              list / hub          (item 07)
/souteze/{id}         competition detail  (item 08)
/souteze/{id}/…       its sub-pages
/zebricek             leaderboard         (item 05)
/nastenka             player dashboard    (item 06)
/zapasy, /zapasy/{id}, /kredity, /oznameni, /profil, /pripojit, …
```

i.e. **delete the `/portal` segment everywhere** and let the Czech nouns carry the structure. Keep
`/admin` — that one *is* a meaningful audience boundary.

Route **names** should follow: `portal_competition_detail` → something without the `portal_` prefix,
consistent with whatever item 07 settles on. Renaming names is cheap and greppable; do it in one pass
rather than leaving a mixture.

## The hard part: security

The portal firewall is a **path pattern** (`^/portal`). Removing the prefix means authentication can
no longer be inferred from the path, so this item must replace that mechanism, not just delete it.

Decide between:
- **(a)** one firewall over the whole app plus explicit `access_control` / `#[IsGranted]` per route, or
- **(b)** keeping a firewall but matching on something other than a path prefix.

**(a) is the conservative, idiomatic choice** and fits a codebase that already leans hard on voters
(`Competition`, `MatchSource`, `SportMatch`, `Guess`, `Leaderboard`, …). Whichever you pick, the
guarantee to preserve is exact:

> **Every page that requires a login today must still require a login, and every page that is public
> today must still be public.**

This is the whole risk of the item. Enumerate the current behaviour **before** changing anything —
`bin/console debug:router` plus the firewall/access_control config — write the list down, and turn it
into a test that asserts, route by route, which ones an anonymous request may reach. That test is the
deliverable that makes this item safe; write it first and watch it pass before *and* after.

Do not forget B1's verification airlock: an unverified logged-in user must stay confined exactly as
B1 left them. Re-run B1's tests as part of this item.

## Acceptance criteria

1. No route path contains `/portal`. `grep -rn "/portal" src/ templates/ tests/ config/` comes back
   clean apart from genuine prose.
2. An anonymous request reaches exactly the same set of routes as before this item — proven by the
   route-by-route test described above, not by spot checks.
3. An unverified logged-in user is still confined to the B1 airlock.
4. `/admin` is untouched.
5. Nothing inside the app 404s: every `path()` call, test and doc updated in the same commit.

## Definition of done

Per `.docs/ui-nav/PLAN.md`, and unusually strict here because the blast radius is the whole app:
`cs:fix` → `quality` → **every** `tests/Integration/<subdir>` chunk, not a selection (still never
`phpunit tests/` whole — it OOMs at exit 137). Then crawl the app logged out, logged in unverified,
logged in verified, and as admin. Update `UI-MAP.md` §2 wholesale. Update the status board row to
DONE + sha. Commit `UI: drop the /portal URL prefix`, push to `main`.

---

## What landed

**The safety net first.** `tests/Integration/Security/AnonymousReachabilityTest` was written and
made green **against the pre-item code**, then re-run unchanged afterwards. It is keyed by
**controller class**, not by route name or path — precisely because those are what this item
rewrote, while the controllers did not move. Two tests:

- `testTheInventoryCoversEveryApplicationRoute` — the written-down inventory must describe the
  router exactly. A new route whose controller is not listed fails; so does a stale entry.
- `testAnonymousVisitorReachesExactlyTheRoutesTheInventoryAllows` — every route is actually
  requested anonymously and classified „bounces to `/prihlaseni`" vs „is served".

Writing it down first paid for itself immediately: it surfaced **two routes whose access was not
what the config suggested**, both pre-existing and both now documented in the map —
`app_design_styleguide` (`/_design`) is ROLE_ADMIN via an in-controller
`denyAccessUnlessGranted`, not via any path rule; and `app_resend_verification_email` bounces an
anonymous POST to the login page (its CSRF failure is an `AuthenticationException`).

**Security mechanism — option (a), as the item recommended.** `access_control` went from 17 path
rules to **one**:

```php
'access_control' => [
    ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
],
```

and every one of the 66 controllers in `src/Controller/Portal/` gained a class-level
`#[IsGranted('ROLE_USER')]`. The `PUBLIC_ACCESS` rules were deleted rather than rewritten — with
no matching rule, access is already open, so they only ever documented intent, and the
reachability test documents it far better. The „fails open if you forget the attribute" risk that
comes with option (a) is closed at CI level by the inventory test: a new `Controller\Portal\…`
route that nobody gated shows up as a diff in the expected map.

**Why a path could no longer carry the boundary.** `/souteze` is now three audiences in one
prefix — the public discovery list (`/souteze`), a logged-out invitation landing
(`/souteze/pozvanka/{token}`) and the members-only hub (`/souteze/{id}`). Expressing that as
ordered path regexes would have been exactly the implicit coupling the item set out to delete.
The existing `Requirement::UUID` constraints are what keep the three apart in the router;
`/souteze/nova` and `/souteze/pozvanka/…` cannot be read as a competition id.

**The rename.** All 65 route names lost the `portal_` prefix (`portal_competition_detail` →
`competition_detail`), and all 47 `/portal/…` paths lost the segment. One pass, no mixture left:
`grep -rn "/portal\|portal_" src/ templates/ tests/ config/` is clean. The remaining prefixes are
`admin_*`, `app_*` (auth + marketing) and `public_*`. B1's airlock allow-list needed exactly one
edit (`portal_account_delete` → `account_delete`) — it turned out to be keyed by route **name**,
not by path, so removing the prefix barely touched it.

**Verification.** `cs:fix` → `quality` (phpstan lvl 8 + 463 unit tests) → all 15
`tests/Integration/<subdir>` chunks + the three root-level flow tests: 767 integration tests
green, including B1's 35-test airlock suite unchanged. Then `db:reset` with `DevFixtures` and a
crawl of the real app as anonymous / unverified / verified / admin, plus a link-following crawl
that requested every internal `href` and `form action` on the main pages: **zero 404s**; the only
non-2xx are 405s (POST-only routes probed with GET) and voter 403s that predate this item.

## Assumptions made

- **Route names: strip the prefix, change nothing else.** `portal_competition_detail` →
  `competition_detail`. Item 07 (which the item file says the naming should follow) is not written
  yet, so the most conservative reading is the mechanical one. `public_competitions_list` and the
  two `competition_*` invitation routes kept their names — they were never portal routes, and
  renaming them is item 07's call.
- **Path segments other than `/portal` were left alone.** `/turnaje/{id}` for a zdroj zápasů and
  `/zdroje/{id}/tymy` for its autocomplete remain two different nouns for one concept. The item
  asked to delete `/portal`, not to re-slug the app; recorded as pain point 9 in `UI-MAP.md`.
- **`/turnaje` (the legacy 301 to `/souteze`) was kept.** `PLAN.md` calls it a deletion candidate,
  but candidacy is not a decision, and it does not collide with `/turnaje/{id}` (UUID-constrained).
- **DONE item files were not rewritten.** Items 01 and 02 mention old route names as a record of
  what they did at the time; falsifying that history would be worse than the staleness. Living
  docs — `UI-MAP.md`, `PLAN.md`, `BUGS.md`, `CREATE-WIZARD.md`, the open items 05 and 08, and
  `.docs/features/*` — were updated. `.docs/rebuild/` and `.docs/redesign/` are finished-stream
  archives and were left alone for the same reason.
- **The Live Component endpoint's anonymous exposure was not touched.** `/_components/…` was never
  covered by `access_control` and still is not; a component reached anonymously fails inside
  itself. That is pre-existing behaviour, the guarantee this item owed was to preserve it exactly,
  and hardening it is a `BUGS.md` matter rather than a URL-structure one.
