# Standing brief for every implementer in this stream

Read this once, at the start. It is the boilerplate that used to be re-typed into every dispatch
prompt — the prompt now carries only what is specific to your item.

## Verify in proportion to what can break

Verification in this stream has repeatedly caught real defects, so none of it is optional — but
**depth must match the kind of change**. Over-verifying a copy change costs an hour and finds
nothing; under-verifying a layout change ships a bug a screenshot cannot see. Pick your tier:

| Kind of change | What is actually required |
|---|---|
| **Copy / string** | Load **one** page showing it, at one width. Run the one test chunk that asserts it. Nothing else. |
| **Colour / variant swap** | `getComputedStyle` on the changed element **and on one neighbour that must not change**, at **one** width — colour does not vary with viewport. No bounding boxes, no multi-width sweep. |
| **A control, link or button changes shape** | Its box before/after at **two** widths (desktop + 320 px), plus `:focus-visible` reachable by keyboard. |
| **Layout: grid/flex tracks, wrapping, a new element in a row** | The full pass: pairwise bounding-box intersection across painted leaves at **≥4 widths down to 320 px**, plus box `width` vs `scrollWidth` for truncation. **This tier is where every layout bug in this stream was found** (B7, B13, B21, B29, B30, item 22's merged card) — do not shorten it. |
| **Query / data** | Query **count** before and after (Symfony profiler) — the N+1 trap is documented in `CLAUDE.md`. Correctness by test, not by eye. |
| **Deletion / route change** | `grep` every reference; nothing inside the app may 404 or 500. |

Two measurement facts that have each cost this stream a wrong conclusion:

- **A `Range` measures text INK, and ink is not clipped by `overflow`** — it reported 82.5 px for a
  name whose box had collapsed to 15.1 px. A Range alone cannot detect truncation; you need box
  `width` vs `scrollWidth` too. (B29.)
- Conversely, **box arithmetic alone can cry wolf**: B30's „clipped at every width" was 2 px of cell
  band, while the glyphs cleared the edge by 11 px. Check both, and say which you measured.

**Never reconstruct a „before" by restoring the file** (see below). Override rules in the live CSSOM,
toggle a class, or capture the baseline before you edit.

## Always, regardless of tier

- **`composer quality` does not catch Twig errors, layout, colour or copy.** It passes on a page that
  throws at render time. **Load the page.** Check the **browser console** — since JS-off support is
  deferred, a Stimulus controller that fails to connect is only visible there.
- **Drive headless Chrome yourself; do not rely on the `claude-in-chrome` extension.** It has
  repeatedly reported itself not connected in this environment, and several agents lost their whole
  verification pass to that before falling back. Headless (puppeteer/playwright against
  `localhost:58080`) works, gives you `getComputedStyle`, geometry **and** the console, and is where
  every measured result in this stream actually came from. A `curl` round-trip proves the server
  rendered without an exception — it proves **nothing** about the console or about layout. If that is
  all you managed, say so plainly rather than calling it verified.
- **Run only the test chunks your change can plausibly break**, plus any the item names. Do not run a
  standard list out of habit. **Never `phpunit tests/` whole — it OOMs (exit 137).** PHPUnit emits
  ANSI codes; strip them before grepping.
- **Never put a Twig comment inside a `<twig:Foo …>` attribute list** — parse error, 500, invisible to
  `composer quality`. Comments go above the tag.
- **Never put a double quote inside a `@param`/`@return` description** — PHPStan silently drops that
  tag's type and reports a misleading „no value type specified".
- Never run `asset-map:compile`. If assets look frozen: `rm -rf public/assets`, then restart `web`.
- **Do not run `composer db:reset` or `docker compose restart web` while a sibling is in flight** —
  the database and the `web` container are shared. If you must, say so first.

## Git — every one of these has actually destroyed work in this repo

- **Never `git add -A`, `git add .`, or `git commit -a`.**
- **`git add` + `git commit` is not safe either**, even with explicit paths: the index is shared
  mutable state and a sibling can write to it between the two commands. Verifying the index proves
  nothing. **Always `git commit -o <path> [<path>…]`** (`--only`) — index-independent. New file:
  `git add -N <path>` first.
- **Never restore a file from HEAD to take a baseline, and never run a tree-wide `git restore` /
  `git checkout .` / `git stash`.** `-o` protects the index; **nothing protects the working tree.**
- **Never run `composer cs:fix` repo-wide** while a sibling is in flight — scope it to your paths.
- **Commit as soon as a change verifies**, and re-check `git status` *and* `git log` before declaring
  done: a clean tree can mean „committed" or „silently discarded", and only the log tells you which.
- Push to `main`. **Do not update the status board** — report your sha and the orchestrator records it.

## Domain rails

Czech in the UI (and Czech query parameters), English in code, identifiers, class names and comments.
**Never „sázka" or any of its forms**; no gambling framing, no payouts — entry fees are burned
credits. **Premium XOR boosts.** **Prices only from `Credits/PricingConfig`** — never a literal.
**Managers and admins get no free entitlement pass.** **`CompetitionMatchProvider`** is the only
answer to „what's in this competition". **`TipStatsProvider` batched per page, never per row.**
**Every route must be declared in `tests/Integration/Security/AnonymousReachabilityTest`.** Migrations
are generated, never hand-written. Icons must be imported before use.

## When the item file is wrong

It often is — line numbers go stale within the hour, and three tabulated counts were wrong on
2026-07-30 alone, each because someone grepped a markup shape instead of the string. **Grep the
string, count every hit, and report where the item file was stale.** If the item states a cause,
treat it as a hypothesis and say which explanation turned out to be true. A finding that corrects the
spec is worth more than a green build.

If a failure cannot be explained by your own diff, it belongs to a sibling: **report it, do not fix
it, do not revert it.**
