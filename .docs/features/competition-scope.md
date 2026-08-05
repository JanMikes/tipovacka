# Zápasy soutěže — the basket, editable any time

The košík of zdroje a soutěž draws from is not a create-time decision. The organizer of a
**private** soutěž edits it for the whole life of the competition at
`/souteze/{id}/zapasy` (`competition_scope`, linked from „Nastavení soutěže"), using the
**same form as step 1 of the create wizard**.

```
ComposesMatchScope (trait)  ── LiveProps + LiveActions + derived read models
   │                            layers · draftOpen · editingIndex · sourceId ·
   │                            selectionMode · includePlayoff · selectedMatchIds ·
   │                            selectedTeamIdsCsv · errorMessage
   ├── Competition:CreateWizard  step 1  → CreateCompetitionCommand
   └── Competition:ScopeEditor   /zapasy → UpdateCompetitionScopeCommand
templates/components/Competition/_scope_basket.html.twig   (the cards)
templates/components/Competition/_scope_editor.html.twig   (add / edit one zdroj)
```

Both components implement two hooks the trait declares: `scopeCatalog()` (the
`MatchScopeCatalog` service — everything the editor READS: composable zdroje, a zdroj's
selectable matches, its teams, and the `ScopeDraft` preview) and `currentUser()`. The wizard
additionally overrides `isGlobalScope()` for its admin branch. `_scope_editor.html.twig`
takes `allow_from_scratch` (wizard only) and `scope_commit_hint`.

## What a save does

`UpdateCompetitionScopeCommand` carries the WHOLE desired basket as
`list<CompetitionSourceSpec>` — the same value object the wizard submits — and
`UpdateCompetitionScopeHandler` reconciles it against the layers that exist:

- a zdroj in the basket but not in the soutěž ⇒ a new `CompetitionSource` at the end,
  `addedAt = now` (the late-add anchor: its matches get their own deadlines);
- a zdroj in both ⇒ `changeScope(mode, includePlayoff)`, keeping its original `addedAt`;
- a zdroj only in the soutěž ⇒ its rows are cleared and the layer removed. **The zdroj
  itself survives** — it may be curated, and a private one keeps the matches typed into it.

Per-layer rows (subset selections / team filters) are written by **`ScopeLayerWriter`**, the
one home of „what a layer's rows mean" — shared with the two narrow per-layer screens
(`UpdateCompetitionMatchSelection`, `UpdateCompetitionTeamFilter`), so validation, the
never-empty guard and the fairness anchoring are written down once.

**Fairness anchoring.** A match re-entering a Subset layer is „pozdě přidaný" only if it was
never in the soutěž. The writer anchors it to `competition.createdAt` when it already carries
active guesses OR when it was in scope before the edit began (`establishedMatchIds`, snapshotted
by the handler from `CompetitionMatchProvider` before anything moves). Without that, switching
a layer from „všechny zápasy" to „vybrané zápasy" would hand every match a fresh deadline and
reopen already-closed tips.

Global competitions are refused (`CompetitionIsGlobal::scopeIsAdminOnly()`) and the page
redirects them to „Nastavení" — their scope is an admin decision, and players joined under
advertised terms. They keep the old per-layer screen (`competition_match_selection`).

## „Vlastní zápasy" and the exclusivity rule

The basket marks the soutěž's own private zdroj with an **empty `sourceId`**, exactly as the
wizard marks a zdroj that does not exist yet — so the card reads „Vlastní zápasy" and the
command resolves it back to the same zdroj. `ownMatchesSourceId()` points the *preview* at the
real zdroj, so the card counts matches that are already there instead of reporting zero.

**`OwnMatchesSource`** is the single answer to „may this soutěž edit these matches in place?":
a zdroj qualifies when it is private, owned by the competition's owner, and **no other soutěž
draws from it** (`CompetitionRepository::countOtherCompetitionsUsingMatchSource`). Only then
does the page offer „Přidat zápas" / „Upravit" / „Odebrat".

The exclusivity is derived, not a schema invariant: reusing your own private zdroj in a second
soutěž stays legal (two partičky tipping one custom turnaj), and the moment it happens the
in-competition match editing closes itself — that zdroj is then edited from its own page, where
the organizer can see both soutěže on the screen. This is the whole point: **editing a soutěž
must never change what another soutěž tips.**

Match editing itself reuses the ordinary match screens; `ScopeReturn` (`?soutez={uuid}`) sends
them back here after a save or delete. It carries a competition UUID, never a URL — the target
route is fixed, so a crafted parameter cannot redirect anywhere else.

## Judgment calls

- **One implementation, two surfaces.** Duplicating the basket into a second component was the
  obvious shortcut and the wrong one — the sport lock, the „already basketed" rule, the stale-id
  intersections and the tom-select island contract would have drifted immediately.
- **A save is a full round trip, not a re-render.** `save()` returns a redirect: a freshly created
  „Vlastní zápasy" zdroj gains an id, and its match panel has to appear with it.
- **An open editor commits itself** (`commitOpenDraft()`), on both surfaces — nobody has to press
  „Přidat" before „Uložit".
- **Removing a layer does not delete the zdroj.** Non-destructive, and the alternative (soft-deleting
  a private zdroj on layer removal) would throw away hand-typed matches on a misclick.
- **Two memos, both keyed, neither a LiveProp.** The trait's getters re-run on every template access, and
  `composableSources` (2 queries) sits under `selectedSource → sourcesById → availableSources →
  lockedSport → allSourcesById`, while `scopeDraft` hydrates every match of every basketed zdroj.
  Memoizing them per request took the page from **37 queries to 22**. Each memo carries what it was
  computed for (`isGlobalScope` / the `layers` array), so an action that changes the basket can never
  serve a stale preview.
- **Per-layer counts come from the SAME resolve as the summary** (`ScopeDraft::$layerCounts`) — the
  earlier shape resolved the whole basket once per card.
- **The preview and the real scope have two evaluators.** `ScopeDraftResolver` (PHP-side, spec-driven)
  answers „what would this basket hold" — it must, because an unsaved basket has no layers to query —
  while `CompetitionMatchProvider` (DQL, layer-driven) answers what players actually tip. They can
  differ on the margin (the resolver drops cancelled matches). `ScopeDraftResolverTest` pins them
  against each other; unifying them is a real refactor, deliberately not attempted here.

## Not done (and why)

- **The old per-layer screen (`competition_match_selection`) still accepts private competitions** by
  direct URL — it is simply no longer linked for them. Retiring it is a product call, not a cleanup,
  and it is still the surface a global competition's team filter is edited from.
- **Exclusivity is not a schema invariant**, so a private zdroj that GAINS a second soutěž stops being
  „Vlastní zápasy" for the first one and the button reappears — pressing it deliberately creates a
  second private zdroj. That is the honest consequence of keeping zdroj reuse legal.
