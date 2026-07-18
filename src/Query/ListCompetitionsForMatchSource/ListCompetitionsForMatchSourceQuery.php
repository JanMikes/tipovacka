<?php

declare(strict_types=1);

namespace App\Query\ListCompetitionsForMatchSource;

use App\Entity\Competition;
use App\Repository\CompetitionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListCompetitionsForMatchSourceQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
    ) {
    }

    /**
     * @return list<CompetitionMatchSourceListItem>
     */
    public function __invoke(ListCompetitionsForMatchSource $query): array
    {
        $competitions = $this->competitionRepository->findByMatchSource($query->matchSourceId);

        return array_values(array_map(
            static fn (Competition $g): CompetitionMatchSourceListItem => new CompetitionMatchSourceListItem(
                competitionId: $g->id,
                competitionName: $g->name,
                ownerId: $g->owner->id,
                ownerNickname: $g->owner->displayName,
                createdAt: $g->createdAt,
            ),
            $competitions,
        ));
    }
}
