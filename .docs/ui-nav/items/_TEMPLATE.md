# NN — <short imperative title>

> **Status:** TODO
> **Depends on:** _(item files that must land first, or „nothing")_
> **Owner decision date:** YYYY-MM-DD

## Why (the requirement, in the product owner's terms)

What the user asked for and the problem behind it. Written so someone who was not in the
conversation understands the intent, not just the mechanics.

## What changes

Concrete, exhaustive. Every route, template, component and controller that moves, appears or
disappears. Prefer a table when several things move.

| Before | After |
|---|---|
| … | … |

## Out of scope

Things a reader might reasonably assume are included but are not. Prevents scope creep and
tells the implementer where to stop.

## Implementation notes

Anything the implementer would otherwise have to rediscover: which service already returns the
data, which query needs a new field, which CSS class already exists, which component to reuse.
Reference `../UI-MAP.md` sections rather than restating them.

## Acceptance criteria

- [ ] Concrete, checkable statements. „Page X at route Y shows Z."
- [ ] Include the negative cases: what must *not* appear, for which role.
- [ ] Include mobile behaviour when the change touches navigation or layout.

## Verification

```bash
docker compose exec web composer cs:fix
docker compose exec web composer quality
docker compose exec web vendor/bin/phpunit tests/Integration/<subdirs>
```

Pages to load and confirm 200 + expected markup:
- `…`

## Assumptions made

_(Implementer appends here if the item did not answer a question it had to answer.)_
