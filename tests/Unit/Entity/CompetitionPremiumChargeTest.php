<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Enum\PremiumChargeStatus;
use App\Event\PremiumBalanceLow;
use App\Event\PremiumChargeUncovered;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionPremiumChargeTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(string $id, string $email, string $nickname): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: $email,
            password: 'hash',
            nickname: $nickname,
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeCompetition(User $owner): Competition
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Curated,
            name: 'Zdroj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Prémiová',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
            monetization: CompetitionMonetization::Premium,
        );
        $competition->popEvents();

        return $competition;
    }

    private function makeCharge(): CompetitionPremiumCharge
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID, AppFixtures::ADMIN_EMAIL, AppFixtures::ADMIN_NICKNAME);
        $member = $this->makeUser(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::SECOND_VERIFIED_USER_EMAIL, AppFixtures::SECOND_VERIFIED_USER_NICKNAME);

        return new CompetitionPremiumCharge(
            id: Uuid::fromString(AppFixtures::PREMIUM_CHARGE_ID),
            competition: $this->makeCompetition($owner),
            member: $member,
            amount: 10,
            createdAt: $this->now,
        );
    }

    public function testFreshChargeIsUncovered(): void
    {
        $charge = $this->makeCharge();

        self::assertSame(PremiumChargeStatus::Uncovered, $charge->status);
        self::assertTrue($charge->isUncovered);
        self::assertFalse($charge->isCharged);
        self::assertSame(10, $charge->amount);
        self::assertNull($charge->chargedAt);
        self::assertNull($charge->refundedAt);
        self::assertCount(0, $charge->popEvents());
    }

    public function testMarkChargedFlipsStateWithoutEvent(): void
    {
        $charge = $this->makeCharge();

        $charge->markCharged($this->now);

        self::assertSame(PremiumChargeStatus::Charged, $charge->status);
        self::assertTrue($charge->isCharged);
        self::assertFalse($charge->isUncovered);
        self::assertSame($this->now, $charge->chargedAt);
        self::assertNull($charge->refundedAt);
        self::assertCount(0, $charge->popEvents());
    }

    public function testMarkUncoveredRecordsEvent(): void
    {
        $charge = $this->makeCharge();
        $charge->markCharged($this->now);
        $charge->popEvents();

        $charge->markUncovered($this->now);

        self::assertSame(PremiumChargeStatus::Uncovered, $charge->status);
        self::assertNull($charge->chargedAt);

        $events = $charge->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PremiumChargeUncovered::class, $events[0]);
        self::assertSame($charge->competition->id, $events[0]->competitionId);
        self::assertSame($charge->competition->owner->id, $events[0]->ownerId);
        self::assertSame($charge->member->id, $events[0]->memberId);
        self::assertSame(AppFixtures::PREMIUM_COMPETITION_ID, $events[0]->competitionId->toRfc4122());
        self::assertSame(AppFixtures::ADMIN_ID, $events[0]->ownerId->toRfc4122());
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $events[0]->memberId->toRfc4122());
        self::assertSame(10, $events[0]->amount);
    }

    public function testMarkRefundedIsIdempotent(): void
    {
        $charge = $this->makeCharge();
        $charge->markCharged($this->now);
        $charge->popEvents();

        $charge->markRefunded($this->now);
        self::assertSame(PremiumChargeStatus::Refunded, $charge->status);
        self::assertSame($this->now, $charge->refundedAt);

        // Second call is a no-op — the refund moment is not overwritten.
        $charge->markRefunded($this->now->modify('+1 day'));
        self::assertSame($this->now, $charge->refundedAt);
    }

    public function testReactivateResetsToUncoveredAtNewPrice(): void
    {
        $charge = $this->makeCharge();
        $charge->markCharged($this->now);
        $charge->markRefunded($this->now);
        $charge->popEvents();

        $charge->reactivate(10, $this->now->modify('+1 day'));

        self::assertSame(PremiumChargeStatus::Uncovered, $charge->status);
        self::assertNull($charge->chargedAt);
        self::assertNull($charge->refundedAt);
        self::assertCount(0, $charge->popEvents());
    }

    public function testFlagOwnerBalanceLowRecordsEvent(): void
    {
        $charge = $this->makeCharge();

        $charge->flagOwnerBalanceLow(7, $this->now);

        $events = $charge->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PremiumBalanceLow::class, $events[0]);
        self::assertSame($charge->competition->id, $events[0]->competitionId);
        self::assertSame($charge->competition->owner->id, $events[0]->ownerId);
        self::assertSame(AppFixtures::ADMIN_ID, $events[0]->ownerId->toRfc4122());
        self::assertSame(7, $events[0]->balance);
    }
}
