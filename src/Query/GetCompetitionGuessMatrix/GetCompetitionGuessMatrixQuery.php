<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Repository\CompetitionRepository;
use App\Repository\GuessScorerRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipVisibilityGate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionGuessMatrixQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private GuessScorerRepository $guessScorerRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
        private TipVisibilityGate $visibilityGate,
    ) {
    }

    public function __invoke(GetCompetitionGuessMatrix $query): CompetitionGuessMatrixResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);
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
                'g.id AS guessId',
                'IDENTITY(g.user) AS userId',
                'IDENTITY(g.sportMatch) AS sportMatchId',
                'g.homeScore AS homeScore',
                'g.awayScore AS awayScore',
                'g.periodScoresData AS periodScores',
                'g.overtimeHomeScore AS overtimeHomeScore',
                'g.overtimeAwayScore AS overtimeAwayScore',
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

        /** @var list<array{guessId: \Symfony\Component\Uid\Uuid, userId: string, sportMatchId: string, homeScore: int, awayScore: int, periodScores: list<array{int, int}>|null, overtimeHomeScore: int|null, overtimeAwayScore: int|null, totalPoints: int|null}> $guessRows */
        $guessRows = $guessRowsQb->getQuery()->getArrayResult();

        $scorerNamesByGuess = $this->guessScorerRepository->playerNamesByGuessIds(
            array_map(static fn (array $row) => $row['guessId'], $guessRows),
        );

        // Per-viewer visibility: this viewer's entitlement (premium toggle / own
        // boost) OR each match's userless deadline having passed. The viewer sees
        // their OWN cells always; OTHERS' cells only when entitled/past-deadline.
        $viewer = $this->userRepository->find($query->requestingUserId);
        $othersVisibleByMatch = $this->visibilityGate->othersTipsVisibleByMatch($competition, $viewer, $matches);

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

            $isHidden = $userKey !== $requestingUserKey
                && !($othersVisibleByMatch[$matchKey] ?? false);

            $cellsByUser[$userKey][$matchKey] = $isHidden
                ? MatrixCell::hidden()
                : new MatrixCell(
                    homeScore: (int) $row['homeScore'],
                    awayScore: (int) $row['awayScore'],
                    points: $points,
                    periodScores: $row['periodScores'],
                    overtimeHomeScore: $row['overtimeHomeScore'],
                    overtimeAwayScore: $row['overtimeAwayScore'],
                    scorerNames: $scorerNamesByGuess[$row['guessId']->toRfc4122()] ?? [],
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
