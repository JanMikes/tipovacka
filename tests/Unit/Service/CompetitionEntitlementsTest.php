<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Enum\UserRole;
use App\Repository\BoostPurchaseRepository;
use App\Service\Competition\CompetitionEntitlements;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The entitlement matrix (deadline-INDEPENDENT). See .docs/DOMAIN.md §Monetization
 * and the 2026-07-19 service-split decision.
 */
final class CompetitionEntitlementsTest extends TestCase
{
    private \DateTimeImmutable $now;
    private User $owner;
    private User $member;
    private User $admin;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->owner = $this->makeUser(AppFixtures::ADMIN_ID);
        $this->member = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $this->admin = $this->makeUser(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->admin->changeRole(UserRole::ADMIN, $this->now);
        $this->admin->popEvents();
    }

    // ───────────────────────── none ─────────────────────────

    public function testNoneWithoutHidingEntitlesEveryone(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::None, hideOthers: false);

        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertTrue($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    public function testNoneWithHidingEntitlesNobody(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::None, hideOthers: true);

        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    // ─────────────────────── premium ────────────────────────

    public function testPremiumAllTogglesOffEntitlesNobody(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Premium);

        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    public function testPremiumShowDistributionEntitlesDistributionOnly(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Premium);
        $competition->setPremiumFeatures(showDistribution: true, showOthersTips: false, allowTipChanges: false, tipChangeOffsetMinutes: 60, now: $this->now);

        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
    }

    public function testPremiumShowOthersTipsIsSupersetOfDistribution(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Premium);
        $competition->setPremiumFeatures(showDistribution: false, showOthersTips: true, allowTipChanges: false, tipChangeOffsetMinutes: 60, now: $this->now);

        self::assertTrue($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->member), 'OthersTips implies distribution.');
    }

    public function testPremiumAllowTipChangesEntitlesTipChange(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Premium);
        $competition->setPremiumFeatures(showDistribution: false, showOthersTips: false, allowTipChanges: true, tipChangeOffsetMinutes: 60, now: $this->now);

        self::assertTrue($entitlements->canChangeTips($competition, $this->member));
    }

    // ──────────────────────── boosts ─────────────────────────

    public function testBoostsNoBoostEntitlesNobody(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);

        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    public function testBoostsTipDistributionEntitlesDistributionOnly(): void
    {
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);
        $entitlements = $this->entitlements([$this->boost($competition, BoostType::TipDistribution)]);

        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    public function testBoostsOthersTipsIsSupersetOfDistribution(): void
    {
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);
        $entitlements = $this->entitlements([$this->boost($competition, BoostType::OthersTips)]);

        self::assertTrue($entitlements->isEntitledToOthersTips($competition, $this->member));
        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->member), 'OthersTips implies distribution.');
        self::assertFalse($entitlements->canChangeTips($competition, $this->member));
    }

    public function testBoostsTipChangeEntitlesTipChangeOnly(): void
    {
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);
        $entitlements = $this->entitlements([$this->boost($competition, BoostType::TipChange)]);

        self::assertTrue($entitlements->canChangeTips($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->member));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->member));
    }

    // ─────────────────── manager / admin ────────────────────

    public function testOwnerGetsNoFreeVisibilityAndNoAutoTipChange(): void
    {
        $entitlements = $this->entitlements([]);
        // A boosts competition where the owner bought nothing.
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);

        // The organizer plays too — a free look at everyone's tips would be an
        // in-game advantage over members who paid for the same sight (2026-07-23).
        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->owner));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->owner));
        // Nor the „Měnit tip" window — locking is a universal freeze; only the paid
        // entitlement (premium toggle / boost) opens it.
        self::assertFalse($entitlements->canChangeTips($competition, $this->owner));
    }

    public function testAdminGetsNoFreeVisibilityAndNoAutoTipChange(): void
    {
        $entitlements = $this->entitlements([]);
        $competition = $this->makeCompetition(CompetitionMonetization::Premium);

        // Premium toggles are off ⇒ not even a system admin sees the tips.
        self::assertFalse($entitlements->isEntitledToDistribution($competition, $this->admin));
        self::assertFalse($entitlements->isEntitledToOthersTips($competition, $this->admin));
        self::assertFalse($entitlements->canChangeTips($competition, $this->admin));
    }

    public function testManagerVisibilityKnobRestoresTheOldFreePass(): void
    {
        // The decision is one constructor argument (wired in config/services.php):
        // flipping it back must restore free manager/admin visibility wholesale.
        $entitlements = $this->entitlements([], managersSeeTipsForFree: true);
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);

        self::assertTrue($entitlements->isEntitledToDistribution($competition, $this->owner));
        self::assertTrue($entitlements->isEntitledToOthersTips($competition, $this->owner));
        self::assertTrue($entitlements->isEntitledToOthersTips($competition, $this->admin));
        // Still never the tip-change window — that knob is unrelated.
        self::assertFalse($entitlements->canChangeTips($competition, $this->owner));
    }

    // ───────────── canChangeTips deadline-independence ───────

    public function testCanChangeTipsDoesNotDependOnAnyDeadline(): void
    {
        // The method takes only (competition, user) — no clock, no match, no
        // deadline — so its answer cannot flip at the competition deadline. A
        // tip_change boost owner is entitled no matter what „now" is.
        $competition = $this->makeCompetition(CompetitionMonetization::Boosts);
        $entitlements = $this->entitlements([$this->boost($competition, BoostType::TipChange)]);

        self::assertTrue($entitlements->canChangeTips($competition, $this->member));
    }

    // ──────────────────────── helpers ────────────────────────

    /**
     * @param list<BoostPurchase> $ownedBoosts
     */
    private function entitlements(array $ownedBoosts, bool $managersSeeTipsForFree = false): CompetitionEntitlements
    {
        $repo = $this->createStub(BoostPurchaseRepository::class);
        $repo->method('findActiveByUserAndCompetition')->willReturn($ownedBoosts);

        return new CompetitionEntitlements($repo, $managersSeeTipsForFree);
    }

    private function boost(Competition $competition, BoostType $type): BoostPurchase
    {
        return new BoostPurchase(
            id: Uuid::fromString(AppFixtures::BOOST_PURCHASE_OTHERS_TIPS_ID),
            user: $this->member,
            competition: $competition,
            type: $type,
            pricePaid: $type->price(),
            purchasedAt: $this->now,
        );
    }

    private function makeUser(string $id): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: $id.'@tipovacka.test',
            password: 'hash',
            nickname: 'u'.substr($id, -4),
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeCompetition(CompetitionMonetization $monetization, bool $hideOthers = false): Competition
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $this->owner,
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
            owner: $this->owner,
            name: 'Test',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
            hideOthersTipsBeforeDeadline: $hideOthers,
            monetization: $monetization,
        );
        $competition->popEvents();

        return $competition;
    }
}
