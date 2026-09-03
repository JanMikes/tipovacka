<?php

declare(strict_types=1);

namespace App\Twig\Components\Competition;

use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Service\Competition\MatchScopeCatalog;
use App\Value\CompetitionSourceSpec;
use App\Value\ScopeDraft;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The „Zápasy soutěže" basket — the košík of zdroje a soutěž draws from, with
 * the editor that puts one in it. ONE implementation, two surfaces:
 *
 * - {@see CreateWizard} step 1, composing a soutěž that does not exist yet;
 * - {@see ScopeEditor} at „/souteze/{id}/zapasy", editing one that does.
 *
 * The organizer therefore meets the same form the day they create the soutěž and
 * every day after — which is the point: the scope is not a create-time decision.
 * The templates are shared too (`_scope_basket.html.twig` + `_scope_editor.html.twig`).
 *
 * State: `layers` is a writable array LiveProp of PLAIN arrays (no entities, no
 * Uuid objects), so the whole basket survives the round trip; everything else the
 * templates render is a derived getter. A layer is only ever built in ONE place
 * ({@see draftLayer}), so the sport lock and the empty-selection guards are
 * enforced once rather than at every entry point.
 *
 * An empty `sourceId` means „vlastní zápasy" — the soutěž's own private zdroj.
 * In the wizard it does not exist yet; on the manage screen it does, and
 * {@see ownMatchesSourceId} points the preview at it while the layer keeps the
 * same empty marker, so both surfaces speak one language.
 */
trait ComposesMatchScope
{
    /**
     * Committed scope layers, in display order.
     *
     * @var list<array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>}>
     */
    #[LiveProp(writable: true)]
    public array $layers = [];

    /**
     * Whether the „add a zdroj" editor is open. It is the whole step while the
     * basket is empty, so a single-source soutěž — still the overwhelmingly
     * common one — is composed exactly as it was before the basket existed.
     */
    #[LiveProp(writable: true)]
    public bool $draftOpen = true;

    /** Index of the layer being re-edited, or null while adding a new one. */
    #[LiveProp]
    public ?int $editingIndex = null;

    #[LiveProp(writable: true)]
    public bool $fromScratch = false;

    #[LiveProp(writable: true)]
    public string $sportId = Sport::FOOTBALL_ID;

    /** From-scratch zdroj only: a drawn match may go on to extra time / penalties (cups, play-offs). */
    #[LiveProp(writable: true)]
    public bool $hasOvertime = false;

    #[LiveProp(writable: true)]
    public string $sourceId = '';

    #[LiveProp(writable: true)]
    public string $selectionMode = 'all';

    #[LiveProp(writable: true)]
    public bool $includePlayoff = true;

    /** @var list<string> selected sport-match UUIDs (subset mode) */
    #[LiveProp(writable: true)]
    public array $selectedMatchIds = [];

    /** Comma-joined filter team UUIDs (teams mode); synced from the tom-select island. */
    #[LiveProp(writable: true)]
    public string $selectedTeamIdsCsv = '';

    #[LiveProp]
    public ?string $errorMessage = null;

    /**
     * The zdroj „Vlastní zápasy" already resolves to. Null in the wizard — the
     * zdroj is created by the handler at submit — and set by the manage screen to
     * the soutěž's own private zdroj, which is what lets the preview count the
     * matches the organizer has already typed in.
     */
    #[LiveProp]
    public ?string $ownMatchesSourceId = null;

    /**
     * Per-request memo of {@see MatchScopeCatalog::composableSources} — TWO
     * queries, and the getters below reach for it constantly: one
     * `this.selectedSource` in a template walks selectedSource → sourcesById →
     * availableSources → lockedSport → allSourcesById. The set depends only on
     * the user and {@see isGlobalScope}, never on the basket, so filtering it per
     * getter stays correct while the fetch happens once. NOT a LiveProp — it is a
     * cache, not state, and every live request re-hydrates the component.
     *
     * @var array{bool, list<MatchSource>}|null the {@see isGlobalScope} it was fetched for, and the set
     */
    private ?array $composableSourcesMemo = null;

    /**
     * Per-request memo of {@see ScopeDraft}, keyed on the basket it was resolved
     * for — the summary line and every card read it, and resolving hydrates every
     * match of every basketed zdroj. Keying on `$layers` (plain scalars, so `===`
     * is exact) means an action that changes the basket can never serve a stale
     * preview.
     *
     * @var array{list<array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>}>, ScopeDraft}|null
     */
    private ?array $scopeDraftMemo = null;

