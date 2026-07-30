<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Exception\GuessNotYetOpen;
use App\Repository\GuessRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * „Tipování otevřeno od" — the write-side gate. Every guess-writing handler
 * refuses while a match waits, which is what makes the feature bypass-proof:
 * hiding the inputs is UI, THIS is the rule.
 *
 * MockClock now = 2025-06-15 12:00 UTC. PUBLIC_COMPETITION takes MATCH_SCHEDULED
 * (kickoff 2025-06-20 18:00) as late-added ⇒ its deadline is its own kickoff, so
 * an opening anywhere before that is a legal window.
 */
final class TipOpeningGateTest extends IntegrationTestCase
{
    private const string FUTURE_OPENING = '2025-06-18 09:00:00';
    private const string PAST_OPENING = '2025-06-14 09:00:00';

    public function testSubmitIsRejectedWhileTheMatchWaits(): void
    {
        $this->openTippingAt(self::FUTURE_OPENING);

        try {
            $this->commandBus()->dispatch($this->submitCommand());
            self::fail('Expected GuessNotYetOpen to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotYetOpen::class, $e->getPrevious());
            self::assertStringContainsString('18. 6. 2025 11:00', $e->getPrevious()->getMessage());
        }

        $this->entityManager()->clear();
        self::assertNull($this->existingGuess());
    }

    public function testSubmitWorksOnceTheOpeningHasPassed(): void
    {
        $this->openTippingAt(self::PAST_OPENING);

        $this->commandBus()->dispatch($this->submitCommand());

        $this->entityManager()->clear();
        self::assertNotNull($this->existingGuess());
    }

    public function testAMatchWithoutAnOpeningStaysTippable(): void
    {
        $this->commandBus()->dispatch($this->submitCommand());

        $this->entityManager()->clear();
        self::assertNotNull($this->existingGuess());
    }

    public function testUpdateIsRejectedWhileTheMatchWaits(): void
    {
        $guessId = $this->submitAndGetId();
        $this->openTippingAt(self::FUTURE_OPENING);

        try {
            $this->commandBus()->dispatch(new UpdateGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                guessId: $guessId,
                homeScore: 4,
                awayScore: 4,
            ));
            self::fail('Expected GuessNotYetOpen to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotYetOpen::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        $guess = $this->existingGuess();
        self::assertNotNull($guess);
        self::assertSame(2, $guess->homeScore);
    }

    public function testDeleteIsRejectedWhileTheMatchWaits(): void
    {
        $guessId = $this->submitAndGetId();
        $this->openTippingAt(self::FUTURE_OPENING);

        try {
            $this->commandBus()->dispatch(new DeleteGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                guessId: $guessId,
            ));
            self::fail('Expected GuessNotYetOpen to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotYetOpen::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNotNull($this->existingGuess());
    }

    /**
     * The organizer gets no free pass — an opening blocks on-behalf tipping
     * exactly as it blocks the member's own. (ADMIN owns BOOSTS_COMPETITION and
     * SECOND_VERIFIED_USER is its non-owner member.).
     */
    public function testOnBehalfSubmitIsRejectedWhileTheMatchWaits(): void
    {
        $this->openTippingAt(
            self::FUTURE_OPENING,
            competitionId: AppFixtures::BOOSTS_COMPETITION_ID,
        );

        try {
            $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
                actingUserId: Uuid::fromString(AppFixtures::ADMIN_ID),
                targetUserId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
            self::fail('Expected GuessNotYetOpen to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotYetOpen::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNull($this->guessRepository()->findActiveByUserMatchCompetition(
            Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
        ));
    }

    private function submitCommand(): SubmitGuessCommand
    {
        return new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        );
    }

    private function submitAndGetId(): Uuid
    {
        $envelope = $this->commandBus()->dispatch($this->submitCommand());
        $handled = $envelope->last(HandledStamp::class);
        self::assertNotNull($handled);
        $guess = $handled->getResult();
        self::assertInstanceOf(Guess::class, $guess);

        return $guess->id;
    }

    private function openTippingAt(string $opensAt, string $competitionId = AppFixtures::PUBLIC_COMPETITION_ID): void
    {
        // Written through the real (admin-only) write path, so the gate is
        // exercised end to end rather than from a hand-built entity.
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString($competitionId),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            deadline: null,
            changeOpening: true,
            opensAt: new \DateTimeImmutable($opensAt),
            openingNote: 'Tipování otevřeme po losu.',
        ));

        $this->entityManager()->clear();
    }

    private function existingGuess(): ?Guess
    {
        return $this->guessRepository()->findActiveByUserMatchCompetition(
            Uuid::fromString(AppFixtures::ADMIN_ID),
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        );
    }

    private function guessRepository(): GuessRepository
    {
        /* @var GuessRepository */
        return self::getContainer()->get(GuessRepository::class);
    }
}
