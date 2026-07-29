# Competition card + filter bar

The two shared building blocks of every competition list. Introduced by
[item 07](../ui-nav/items/07-page-souteze.md) for `/souteze`, where the organizer grid („Tvé
soutěže") and the public grid („Veřejné soutěže") are **the same components in two contexts** —
a product-owner decision, not an optimisation.

## One query behind both grids

`App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions` takes a
`CompetitionBrowseScope` (`Organized` — competitions the viewer owns; `Discoverable` — global
competitions still joinable) and returns `BrowsableCompetitionItem`s.

It issues a **constant number of statements regardless of list length** — the player count and the
match progress are batch aggregates, and the per-competition match scope is applied by
`CompetitionMatchProvider::applyRowLevelCompetitionMatchFilter`, never by a loop. Its predecessor
(`ListDiscoverableGlobalCompetitions`) ran a `COUNT` per row; do not reintroduce that shape.
`tests/Integration/Query/ListBrowsableCompetitionsQueryTest` pins it.

## `<twig:Competition:Card>`

```twig
<twig:Competition:Card :item="item" context="organizer" />
<twig:Competition:Card :item="item" context="public" :walletBalance="wallet_balance" />
```

| | `organizer` | `public` |
|---|---|---|
| CTA | „Spravovat" → `competition_detail` | „Připojit se" (POST) / „Otevřít" (member) / „Přihlásit se a připojit" (anonymous) / „Dokoupit kredity" (short on credits) |
| Extra | „% dokončeno" bar (`.lb-acc-bar`) | — |

Status pill: Live · N → Ukončeno → Před startem → Probíhá, in that order of precedence.

**Money is an entry fee, never a prize pool.** Entry fees are burned credits and there are no
payouts ([DOMAIN.md](../DOMAIN.md)), so the card shows „Vstupné X" / „Zdarma" — never a bank, pool
or „rozděleno". The same rule is why `/souteze` has no „Výherní bank" hero card.

## `<twig:Competition:FilterBar>`

```twig
<twig:Competition:FilterBar
    prefix="moje-" anchor="#souteze-organizuji"
    :sportOptions="result.sportOptions" :activeSportId="filters.sport"
    :stateOptions="state_options" :activeState="filters.state"
    :visibilityOptions="visibility_options" :activeVisibility="filters.visibility"
    :search="filters.search" :filteredCount="result.filteredCount" :totalCount="result.totalCount" />
```

- **Server-side and linkable.** Chips are plain links carrying query params, so a filtered view
  survives a reload and can be pasted to someone else. No JS.
- **`prefix` keeps two bars on one page independent** — the public bar owns `sport` · `stav` ·
  `hledat` · `strana`, the organizer bar prefixes its own with `moje-`. Each chip link rebuilds
  the *whole* query string, so switching one bar never resets the other.
- **`visibilityOptions = null` hides the „Viditelnost" group** (the public list is public by
  definition).
- **Which „Stav" chips a context offers** comes from `CompetitionStateFilter::forScope()` —
  discovery has no „Skončené", because a global competition over a completed source is not listed
  at all.
- The count reads „X z Y soutěží": filtered over the unfiltered scope total.

Styling reuses existing primitives only — `.card-glass` for the bar, `.lb-tabs`/`.lb-tab` for the
chips, `.stat-lbl` for the group labels.

## `<twig:Competition:PlayingCard>`

The third card, used by „Soutěže, kde tipuješ" only: rank / body / „+N v kole" over an inset
`.cmp-standing` panel, and the one next action that belongs to the competition — „Tipuj N" while
something is still open, „Otevřít" otherwise. Fed by `ListMyPlayingCompetitions`, whose cost is
O(competitions the viewer is in) — the same order as the cards it renders.
