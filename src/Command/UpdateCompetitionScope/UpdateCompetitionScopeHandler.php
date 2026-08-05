<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionScope;

use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\CompetitionIsGlobal;
use App\Exception\CompetitionSourcesSportMismatch;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionSourceRepository;
use App\Repository\MatchSourceRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\OwnMatchesSource;
use App\Service\Competition\ScopeLayerWriter;
use App\Service\Identity\ProvideIdentity;
use App\Value\CompetitionSourceSpec;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reconciles a competition's scope layers against the basket the organizer just
 * saved. The create wizard composes the same {@see CompetitionSourceSpec} list
 * from nothing; this one diffs it against what already exists, so a soutěž can
 * gain a zdroj, drop one, or change how it takes one — at any time, exactly like
 * step 1 of the wizard.
 *
 * Two invariants make the edit safe for everybody else:
 *
 * - it only ever writes rows that BELONG TO THIS COMPETITION (its layers, its
 *   selections, its team filters). A zdroj is referenced, never modified — the
 *   matches of a curated (or shared private) zdroj are untouched;
 * - „vlastní zápasy" resolves through {@see OwnMatchesSource}, which hands back a
 *   private zdroj no other soutěž draws from — creating a fresh one when there
 *   is none.
 */
#[AsMessageHandler]
final readonly class UpdateCompetitionScopeHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionSourceRepository $competitionSourceRepository,
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private OwnMatchesSource $ownMatchesSource,
        private ScopeLayerWriter $layerWriter,
        private CompetitionMatchProvider $matchProvider,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionScopeCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);

        if ($competition->isGlobal) {
            throw CompetitionIsGlobal::scopeIsAdminOnly();
        }

        if ([] === $command->layers) {
            throw new \DomainException('Soutěž musí mít aspoň jeden zdroj zápasů.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Which matches the soutěž ALREADY contains, resolved before anything
        // moves: a match that was in scope all along must not be re-anchored as
        // „pozdě přidaný" just because its layer changed mode. Only a Subset layer
        // writes rows that carry an anchor, so a basket without one never pays for
        // the snapshot.
        $established = $this->hasSubsetLayer($command->layers)
            ? $this->matchProvider->matchIdSetFor($competition)
            : [];

        $desired = $this->resolveSources($competition, $command->layers, $now);
        $existingLayers = $this->layersByMatchSource($competition);
        // Read ONCE and incremented in memory: several zdroje may join in one save
        // and nothing is flushed until the whole command succeeds, so re-querying
        // MAX(position) per layer would hand out the same number twice.
        $nextPosition = $this->competitionSourceRepository->nextPosition($competition->id);
        $changed = false;

        // Additions and updates first, removals after: dropping the FIRST layer
        // repoints Competition::$headlineSource at whatever is left, and „what is
        // left" must already include the replacement.
        foreach ($desired as $key => [$matchSource, $spec]) {
            $layer = $existingLayers[$key] ?? null;

            if (null === $layer) {
                $layer = $this->attachLayer($competition, $matchSource, $spec, $nextPosition++, $now);
                $changed = true;
            } elseif ($layer->selectionMode !== $spec->selectionMode || $layer->includePlayoff !== $spec->includePlayoff) {
                $layer->changeScope($spec->selectionMode, $spec->includePlayoff);
                $changed = true;
            }

            $rowsChanged = $this->layerWriter->writeRowsForMode(
                layer: $layer,
                selectedMatchIds: $spec->selectedMatchIds,
                filterTeamIds: $spec->filterTeamIds,
                now: $now,
                establishedMatchIds: $established,
            );

            $changed = $changed || $rowsChanged;
        }

        foreach ($existingLayers as $key => $layer) {
            if (isset($desired[$key])) {
                continue;
            }

            // The zdroj itself survives — it may be a curated one, and even a
            // private one keeps the matches the organizer typed into it.
            $this->layerWriter->clearRows($layer);
            $competition->detachSource($layer);
            $this->competitionSourceRepository->remove($layer);
            $changed = true;
        }

        if ($changed) {
            $competition->recordMatchSelectionChanged($editor, $now);
            $this->matchProvider->forgetSelections($competition->id);
            $this->matchProvider->forgetTeamFilters($competition->id);
        }
    }

    /**
     * @param list<CompetitionSourceSpec> $specs
     */
    private function hasSubsetLayer(array $specs): bool
    {
        foreach ($specs as $spec) {
            if (CompetitionMatchSelectionMode::Subset === $spec->selectionMode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turns the requested specs into „zdroj → spec", resolving „vlastní zápasy"
     * and refusing anything the soutěž may not draw from. A zdroj named twice
     * collapses into one layer — the union is what adding it twice would mean
     * anyway, so the FIRST spec for it wins (mirrors the create handler).
     *
     * @param list<CompetitionSourceSpec> $specs
     *
     * @return array<string, array{0: MatchSource, 1: CompetitionSourceSpec}> keyed by zdroj UUID
     */
    private function resolveSources(Competition $competition, array $specs, \DateTimeImmutable $now): array
    {
        $sport = $competition->headlineSource->sport;
        $ownMatches = null;
        $resolved = [];

        foreach ($specs as $spec) {
            if (null === $spec->matchSourceId) {
                // At most ONE „vlastní zápasy" layer, however many specs ask for
                // it — and it is the zdroj the soutěž already owns, if it has one.
                $matchSource = $ownMatches ??= $this->ownMatchesSource->of($competition)
                    ?? $this->ownMatchesSource->createFor($competition, $now);
            } else {
                $matchSource = $this->matchSourceRepository->get($spec->matchSourceId);
            }

            $key = $matchSource->id->toRfc4122();

            if (isset($resolved[$key])) {
                continue;
            }

            // One sport per soutěž: the rules are configured once, in the sport's
            // own vocabulary, so a mixed scope has no coherent ruleset.
            if (!$matchSource->sport->id->equals($sport->id)) {
                throw CompetitionSourcesSportMismatch::between($sport->name, $matchSource->sport->name);
            }

            // A zdroj already feeding the soutěž may finish („poslední zápas")
            // without the soutěž losing it; a NEW one has to be live.
            if (!$matchSource->isActive && null === $competition->sourceFor($matchSource->id)) {
                throw new \DomainException(sprintf('Zdroj zápasů „%s" už není aktivní, do soutěže ho přidat nelze.', $matchSource->name));
            }

            $resolved[$key] = [$matchSource, $spec];
        }

        return $resolved;
    }

    /**
     * @return array<string, CompetitionSource> the competition's layers keyed by zdroj UUID
     */
    private function layersByMatchSource(Competition $competition): array
    {
        $layers = [];

        foreach ($competition->sources as $layer) {
            $layers[$layer->matchSource->id->toRfc4122()] = $layer;
        }

        return $layers;
    }

    /**
     * A zdroj joining an EXISTING soutěž: `addedAt = now` is the late-add anchor
     * for the whole layer, so its matches get their own deadlines instead of
     * inheriting one that has already passed.
     */
    private function attachLayer(
        Competition $competition,
        MatchSource $matchSource,
        CompetitionSourceSpec $spec,
        int $position,
        \DateTimeImmutable $now,
    ): CompetitionSource {
        $layer = new CompetitionSource(
            id: $this->identity->next(),
            competition: $competition,
            matchSource: $matchSource,
            addedAt: $now,
            selectionMode: $spec->selectionMode,
            includePlayoff: $spec->includePlayoff,
            position: $position,
        );

        $this->competitionSourceRepository->save($layer);
        $competition->attachSource($layer);

        return $layer;
    }
}
