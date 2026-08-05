<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionMatchSelection;

use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\ScopeLayerWriter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class UpdateCompetitionMatchSelectionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private ScopeLayerWriter $layerWriter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionMatchSelectionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);

        $layer = $this->resolveLayer($competition, $command->competitionSourceId);

        if (CompetitionMatchSelectionMode::Subset !== $layer->selectionMode) {
            throw new \DomainException('Výběr zápasů lze upravovat jen u soutěží s vybranými zápasy.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Validation, the „never empty" guard and the fairness anchoring of a
        // re-added match all live in the writer — this screen edits ONE layer,
        // the basket screen edits them all, and both mean the same thing.
        $changed = $this->layerWriter->replaceSelections($layer, $command->selectedMatchIds, $now);

        if ($changed) {
            $competition->recordMatchSelectionChanged($editor, $now);
            $this->matchProvider->forgetSelections($competition->id);
        }
    }

    /**
     * The layer being edited. Null (every single-zdroj competition) means the
     * first one; an id from another competition is refused rather than silently
     * redirected at someone else's scope.
     */
    private function resolveLayer(Competition $competition, ?Uuid $competitionSourceId): CompetitionSource
    {
        if (null === $competitionSourceId) {
            return $competition->sources[0] ?? throw new \DomainException('Soutěž nemá žádný zdroj zápasů.');
        }

        foreach ($competition->sources as $layer) {
            if ($layer->id->equals($competitionSourceId)) {
                return $layer;
            }
        }

        throw new \DomainException('Tento zdroj zápasů do soutěže nepatří.');
    }
}
