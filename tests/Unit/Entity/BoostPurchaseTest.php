<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Event\BoostRefunded;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class BoostPurchaseTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(string $id): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: 'user@tipovacka.test',
            password: 'hash',
            nickname: 'tipovac',
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeBoost(BoostType $type): BoostPurchase
    {
        $owner = $this->makeUser(AppFixtures::ADMIN_ID);
        $buyer = $this->makeUser(AppFixtures::VERIFIED_USER_ID);

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
            id: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Příspěvková',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
            monetization: CompetitionMonetization::Boosts,
        );
        $competition->popEvents();

        return new BoostPurchase(
            id: Uuid::fromString(AppFixtures::BOOST_PURCHASE_OTHERS_TIPS_ID),
            user: $buyer,
            competition: $competition,
            type: $type,
            pricePaid: $type->price(),
            purchasedAt: $this->now,
        );
    }

    public function testFreshPurchaseIsActiveAndRecordsNoEvent(): void
    {
        $boost = $this->makeBoost(BoostType::OthersTips);

        self::assertTrue($boost->isActive);
        self::assertNull($boost->refundedAt);
        self::assertSame(BoostType::OthersTips, $boost->type);
        self::assertSame(20, $boost->pricePaid);
        self::assertCount(0, $boost->popEvents());
    }

    public function testRefundMarksRefundedAndRecordsBoostRefunded(): void
    {
        $boost = $this->makeBoost(BoostType::OthersTips);

        $boost->refund($this->now);

        self::assertFalse($boost->isActive);
        self::assertSame($this->now, $boost->refundedAt);

        $events = $boost->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BoostRefunded::class, $events[0]);
        self::assertSame(AppFixtures::BOOSTS_COMPETITION_ID, $events[0]->competitionId->toRfc4122());
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $events[0]->userId->toRfc4122());
        self::assertSame('others_tips', $events[0]->boostType);
        self::assertSame(20, $events[0]->amount);
    }

    public function testRefundIsIdempotent(): void
    {
        $boost = $this->makeBoost(BoostType::TipChange);

        $boost->refund($this->now);
        $boost->popEvents();

        // Second call is a no-op — moment untouched, no further event.
        $boost->refund($this->now->modify('+1 day'));

        self::assertSame($this->now, $boost->refundedAt);
        self::assertCount(0, $boost->popEvents());
    }
}