    abstract private function scopeCatalog(): MatchScopeCatalog;

    abstract private function currentUser(): User;

    /**
     * GLOBAL scope: curated zdroje only, no hand-picked subset, no „vlastní
     * zápasy". Overridden by the wizard's admin branch; the manage screen is
     * private-only and never enters it.
     */
    public function isGlobalScope(): bool
    {
        return false;
    }

    // ---- Read models for the template ------------------------------------

    /**
     * The basket rows: one card per committed layer, with the copy describing
     * what it contributes. „Vlastní zápasy" is a layer like any other — the user
     * never meets the word „zdroj" for it.
     *
     * @var list<array{index: int, name: string, sportName: string, scope: string, isOwnMatches: bool, matchCount: int}>
     */
    public array $layerCards {
        get {
            $counts = $this->scopeDraft->layerCounts;
            // Hoisted: this getter fetches, and the loop would re-fetch per row.
            $sourcesById = $this->allSourcesById;
            $cards = [];

            foreach ($this->layers as $index => $layer) {
                $source = '' === $layer['sourceId'] ? null : ($sourcesById[$layer['sourceId']] ?? null);

                $cards[] = [
                    'index' => $index,
                    'name' => null === $source ? 'Vlastní zápasy' : $source->name,
                    'sportName' => null === $source ? $this->selectedSport->name : $source->sport->name,
                    'scope' => $this->scopeLabel($layer),
                    'isOwnMatches' => null === $source,
                    'matchCount' => $counts[$index] ?? 0,
                ];
            }

            return $cards;
        }
    }

    /**
     * The whole basket resolved: the fixture list the soutěž would hold, its
     * span, any fixture taken twice from different zdroje, and what each layer
     * contributes on its own ({@see ScopeDraft::$layerCounts}) — one resolve for
     * the summary line AND every card, rather than one per card.
     */
    public ScopeDraft $scopeDraft {
        get {
            if (null !== $this->scopeDraftMemo && $this->scopeDraftMemo[0] === $this->layers) {
                return $this->scopeDraftMemo[1];
            }

            $specs = [];

            foreach ($this->layers as $layer) {
                $specs[] = $this->previewSpecFor($layer);
            }

            $draft = $this->scopeCatalog()->resolveDraft($specs);
            $this->scopeDraftMemo = [$this->layers, $draft];

            return $draft;
        }
    }

    /**
     * The basket as the domain takes it: „vlastní zápasy" travels as a null
     * zdroj, which the command handler resolves (or creates).
     *
     * @var list<CompetitionSourceSpec>
     */
    public array $layerSpecs {
        get {
            $specs = [];

            foreach ($this->layers as $layer) {
                $specs[] = $this->specFor($layer);
            }

            return $specs;
        }
    }

    public bool $hasLayers {
        get => [] !== $this->layers;
    }

