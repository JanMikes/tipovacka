<?php

declare(strict_types=1);

namespace App\Command\SubmitGuess;

use App\Entity\Guess;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SubmitGuessHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitGuessCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $user = $this->userRepository->get($command->userId);
        $competition = $this->competitionRepository->get($command->competitionId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        if (!$this->membershipRepository->hasActiveMembership($user->id, $competition->id)) {
            throw NotAMember::of($competition->id);
        }

        if (!$this->matchProvider->includes($competition, $sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadline = $this->deadlineResolver->resolve($competition, $sportMatch);

        if (!$sportMatch->isOpenForGuesses || $now >= $deadline) {
            throw GuessDeadlinePassed::create();
        }

        $existing = $this->guessRepository->findActiveByUserMatchCompetition(
            $user->id,
            $sportMatch->id,
            $competition->id,
        );

        if (null !== $existing) {
            throw GuessAlreadyExists::create();
        }

        $guess = new Guess(
            id: $this->identity->next(),
            user: $user,
            sportMatch: $sportMatch,
            competition: $competition,
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            submittedAt: $now,
        );

        $this->guessRepository->save($guess);

        return $guess;
    }
}
