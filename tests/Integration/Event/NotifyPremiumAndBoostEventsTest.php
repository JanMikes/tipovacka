<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Enum\BoostType;
use App\Enum\NotificationType;
use App\Event\BoostRefunded;
use App\Event\MemberJoinedCompetition;
use App\Event\PremiumBalanceLow;
use App\Event\PremiumChargeUncovered;
use App\Event\PremiumConfirmed;
use App\Event\PremiumDowngraded;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class NotifyPremiumAndBoostEventsTest extends IntegrationTestCase
{
    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock()->now());
    }

    public function testPremiumChargeUncoveredNotifiesOwner(): void
    {
        $this->eventBus()->dispatch(new PremiumChargeUncovered(
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            memberId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            amount: 10,
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::PremiumChargeUncovered));
    }

    public function testPremiumBalanceLowNotifiesOwner(): void
    {
        $this->eventBus()->dispatch(new PremiumBalanceLow(
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            balance: 30,
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::PremiumBalanceLow));
    }

    public function testPremiumDowngradedNotifiesOwner(): void
    {
        $this->eventBus()->dispatch(new PremiumDowngraded(
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::PremiumDowngraded));
    }

    public function testPremiumConfirmedNotifiesOwner(): void
    {
        $this->eventBus()->dispatch(new PremiumConfirmed(
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::PremiumEnabled));
    }

    public function testBoostRefundedNotifiesBuyer(): void
    {
        $this->eventBus()->dispatch(new BoostRefunded(
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            boostType: BoostType::OthersTips->value,
            amount: 20,
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::SECOND_VERIFIED_USER_ID, NotificationType::BoostRefunded));
        // The owner (admin) is not the buyer and is not notified.
        self::assertSame(0, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::BoostRefunded));
    }

    public function testMemberJoinedNotifiesOwnerNotSelf(): void
    {
        // A non-owner joins PUBLIC_COMPETITION (owner = admin) → owner notified.
        $this->eventBus()->dispatch(new MemberJoinedCompetition(
            membershipId: Uuid::v7(),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::MemberJoined));

        // The owner joining their own competition is not a "someone joined" event.
        $this->eventBus()->dispatch(new MemberJoinedCompetition(
            membershipId: Uuid::v7(),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            occurredOn: $this->now(),
        ));

        self::assertSame(1, $this->countByUserAndType(AppFixtures::ADMIN_ID, NotificationType::MemberJoined));
    }

    private function countByUserAndType(string $userId, NotificationType $type): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
