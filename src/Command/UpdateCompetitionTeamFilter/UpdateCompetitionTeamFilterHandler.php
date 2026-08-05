<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionTeamFilter;

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
final readonly class UpdateCompetitionTeamFilterHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private ScopeLayerWriter $layerWriter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionTeamFilterCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);

        $layer = $this->resolveLayer($competition, $command->competitionSourceId);

        if (CompetitionMatchSelectionMode::Teams !== $layer->selectionMode) {
            throw new \DomainException('Filtr týmů lze upravovat jen u soutěží se zápasy vybraných týmů.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Validation, the „never empty" guard and the „team must belong to this
        // zdroj's scope" rule live in the writer — shared with the whole-basket
        // screen, so the two never drift apart.
        $changed = $this->layerWriter->replaceTeamFilters($layer, $command->teamIds, $now);

        if ($changed) {
            $competition->recordMatchSelectionChanged($editor, $now);
            $this->matchProvider->forgetTeamFilters($competition->id);
        }
    }

    /** @see \App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionHandler */
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
