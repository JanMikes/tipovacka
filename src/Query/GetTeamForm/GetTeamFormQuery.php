<?php

declare(strict_types=1);

namespace App\Query\GetTeamForm;

use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTeamFormQuery
{
    public function __construct(
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetTeamForm $query): TeamFormResult
    {
        if ([] === $query->teamIds) {
            return new TeamFormResult([]);
        }

        $teamIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $query->teamIds);

        // ONE query for every requested team: the competition's finished matches
        // in which any of them played, home or away.
        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(m.homeTeam) AS homeTeamId',
                'IDENTITY(m.awayTeam) AS awayTeamId',
                'm.homeScore AS homeScore',
                'm.awayScore AS awayScore',
            )
            ->from(SportMatch::class, 'm')
            ->andWhere('m.state = :tf_finished')
            ->andWhere('m.homeScore IS NOT NULL')
            ->andWhere('m.awayScore IS NOT NULL')
            ->andWhere('(m.homeTeam IN (:tf_teams) OR m.awayTeam IN (:tf_teams))')
            ->setParameter('tf_finished', SportMatchState::Finished)
            ->setParameter('tf_teams', $teamIds);

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $query->competitionId);

        /** @var list<array{homeTeamId: string, awayTeamId: string, homeScore: int, awayScore: int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        /** @var array<string, array{wins: int, draws: int, losses: int, played: int}> $tally */
        $tally = [];

        foreach ($rows as $row) {
            // The REGULAR score decides: it stays the primary result even when a
            // shootout/overtime score exists (see SportMatch::$overtimeHomeScore).
            $homeGoals = (int) $row['homeScore'];
            $awayGoals = (int) $row['awayScore'];

            foreach ([[$row['homeTeamId'], $homeGoals, $awayGoals], [$row['awayTeamId'], $awayGoals, $homeGoals]] as [$teamId, $scored, $conceded]) {
                if (!in_array($teamId, $teamIds, true)) {
                    continue;
                }

                $tally[$teamId] ??= ['wins' => 0, 'draws' => 0, 'losses' => 0, 'played' => 0];
                ++$tally[$teamId]['played'];

                if ($scored > $conceded) {
                    ++$tally[$teamId]['wins'];
                } elseif ($scored === $conceded) {
                    ++$tally[$teamId]['draws'];
                } else {
                    ++$tally[$teamId]['losses'];
                }
            }
        }

        return new TeamFormResult(array_map(
            static fn (array $counts): TeamForm => new TeamForm(
                wins: $counts['wins'],
                draws: $counts['draws'],
                losses: $counts['losses'],
                played: $counts['played'],
            ),
            $tally,
        ));
    }
}
