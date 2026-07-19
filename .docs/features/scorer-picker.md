# Scorer picker (tom-select in a LiveComponent)

Multi-select of guessed scorers on the guess form (`Guess:GuessSubmitForm`),
backed by the source's `Player` roster pool with free-typed creation.

## Usage

```twig
<div data-live-ignore>
    <div data-controller="scorer-picker"
         data-scorer-picker-max-value="5"
         data-scorer-picker-home-team-value="{{ match.homeTeam }}"
         data-scorer-picker-away-team-value="{{ match.awayTeam }}">
        <select multiple data-scorer-picker-target="select">
            <optgroup label="{{ match.homeTeam }}"><option value="h|Jan Novák">Jan Novák</option>…</optgroup>
            <optgroup label="{{ match.awayTeam }}">…</optgroup>
        </select>
        <label><input type="radio" value="home" checked data-scorer-picker-target="sideRadio"> {{ match.homeTeam }}</label>
        <label><input type="radio" value="away" data-scorer-picker-target="sideRadio"> {{ match.awayTeam }}</label>
        <input type="hidden" data-model="scorersJson" data-scorer-picker-target="payload">
    </div>
</div>
```

## How it works

- **Option values** encode the team side as a 2-char prefix: `h|<name>` / `a|<name>`.
  Options are rendered server-side from `PlayerRepository::listBySourceAndTeam` for
  both teams (local tom-select filtering = the autocomplete UX, no remote endpoint,
  no voter coupling).
- **Free-create**: typing an unknown name creates an option under the side chosen by
  the `sideRadio` toggle („Nového hráče přidat do týmu"). The actual `Player` row is
  created server-side on submit via `PlayerRepository::findOrCreate` (case-insensitive,
  same pool as the organizer's score-entry sheet). Because `createOnBlur` fires before
  a clicked radio's `checked` state flips, the controller captures the intended side on
  `pointerdown` (`pendingSide`) — typing a name and clicking the other team's radio
  creates the player under the team being clicked.
- **Persisted side**: the submitted side is stored on `GuessScorer.side` (like
  `MatchEvent`) — prefill and pass-through updates read the persisted enum, never
  compare the player's team name to the match's current team strings.
- **LiveComponent integration**: the whole picker sits in a `data-live-ignore` island
  so live re-renders never destroy the tom-select DOM. Every change serializes
  `[{side, name}, …]` into the hidden input bound to the writable `scorersJson`
  LiveProp and dispatches `input` — the only channel between JS island and component.
- **Cap**: `maxItems` mirrors `GuessScorer::MAX_PER_GUESS` (5); the command path
  enforces it authoritatively (`TooManyGuessScorers`, 422).
