# Item 36 — the missing-tips badge says „Chybí 3 tipy"

**Status:** TODO
**Filed:** 2026-07-30, from the product owner.

## The instruction (verbatim)

> „„Chybí natipovat X zápasů" is too long, just „Chybí X tipů" shorten the badges everywhere"

## What to change

Item 30 (`9937154`) shipped the badge on two surfaces, both rendering
`label="Chybí natipovat {{ n }} {{ n|czech_zapas }}"`:

- `templates/portal/dashboard.html.twig` (~228) — „Moje soutěže" grid
- `templates/components/Competition/PlayingCard.html.twig` (~41) — `/souteze`, „Soutěže, kde tipuješ"

Both become **„Chybí {{ n }} {{ n|czech_tip }}"**.

**The plural is the actual work.** „tipů" is the genitive plural — correct for 5+, wrong for 1 and
2–4. The badge must read:

| n | Czech |
|---|---|
| 1 | Chybí **1 tip** |
| 2–4 | Chybí **2 tipy** |
| 5+ | Chybí **5 tipů** |

`src/Twig/CzechPluralExtension.php` already does exactly this shape for `zápas` (item 30). Add the
`tip` forms alongside it, in the same style — do not write a second pluralisation mechanism, and do
not inline a ternary in the template.

**Check whether `czech_zapas` still has a caller** once both badges stop using it. If it does, leave
it. If it does not, say so and let the orchestrator decide — do not delete it on your own initiative,
because a „shorten the copy" item is not the place to remove a helper.

## Also update, so the record does not drift

The old string is quoted in comments, docblocks and tests — `grep -rn "natipovat"` and fix the ones
that describe **this badge**:

- `tests/Integration/Portal/MissingTipsBadgeTest.php` — asserts the exact strings in several places,
  including a deliberate „1 zápasy" negative assertion. Keep that shape: assert the correct form **and**
  that the wrong one is absent, now for „tip"/„tipy".
- `tests/Integration/Query/ListMyCompetitionsQueryTest.php`
- Docblocks in `src/Service/Competition/MissingTipCounter.php`,
  `src/Query/ListMyCompetitions/{ListMyCompetitions,ListMyCompetitionsQuery,CompetitionListItem}.php`,
  `src/Query/ListMyPlayingCompetitions/{ListMyPlayingCompetitionsQuery,PlayingCompetitionItem}.php`,
  `src/Controller/Portal/DashboardController.php`, `src/Controller/DesignStyleguideController.php`
- `templates/design/styleguide.html.twig` (~337) — the gallery caption describing the badge
- The template comments at `dashboard.html.twig:214` and `PlayingCard.html.twig:23` that quote
  „Chybí natipovat …" while explaining the wrap behaviour

**Do NOT touch** `PlayingCard.html.twig:75`'s „Zbývá natipovat" — that is a different label on the
same card, and the product owner asked only for the badge. If you think it should follow, say so.
Nothing in `.docs/redesign/` (historical) and nothing in `.docs/DOMAIN.md`.

## What must NOT change

- The **count** and its rule (untipped ∧ still tippable) — this is copy only. `MissingTipCounter`
  stays the one implementation feeding both surfaces.
- The badge's colour (`pill-danger`), its top-right position, and the card staying wholly clickable.
- Zero missing tips still renders **nothing**.
- Czech in the UI, English in code and identifiers. Never „sázka".

## Acceptance criteria

1. Both badges read „Chybí 1 tip" / „Chybí 2 tipy" / „Chybí 5 tipů" as the count dictates.
2. The same soutěž shows the same label on `/nastenka` and `/souteze`.
3. `grep -rn "Chybí natipovat"` finds nothing in `templates/` or `src/`.
4. `composer quality` clean.

## Verification

Follow `.docs/ui-nav/AGENT-BRIEF.md`. **Copy tier**: load `/nastenka` and `/souteze` once, at one
width, and confirm the label on a real card; check the console. Run
`tests/Integration/Portal/MissingTipsBadgeTest.php` and `tests/Integration/Query`. Nothing else.

One thing worth a second look even at copy tier: the badge got **shorter**, and item 30 found that
`.card-glass` is `overflow: hidden` and had silently clipped the longer label at 320 px (fixed with
`max-w-full`). Shorter copy cannot reintroduce that — but confirm the `max-w-full` guard is still
there rather than tidied away.

## Commit

`git commit -o <path> [<path>…]`. Push to `main`. Report your sha; do not touch the status board.