    public bool $hasOwnMatchesLayer {
        get {
            foreach ($this->layers as $layer) {
                if ('' === $layer['sourceId']) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * The sport every further zdroj must match, once the first layer fixed it.
     * Null while the basket is empty. Rules are configured once per soutěž and
     * phrased in the sport's own words, so a mixed scope has no coherent
     * ruleset — see .docs/DOMAIN.md §Core model.
     */
    public ?Sport $lockedSport {
        get {
            $first = $this->layers[0] ?? null;

            if (null === $first) {
                return null;
            }

            if ('' === $first['sourceId']) {
                return $this->selectedSport;
            }

            return ($this->allSourcesById[$first['sourceId']] ?? null)?->sport;
        }
    }

    /**
     * The zdroje offered as „Zdroj zápasů": everything composable, minus the
     * wrong sport, minus what the basket already holds.
     *
     * @var list<MatchSource>
     */
    public array $availableSources {
        get {
            $locked = $this->lockedSport;
            $taken = [];

            foreach ($this->layers as $index => $layer) {
                if ('' !== $layer['sourceId'] && $index !== $this->editingIndex) {
                    $taken[$layer['sourceId']] = true;
                }
            }

            // The soutěž's own zdroj is „Vlastní zápasy" in this basket, never a
            // zdroj to pick from the list.
            if (null !== $this->ownMatchesSourceId) {
                $taken[$this->ownMatchesSourceId] = true;
            }

            $sources = array_filter(
                $this->composableSources(),
                // One sport per soutěž, and a zdroj already in the basket is not
                // offered again — adding it twice would mean the same union.
                static fn (MatchSource $source): bool => (null === $locked || $source->sport->id->equals($locked->id))
                    && !isset($taken[$source->id->toRfc4122()]),
            );

            return array_values($sources);
        }
    }

    /**
     * Every zdroj the user may reference, INCLUDING those already basketed —
     * `availableSources` hides those, but the basket still has to render their
     * names. Keyed by UUID.
     *
     * @var array<string, MatchSource>
     */
    public array $allSourcesById {
        get {
            $byId = [];

            foreach ($this->composableSources() as $source) {
                $byId[$source->id->toRfc4122()] = $source;
            }

            return $byId;
        }
    }

    /** @var array<string, MatchSource> */
    public array $sourcesById {
        get {
            $byId = [];

            foreach ($this->availableSources as $source) {
                $byId[$source->id->toRfc4122()] = $source;
            }

            return $byId;
        }
    }

    public ?MatchSource $selectedSource {
        get => '' !== $this->sourceId ? ($this->sourcesById[$this->sourceId] ?? null) : null;
    }

    public bool $isSubset {
        get => 'subset' === $this->selectionMode;
    }

    public bool $isTeams {
        get => 'teams' === $this->selectionMode;
    }

    /**
     * Teams that play in the chosen source — the pool the team-filter picker
     * offers and the basket validates the selection against.
     *
     * @var list<Team>
     */
    public array $sourceTeams {
        get {
            $source = $this->selectedSource;

            return null === $source ? [] : $this->scopeCatalog()->teamsIn($source);
        }
    }

    /**
     * The currently-picked filter teams as {id, name}. Rendered as <option selected>
     * so the tom-select chips survive step navigation (they reappear from the DOM,
     * not from bare ids). Stale ids from a previously chosen source are dropped.
     *
     * @var list<array{id: string, name: string}>
     */
    public array $filterTeamOptions {
        get {
            if ('' === $this->selectedTeamIdsCsv) {
                return [];
            }

            $selected = array_flip($this->parseCsvIds($this->selectedTeamIdsCsv));
            $options = [];

            foreach ($this->sourceTeams as $team) {
                $id = $team->id->toRfc4122();

                if (isset($selected[$id])) {
                    $options[] = ['id' => $id, 'name' => $team->name];
                }
            }

            return $options;
        }
    }

    /**
     * Matches of the chosen source grouped by round (fallback: kickoff date).
     *
     * @var array<string, list<SportMatch>>
     */
    public array $groupedMatches {
        get {
            $source = $this->selectedSource;

            return null === $source ? [] : $this->scopeCatalog()->selectableMatchesByRound($source);
        }
    }

    /** @var list<Sport> */
    public array $availableSports {
        get => $this->scopeCatalog()->sports();
    }

    public Sport $selectedSport {
        get => $this->scopeCatalog()->sport(Uuid::fromString($this->sportId));
    }

    // ---- Actions ---------------------------------------------------------

    /**
     * Commits the draft editor into the basket. This is the ONE place a layer
     * is added, so the sport lock and the „already in the basket" rule are
     * enforced once rather than at every entry point.
     */
    #[LiveAction]
    public function addLayer(): void
    {
        $this->errorMessage = null;

        $layer = $this->draftLayer();

        if (null === $layer) {
            return;
        }

        if (null !== $this->editingIndex && isset($this->layers[$this->editingIndex])) {
            $this->layers[$this->editingIndex] = $layer;
        } else {
            $this->layers[] = $layer;
        }

        $this->resetDraft();
        // Composing more than one zdroj is the exception, so the editor closes
        // and the basket becomes the step. „Přidat zdroj" reopens it.
        $this->draftOpen = false;
    }

    /**
     * Commits the draft and immediately reopens an empty editor — the one
     * gesture that turns a single-zdroj soutěž into a multi-zdroj one, so it is
     * offered as soon as the editor holds a usable zdroj rather than once a
     * basket already exists.
     */
    #[LiveAction]
    public function addLayerAndContinue(): void
    {
        $this->addLayer();

        if (null !== $this->errorMessage) {
            return;
        }

        $this->resetDraft();
        $this->draftOpen = true;
    }

    #[LiveAction]
    public function editLayer(#[LiveArg] int $index): void
    {
        if (!isset($this->layers[$index])) {
            return;
        }

        $layer = $this->layers[$index];

        $this->errorMessage = null;
        $this->editingIndex = $index;
        $this->sourceId = $layer['sourceId'];
        $this->fromScratch = '' === $layer['sourceId'];
        $this->selectionMode = $layer['mode'];
        $this->includePlayoff = $layer['includePlayoff'];
        $this->selectedMatchIds = $layer['matchIds'];
        $this->selectedTeamIdsCsv = implode(',', $layer['teamIds']);
        $this->draftOpen = true;
    }

    #[LiveAction]
    public function removeLayer(#[LiveArg] int $index): void
    {
        if (!isset($this->layers[$index])) {
            return;
        }

        $remaining = $this->layers;
        unset($remaining[$index]);
        $this->layers = array_values($remaining);
        $this->errorMessage = null;

        if ([] === $this->layers) {
            $this->draftOpen = true;
        }
    }

    /** Opens the editor for a NEW zdroj (as opposed to re-editing one). */
    #[LiveAction]
    public function startLayer(): void
    {
        $this->errorMessage = null;
        $this->resetDraft();
        $this->draftOpen = true;
    }

    /**
     * Adds „Vlastní zápasy" — the soutěž's own private zdroj. It needs no editor:
     * in the wizard the matches are entered once the soutěž exists, and on the
     * manage screen the card itself is where they are entered.
     */
    #[LiveAction]
    public function addOwnMatchesLayer(): void
    {
        $this->errorMessage = null;

        if ($this->hasOwnMatchesLayer) {
            return;
        }

        $this->layers[] = [
            'sourceId' => '',
            'mode' => 'all',
            'includePlayoff' => true,
            'matchIds' => [],
            'teamIds' => [],
        ];
        $this->resetDraft();
        $this->draftOpen = false;
    }

    #[LiveAction]
    public function cancelLayer(): void
    {
        $this->errorMessage = null;
        $this->resetDraft();
        $this->draftOpen = [] === $this->layers;
    }

    // ---- Basket helpers ---------------------------------------------------

    /**
     * The composable zdroje, fetched once per request. See {@see $composableSourcesMemo}.
     *
     * @return list<MatchSource>
     */
    private function composableSources(): array
    {
        $isGlobal = $this->isGlobalScope();

        if (null !== $this->composableSourcesMemo && $this->composableSourcesMemo[0] === $isGlobal) {
            return $this->composableSourcesMemo[1];
        }

        $sources = $this->scopeCatalog()->composableSources($this->currentUser(), $isGlobal);
        $this->composableSourcesMemo = [$isGlobal, $sources];

        return $sources;
    }

    /**
     * Leaving the editor open with a usable zdroj commits it, so nobody has to
     * press „Přidat" before „Pokračovat" / „Uložit". Returns false when the
     * commit failed (with `$errorMessage` set).
     */
    private function commitOpenDraft(): bool
    {
        if ($this->draftOpen && ($this->fromScratch || null !== $this->selectedSource)) {
            $this->addLayer();

            if (null !== $this->errorMessage) {
                return false;
            }
        }

        return true;
    }

    /**
     * The draft editor's current state as a basket layer, or null (with
     * `$errorMessage` set) when it does not describe a usable one.
     *
     * @return array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>}|null
     */
    private function draftLayer(): ?array
    {
        if ($this->fromScratch) {
            return [
                'sourceId' => '',
                'mode' => 'all',
                'includePlayoff' => true,
                'matchIds' => [],
                'teamIds' => [],
            ];
        }

        $source = $this->selectedSource;

        if (null === $source) {
            $this->errorMessage = 'Vyberte prosím zdroj zápasů.';

            return null;
        }

        $locked = $this->lockedSport;

        if (null !== $locked && !$source->sport->id->equals($locked->id) && 0 !== $this->editingIndex) {
            $this->errorMessage = sprintf(
                'Soutěž může kombinovat jen zdroje stejného sportu — už jste vybrali %s.',
                mb_strtolower($locked->name),
            );

            return null;
        }

        $matchIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $this->selectedMatchUuids());
        $teamIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $this->filterTeamUuids());

        if ($this->isSubset && [] === $matchIds) {
            $this->errorMessage = 'Vyberte prosím alespoň jeden zápas.';

            return null;
        }

        if ($this->isTeams && [] === $teamIds) {
            $this->errorMessage = 'Vyberte prosím alespoň jeden tým.';

            return null;
        }

        return [
            'sourceId' => $source->id->toRfc4122(),
            'mode' => $this->selectionMode,
            'includePlayoff' => $this->includePlayoff,
            'matchIds' => $matchIds,
            'teamIds' => $teamIds,
        ];
    }

    private function resetDraft(): void
    {
        $this->editingIndex = null;
        $this->sourceId = '';
        $this->fromScratch = false;
        $this->selectionMode = 'all';
        $this->includePlayoff = true;
        $this->selectedMatchIds = [];
        $this->selectedTeamIdsCsv = '';
    }

    /**
     * Selected match UUIDs, intersected with the chosen source's matches so a
     * stale selection left over from a previously chosen source is dropped.
     *
     * @return list<Uuid>
     */
    private function selectedMatchUuids(): array
    {
        if ($this->fromScratch || !$this->isSubset) {
            return [];
        }

        $validIds = [];

        foreach ($this->groupedMatches as $matches) {
            foreach ($matches as $match) {
                $validIds[$match->id->toRfc4122()] = true;
            }
        }

        $result = [];

        foreach ($this->selectedMatchIds as $id) {
            if (isset($validIds[$id])) {
                $result[] = Uuid::fromString($id);
            }
        }

        return $result;
    }

    /**
     * Filter team UUIDs, intersected with the chosen source's teams so a stale
     * selection left over from a previously chosen source is dropped.
     *
     * @return list<Uuid>
     */
    private function filterTeamUuids(): array
    {
        if (!$this->isTeams) {
            return [];
        }

        $validIds = [];

        foreach ($this->sourceTeams as $team) {
            $validIds[$team->id->toRfc4122()] = true;
        }

        $result = [];

        foreach ($this->parseCsvIds($this->selectedTeamIdsCsv) as $id) {
            if (isset($validIds[$id])) {
                $result[] = Uuid::fromString($id);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function parseCsvIds(string $csv): array
    {
        $ids = [];

        foreach (explode(',', $csv) as $raw) {
            $raw = trim($raw);

            if ('' !== $raw && Uuid::isValid($raw)) {
                $ids[] = $raw;
            }
        }

        return $ids;
    }

    /**
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function specFor(array $layer): CompetitionSourceSpec
    {
        return $this->buildSpec($layer, '' === $layer['sourceId'] ? null : $layer['sourceId']);
    }

    /**
     * The spec the PREVIEW resolves: identical, except that „vlastní zápasy"
     * points at the zdroj it already resolves to, so the manage screen counts
     * the matches the organizer has typed in instead of reporting zero.
     *
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function previewSpecFor(array $layer): CompetitionSourceSpec
    {
        return $this->buildSpec($layer, '' === $layer['sourceId'] ? $this->ownMatchesSourceId : $layer['sourceId']);
    }

    /**
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function buildSpec(array $layer, ?string $matchSourceId): CompetitionSourceSpec
    {
        return new CompetitionSourceSpec(
            matchSourceId: null === $matchSourceId ? null : Uuid::fromString($matchSourceId),
            selectionMode: CompetitionMatchSelectionMode::from($layer['mode']),
            includePlayoff: $layer['includePlayoff'],
            selectedMatchIds: array_map(static fn (string $id): Uuid => Uuid::fromString($id), $layer['matchIds']),
            filterTeamIds: array_map(static fn (string $id): Uuid => Uuid::fromString($id), $layer['teamIds']),
        );
    }

    /**
     * @param array{sourceId: string, mode: string, includePlayoff: bool, matchIds: list<string>, teamIds: list<string>} $layer
     */
    private function scopeLabel(array $layer): string
    {
        if ('' === $layer['sourceId']) {
            return 'Zápasy, které si sami zadáte';
        }

        return match ($layer['mode']) {
            'subset' => sprintf('Vybrané zápasy (%d)', count($layer['matchIds'])),
            'teams' => sprintf('Jen zápasy vybraných týmů (%d)', count($layer['teamIds'])),
            default => $layer['includePlayoff'] ? 'Všechny zápasy' : 'Všechny zápasy kromě playoff',
        };
    }
}
