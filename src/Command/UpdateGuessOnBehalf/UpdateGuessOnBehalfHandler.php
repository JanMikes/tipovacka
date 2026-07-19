<?php

declare(strict_types=1);

namespace App\Command\UpdateGuessOnBehalf;

use App\Entity\Guess;
use App\Enum\UserRole;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Repository\GuessRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Guess\GuessScorerWriter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class UpdateGuessOnBehalfHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private CompetitionGuessFeatures $guessFeatures,
        private GuessScorerWriter $scorerWriter,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateGuessOnBehalfCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $guess = $this->guessRepository->get($command->guessId);
        $actingUser = $this->userRepository->get($command->actingUserId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actingUser->getRoles(), true);

        if (!$isAdmin && !$actingUser->id->equals($guess->competition->owner->id)) {
            throw new AccessDeniedException('Only the competition owner or an admin can edit a member\'s guess.');
        }

        if (null !== $guess->deletedAt) {
            throw GuessNotFound::withId($command->guessId);
        }

        if (!$this->matchProvider->includes($guess->competition, $guess->sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        // Feature toggles = rule enablement: payload parts of disabled features
        // are rejected, never silently dropped.
        $features = $this->guessFeatures->featuresFor($guess->competition->id);

        if (null !== $command->periodScores && !$features->periodTips) {
            throw GuessFeatureNotEnabled::periods();
        }

        if ((null !== $command->overtimeHomeScore || null !== $command->overtimeAwayScore) && !$features->overtimeTip) {
            throw GuessFeatureNotEnabled::overtime();
        }

        if ([] !== $command->scorers && !$features->scorerTips) {
            throw GuessFeatureNotEnabled::scorers();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadline = $this->deadlineResolver->resolve($guess->competition, $guess->sportMatch);

        if (!$guess->sportMatch->isOpenForGuesses || $now >= $deadline) {
            throw GuessDeadlinePassed::create();
        }

        // Full replace: every tip part becomes exactly what the command carries.
        $guess->updateScores(
            $command->homeScore,
            $command->awayScore,
            $now,
            $command->periodScores,
            $command->overtimeHomeScore,
            $command->overtimeAwayScore,
        );

        $this->scorerWriter->replace($guess, $command->scorers, $now);

        return $guess;
    }
}
