# Item 09 — Drop the `/portal` URL prefix; unify the soutěž URL space

**Status:** TODO
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
