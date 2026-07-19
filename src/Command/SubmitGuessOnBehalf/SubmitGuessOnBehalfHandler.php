<?php

declare(strict_types=1);

namespace App\Command\SubmitGuessOnBehalf;

use App\Entity\Guess;
use App\Enum\UserRole;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Guess\GuessScorerWriter;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class SubmitGuessOnBehalfHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionMatchProvider $matchProvider,
        private CompetitionGuessFeatures $guessFeatures,
        private GuessScorerWriter $scorerWriter,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitGuessOnBehalfCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $actingUser = $this->userRepository->get($command->actingUserId);
        $competition = $this->competitionRepository->get($command->competitionId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actingUser->getRoles(), true);

        if (!$isAdmin && !$actingUser->id->equals($competition->owner->id)) {
            throw new AccessDeniedException('Only the competition owner or an admin can tip on behalf of a member.');
        }

        $targetUser = $this->userRepository->get($command->targetUserId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        if (!$this->membershipRepository->hasActiveMembership($targetUser->id, $competition->id)) {
            throw NotAMember::of($competition->id);
        }

        if (!$this->matchProvider->includes($competition, $sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        // Feature toggles = rule enablement: payload parts of disabled features
        // are rejected, never silently dropped.
        $features = $this->guessFeatures->featuresFor($competition->id);

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
        $deadline = $this->deadlineResolver->resolve($competition, $sportMatch);

        if (!$sportMatch->isOpenForGuesses || $now >= $deadline) {
            throw GuessDeadlinePassed::create();
        }

        $existing = $this->guessRepository->findActiveByUserMatchCompetition(
            $targetUser->id,
            $sportMatch->id,
            $competition->id,
        );

        if (null !== $existing) {
            throw GuessAlreadyExists::create();
        }

        $guess = new Guess(
            id: $this->identity->next(),
            user: $targetUser,
            sportMatch: $sportMatch,
            competition: $competition,
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            submittedAt: $now,
            submittedBy: $actingUser,
            periodScores: $command->periodScores,
            overtimeHomeScore: $command->overtimeHomeScore,
            overtimeAwayScore: $command->overtimeAwayScore,
        );

        $this->scorerWriter->replace($guess, $command->scorers, $now);

        $this->guessRepository->save($guess);

        return $guess;
    }
}
