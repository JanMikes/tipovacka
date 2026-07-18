<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionGuessMatrixQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetCompetitionGuessMatrix $query): CompetitionGuessMatrixResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $requestingUserKey = $query->requestingUserId->toRfc4122();

        $matchesQb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.state != :cancelled')
            ->setParameter('cancelled', SportMatchState::Cancelled)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');
        $this->matchProvider->applyCompetitionMatchFilter($matchesQb, 'm', $competition);

        /** @var list<SportMatch> $matches */
        $matches = $matchesQb->getQuery()->getResult();

        $guessRowsQb = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(g.user) AS userId',
                'IDENTITY(g.sportMatch) AS sportMatchId',
                'g.homeScore AS homeScore',
                'g.awayScore AS awayScore',
                'e.totalPoints AS totalPoints',
            )
            ->from(Guess::class, 'g')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->leftJoin(GuessEvaluation::class, 'e', 'WITH', 'e.guess = g.id')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.state != :cancelled')
            ->setParameter('competitionId', $competition->id)
            ->setParameter('cancelled', SportMatchState::Cancelled);
        $this->matchProvider->applyCompetitionMatchFilter($guessRowsQb, 'm', $competition);

        /** @var list<array{userId: string, sportMatchId: string, homeScore: int, awayScore: int, totalPoints: int|null}> $guessRows */
        $guessRows = $guessRowsQb->getQuery()->getArrayResult();

        $deadlineByMatchKey = $this->deadlineResolver->resolveMany($competition, $matches);

        /** @var array<string, array<string, MatrixCell>> $cellsByUser */
        $cellsByUser = [];
        /** @var array<string, list<int>> $pointsByMatch */
        $pointsByMatch = [];
        /** @var array<string, int> $totalByUser */
        $totalByUser = [];

        foreach ($guessRows as $row) {
            $userKey = $row['userId'];
            $matchKey = $row['sportMatchId'];
            $points = null !== $row['totalPoints'] ? (int) $row['totalPoints'] : null;

            $deadline = $deadlineByMatchKey[$matchKey] ?? null;
            $isHidden = $query->applyHiding
                && $userKey !== $requestingUserKey
                && null !== $deadline
                && $now < $deadline;

            $cellsByUser[$userKey][$matchKey] = $isHidden
                ? MatrixCell::hidden()
                : new MatrixCell(
                    homeScore: (int) $row['homeScore'],
                    awayScore: (int) $row['awayScore'],
                    points: $points,
                );

            if (null !== $points) {
                $pointsByMatch[$matchKey][] = $points;
                $totalByUser[$userKey] = ($totalByUser[$userKey] ?? 0) + $points;
            }
        }

        $matchColumns = [];

        foreach ($matches as $match) {
            $matchKey = $match->id->toRfc4122();
            $points = $pointsByMatch[$matchKey] ?? [];

            $topScores = array_values(array_unique(array_filter($points, static fn (int $p): bool => $p > 0)));
            rsort($topScores);
            $topScores = array_slice($topScores, 0, 3);

            $matchColumns[] = new MatrixMatchColumn(
                sportMatchId: $match->id,
                homeTeam: $match->homeTeam,
                awayTeam: $match->awayTeam,
                kickoffAt: $match->kickoffAt,
                state: $match->state,
                actualHomeScore: $match->homeScore,
                actualAwayScore: $match->awayScore,
                topScores: $topScores,
            );
        }

        $baseRows = [];

        foreach ($memberships as $membership) {
            $user = $membership->user;
            $userKey = $user->id->toRfc4122();
            $hasNickname = null !== $user->nickname && '' !== $user->nickname;
            $hasFullName = '' !== $user->fullName;

            $baseRows[] = [
                'userId' => $user->id,
                'nickname' => $user->displayName,
                'fullName' => ($hasNickname && $hasFullName) ? $user->fullName : null,
                'total' => $totalByUser[$userKey] ?? 0,
                'cells' => $cellsByUser[$userKey] ?? [],
            ];
        }

        usort(
            $baseRows,
            static fn (array $a, array $b): int => $b['total'] <=> $a['total']
                ?: strcmp($a['nickname'], $b['nickname']),
        );

        $memberRows = [];

        foreach ($baseRows as $row) {
            $rank = 1;

            foreach ($baseRows as $other) {
                if ($other['total'] > $row['total']) {
                    ++$rank;
                }
            }

            $memberRows[] = new MatrixMemberRow(
                userId: $row['userId'],
                nickname: $row['nickname'],
                fullName: $row['fullName'],
                totalPoints: $row['total'],
                rank: $rank,
                cells: $row['cells'],
            );
        }

        return new CompetitionGuessMatrixResult(
            matches: $matchColumns,
            members: $memberRows,
        );
    }
}
