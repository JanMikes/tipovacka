<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Repository\CompetitionRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMemberLeaderboardBreakdownQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private GuessEvaluationRepository $evaluationRepository,
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMemberLeaderboardBreakdown $query): MemberBreakdownResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $user = $this->userRepository->get($query->userId);

        $matchesQb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.state = :finished')
            ->setParameter('finished', SportMatchState::Finished)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');
        $this->matchProvider->applyCompetitionMatchFilter($matchesQb, 'm', $competition);

        /** @var list<SportMatch> $matches */
        $matches = $matchesQb->getQuery()->getResult();

        $guessesQb = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.user = :userId')
            ->andWhere('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.state = :finished')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', $competition->id)
            ->setParameter('finished', SportMatchState::Finished);
        $this->matchProvider->applyCompetitionMatchFilter($guessesQb, 'm', $competition);

        /** @var list<Guess> $guesses */
        $guesses = $guessesQb->getQuery()->getResult();

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
            nickname: $user->displayName,
            totalPoints: $totalPoints,
            rows: $rows,
        );
    }
}
