<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\LockCompetitionTips\LockCompetitionTipsCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Service\EffectiveTipDeadlineResolver;
use App\Tests\Support\IntegrationTestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The `tip_change` boost, through the REAL CompetitionEntitlements, extends the
 * resolver's tip deadline (extend-only max composition). A member without it is
 * locked at competition start; the boost owner keeps the change window until the
 * offset before the day's first match. See .docs/DOMAIN.md §Tip locking.
 */
final class TipChangeBoostDeadlineTest extends IntegrationTestCase
{
    public function testTipChangeBoostOwnerKeepsChangeWindowWhileOthersAreLocked(): void
    {
        // Manually lock BOOSTS_COMPETITION now (2025-06-15 12:00). MATCH_SCHEDULED
        // (created at the same instant as the competition) is then NOT late-added,
        // so its deadline collapses to the lock moment (= now) for a plain member.
        $this->commandBus()->dispatch(new LockCompetitionTipsCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
        ));

        // SECOND_VERIFIED_USER buys the tip_change boost; VERIFIED_USER (fixture
        // OthersTips only) does not — so only SECOND gets the extended window.
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            amount: 100,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->commandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::TipChange,
        ));

        $this->entityManager()->clear();

        $resolver = self::getContainer()->get(EffectiveTipDeadlineResolver::class);
        $now = \DateTimeImmutable::createFromInterface(self::getContainer()->get(ClockInterface::class)->now());

        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        $match = $this->entityManager()->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        $withBoost = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        $withoutBoost = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertInstanceOf(User::class, $withBoost);
        self::assertInstanceOf(User::class, $withoutBoost);

        // Plain member: locked at competition start (the lock moment = now).
        self::assertTrue($resolver->isLocked($competition, $match, $withoutBoost, $now));

        // tip_change boost owner: still open (window extends to the offset before
        // the day's first match, well after now).
        self::assertFalse($resolver->isLocked($competition, $match, $withBoost, $now));

        // And the extended deadline is strictly later than the plain deadline.
        self::assertGreaterThan(
            $resolver->deadlineFor($competition, $match, $withoutBoost),
            $resolver->deadlineFor($competition, $match, $withBoost),
        );
    }
}
