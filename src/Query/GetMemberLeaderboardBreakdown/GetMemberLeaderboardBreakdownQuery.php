<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Repository\GroupRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMemberLeaderboardBreakdownQuery
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private GuessEvaluationRepository $evaluationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMemberLeaderboardBreakdown $query): MemberBreakdownResult
    {
        $group = $this->groupRepository->get($query->groupId);
        $user = $this->userRepository->get($query->userId);

        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.tournament = :tournamentId')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state = :finished')
            ->setParameter('tournamentId', $group->tournament->id)
            ->setParameter('finished', SportMatchState::Finished)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<Guess> $guesses */
        $guesses = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.user = :userId')
            ->andWhere('g.group = :groupId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.state = :finished')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', $group->id)
            ->setParameter('finished', SportMatchState::Finished)
            ->getQuery()
            ->getResult();

        $guessesByMatch = [];

        foreach ($guesses as $guess) {
            $guessesByMatch[$guess->sportMatch->id->toRfc4122()] = $guess;
        }

        $rows = [];
        $totalPoints = 0;

        foreach ($matches as $match) {
            $matchKey = $match->id->toRfc4122();
            $guess = $guessesByMatch[$matchKey] ?? null;

            $matchPoints = 0;
            $breakdown = [];
            $myHome = null;
            $myAway = null;

            if (null !== $guess) {
                $myHome = $guess->homeScore;
                $myAway = $guess->awayScore;

                $evaluation = $this->evaluationRepository->findByGuess($guess->id);

                if (null !== $evaluation) {
                    $matchPoints = $evaluation->totalPoints;

                    foreach ($evaluation->rulePoints as $rulePoints) {
                        $breakdown[] = new RulePointsItem(
                            ruleIdentifier: $rulePoints->ruleIdentifier,
                            points: $rulePoints->points,
                        );
                    }
                }
            }

            $totalPoints += $matchPoints;

            $rows[] = new MemberMatchBreakdown(
                sportMatchId: $match->id,
                homeTeam: $match->homeTeam,
                awayTeam: $match->awayTeam,
                kickoffAt: $match->kickoffAt,
                actualHomeScore: $match->homeScore,
                actualAwayScore: $match->awayScore,
                myHomeScore: $myHome,
                myAwayScore: $myAway,
                totalPoints: $matchPoints,
                breakdown: $breakdown,
            );
        }

        return new MemberBreakdownResult(
            userId: $user->id,
            nickname: $user->nickname,
            totalPoints: $totalPoints,
            rows: $rows,
        );
    }
}
