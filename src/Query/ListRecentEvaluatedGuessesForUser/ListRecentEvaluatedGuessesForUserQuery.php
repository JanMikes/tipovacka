<?php

declare(strict_types=1);

namespace App\Query\ListRecentEvaluatedGuessesForUser;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Enum\SportMatchState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListRecentEvaluatedGuessesForUserQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<EvaluatedGuessItem>
     */
    public function __invoke(ListRecentEvaluatedGuessesForUser $query): array
    {
        /** @var list<array{guess: Guess, totalPoints: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('g AS guess, e.totalPoints AS totalPoints')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('m.tournament', 't')
            ->innerJoin('g.group', 'gr')
            ->innerJoin(GuessEvaluation::class, 'e', 'WITH', 'e.guess = g.id')
            ->where('g.user = :userId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.state = :finished')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('gr.deletedAt IS NULL')
            ->setParameter('userId', $query->userId)
            ->setParameter('finished', SportMatchState::Finished)
            ->orderBy('m.kickoffAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static function (array $row): EvaluatedGuessItem {
                $guess = $row['guess'];
                \assert(null !== $guess->sportMatch->homeScore);
                \assert(null !== $guess->sportMatch->awayScore);

                return new EvaluatedGuessItem(
                    sportMatchId: $guess->sportMatch->id,
                    tournamentId: $guess->sportMatch->tournament->id,
                    tournamentName: $guess->sportMatch->tournament->name,
                    groupId: $guess->group->id,
                    groupName: $guess->group->name,
                    homeTeam: $guess->sportMatch->homeTeam,
                    awayTeam: $guess->sportMatch->awayTeam,
                    kickoffAt: $guess->sportMatch->kickoffAt,
                    actualHomeScore: $guess->sportMatch->homeScore,
                    actualAwayScore: $guess->sportMatch->awayScore,
                    myHomeScore: $guess->homeScore,
                    myAwayScore: $guess->awayScore,
                    totalPoints: (int) $row['totalPoints'],
                );
            },
            $rows,
        );
    }
}
