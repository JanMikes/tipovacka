<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Exception\GuessNotYetOpen;
use App\Repository\GuessRepository;
use App\Service\Competition\TipChangeUnlock;
use App\Service\EffectiveTipDeadlineResolver;
use App\Tests\Support\IntegrationTestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * „Počkejte si na sestavy" buys BOTH ends of the tip window (product owner,
 * 2026-07-30): as well as extending the uzávěrka it LIFTS a „tipování otevřeno
 * od", so the buyer tips a waiting match straight away and keeps tipping to the
 * deadline. That is the promise the paywall on a waiting card makes.
 *
 * MockClock now = 2025-06-15 12:00 UTC; the opening below is in the future.
 */
final class TipChangeBoostOpeningTest extends IntegrationTestCase
{
    private const string OPENS_AT = '2025-06-18 09:00:00';

    public function testBoostOwnerCanTipAWaitingMatchWhileOthersWait(): void
    {
        $this->setOpening();
        $this->buyTipChangeFor(AppFixtures::SECOND_VERIFIED_USER_ID);

        $resolver = self::getContainer()->get(EffectiveTipDeadlineResolver::class);
        $now = $this->now();

        $competition = $this->competition();
        $match = $this->match();

        $buyer = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $plain = $this->user(AppFixtures::ADMIN_ID);

        // The buyer's window has no opening left…
        self::assertNull($resolver->windowFor($competition, $match, $buyer)->opensAt);
        self::assertFalse($resolver->isLocked($competition, $match, $buyer, $now));

        // …everyone else still waits.
        self::assertEquals(
            new \DateTimeImmutable(self::OPENS_AT),
            $resolver->windowFor($competition, $match, $plain)->opensAt,
        );
        self::assertTrue($resolver->isLocked($competition, $match, $plain, $now));
    }

    public function testTheBoostOwnerMayActuallyStoreATipOnAWaitingMatch(): void
    {
        $this->setOpening();
        $this->buyTipChangeFor(AppFixtures::SECOND_VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->entityManager()->clear();

        // The write really went through the gate, not around it.
        self::assertNotNull(self::getContainer()->get(GuessRepository::class)
            ->findActiveByUserMatchCompetition(
                Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            ));
    }

    public function testWithoutTheBoostTheWaitingMatchStillRefusesATip(): void
    {
        $this->setOpening();

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                homeScore: 2,
                awayScore: 1,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessNotYetOpen::class, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * The paywall must appear on a waiting match even when the deadline itself
     * would not move — „cannot tip at all" → „can tip now" is the gain being sold.
     */
    public function testAnOfferExistsForAWaitingMatchWhoseDeadlineWouldNotMove(): void
    {
        // Deadline pinned to the match's own kickoff, so the boost's „kickoff
        // minus offset" term can extend nothing: only the opening is left to buy.
        $this->setOpening(deadline: new \DateTimeImmutable('2025-06-20 18:00:00'));

        $unlock = self::getContainer()->get(TipChangeUnlock::class);

        $offer = $unlock->forMatch(
            $this->competition(),
            $this->match(),
            $this->user(AppFixtures::SECOND_VERIFIED_USER_ID),
            $this->now(),
        );

        self::assertNotNull($offer);
        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00:00'), $offer->deadline);
    }

    private function setOpening(?\DateTimeImmutable $deadline = null): void
    {
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            deadline: $deadline,
            changeOpening: true,
            opensAt: new \DateTimeImmutable(self::OPENS_AT),
            openingNote: 'Otevřeme po losu.',
        ));

        $this->entityManager()->clear();
    }

    private function buyTipChangeFor(string $userId): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: 200,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->commandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::TipChange,
        ));

        $this->entityManager()->clear();
    }

    private function competition(): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertNotNull($competition);

        return $competition;
    }

    private function match(): SportMatch
    {
        $match = $this->entityManager()->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertNotNull($match);

        return $match;
    }

    private function user(string $id): User
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString($id));
        self::assertNotNull($user);

        return $user;
    }

    private function now(): \DateTimeImmutable
    {
        $clock = self::getContainer()->get(ClockInterface::class);

        return \DateTimeImmutable::createFromInterface($clock->now());
    }
}
