<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Decision matrix of CompetitionMatchProvider::includes() —
 * (All × Subset) × includePlayoff × (regular / playoff / deleted / foreign matches).
 * The QueryBuilder-composition methods are covered by integration tests.
 */
final class CompetitionMatchProviderTest extends TestCase
{
    private \DateTimeImmutable $now;
    private User $owner;
    private MatchSource $source;
    private MatchSource $otherSource;
    private SportMatch $regularMatch;
    private SportMatch $playoffMatch;
    private SportMatch $deletedMatch;
    private SportMatch $foreignMatch;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $this->owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $this->owner->popEvents();

        $this->source = $this->makeSource(AppFixtures::PUBLIC_SOURCE_ID);
        $this->otherSource = $this->makeSource(AppFixtures::PRIVATE_SOURCE_ID);

        $this->regularMatch = $this->makeMatch('019ddddd-0000-7000-8000-00000000f001', $this->source, isPlayoff: false);
        $this->playoffMatch = $this->makeMatch('019ddddd-0000-7000-8000-00000000f002', $this->source, isPlayoff: true);
        $this->deletedMatch = $this->makeMatch('019ddddd-0000-7000-8000-00000000f003', $this->source, isPlayoff: false);
        $this->deletedMatch->softDelete($this->now);
        $this->deletedMatch->popEvents();
        $this->foreignMatch = $this->makeMatch('019ddddd-0000-7000-8000-00000000f004', $this->otherSource, isPlayoff: false);
    }

    public function testAllModeWithPlayoffIncludesEverythingExceptDeletedAndForeign(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::All, includePlayoff: true);
        $provider = $this->makeProvider([]);

        self::assertTrue($provider->includes($competition, $this->regularMatch));
        self::assertTrue($provider->includes($competition, $this->playoffMatch));
        self::assertFalse($provider->includes($competition, $this->deletedMatch));
        self::assertFalse($provider->includes($competition, $this->foreignMatch));
    }

    public function testAllModeWithoutPlayoffExcludesPlayoffMatches(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::All, includePlayoff: false);
        $provider = $this->makeProvider([]);

        self::assertTrue($provider->includes($competition, $this->regularMatch));
        self::assertFalse($provider->includes($competition, $this->playoffMatch));
        self::assertFalse($provider->includes($competition, $this->deletedMatch));
        self::assertFalse($provider->includes($competition, $this->foreignMatch));
    }

    public function testSubsetModeIncludesOnlySelectedMatches(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: true);
        $provider = $this->makeProvider([$this->regularMatch->id->toRfc4122()]);

        self::assertTrue($provider->includes($competition, $this->regularMatch));
        self::assertFalse($provider->includes($competition, $this->playoffMatch));
        self::assertFalse($provider->includes($competition, $this->foreignMatch));
    }

    public function testSubsetSelectionWinsOverIncludePlayoff(): void
    {
        // An explicitly selected playoff match counts even with includePlayoff = false.
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: false);
        $provider = $this->makeProvider([$this->playoffMatch->id->toRfc4122()]);

        self::assertTrue($provider->includes($competition, $this->playoffMatch));
        self::assertFalse($provider->includes($competition, $this->regularMatch));
    }

    public function testSubsetModeNeverIncludesDeletedMatchesEvenWhenSelected(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: true);
        $provider = $this->makeProvider([$this->deletedMatch->id->toRfc4122()]);

        self::assertFalse($provider->includes($competition, $this->deletedMatch));
    }

    public function testSubsetModeWithEmptySelectionIncludesNothing(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: true);
        $provider = $this->makeProvider([]);

        self::assertFalse($provider->includes($competition, $this->regularMatch));
        self::assertFalse($provider->includes($competition, $this->playoffMatch));
    }

    public function testSelectionIsLoadedOnceAndCachedPerCompetition(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: true);

        $selectionRepository = $this->createMock(CompetitionMatchSelectionRepository::class);
        $selectionRepository->expects(self::once())
            ->method('selectedMatchIds')
            ->with($competition->id)
            ->willReturn([$this->regularMatch->id->toRfc4122()]);

        $provider = new CompetitionMatchProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CompetitionRepository::class),
            $selectionRepository,
        );

        self::assertTrue($provider->includes($competition, $this->regularMatch));
        self::assertFalse($provider->includes($competition, $this->playoffMatch));
        self::assertTrue($provider->includes($competition, $this->regularMatch));
    }

    public function testForgetSelectionsBustsTheCache(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, includePlayoff: true);

        $selectionRepository = $this->createMock(CompetitionMatchSelectionRepository::class);
        $selectionRepository->expects(self::exactly(2))
            ->method('selectedMatchIds')
            ->with($competition->id)
            ->willReturnOnConsecutiveCalls([], [$this->regularMatch->id->toRfc4122()]);

        $provider = new CompetitionMatchProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CompetitionRepository::class),
            $selectionRepository,
        );

        self::assertFalse($provider->includes($competition, $this->regularMatch));
        $provider->forgetSelections($competition->id);
        self::assertTrue($provider->includes($competition, $this->regularMatch));
    }

    private function makeSource(string $id): MatchSource
    {
        $source = new MatchSource(
            id: Uuid::fromString($id),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $this->owner,
            kind: MatchSourceKind::Curated,
            name: 'Zdroj '.$id,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $source->popEvents();

        return $source;
    }

    private function makeMatch(string $id, MatchSource $source, bool $isPlayoff): SportMatch
    {
        $match = new SportMatch(
            id: Uuid::fromString($id),
            matchSource: $source,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: null,
            createdAt: $this->now,
            isPlayoff: $isPlayoff,
        );
        $match->popEvents();

        return $match;
    }

    private function makeCompetition(CompetitionMatchSelectionMode $mode, bool $includePlayoff): Competition
    {
        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            matchSource: $this->source,
            owner: $this->owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: 'token-x',
            createdAt: $this->now,
            selectionMode: $mode,
            includePlayoff: $includePlayoff,
        );
        $competition->popEvents();

        return $competition;
    }

    /**
     * @param list<string> $selectedMatchIds
     */
    private function makeProvider(array $selectedMatchIds): CompetitionMatchProvider
    {
        $selectionRepository = $this->createStub(CompetitionMatchSelectionRepository::class);
        $selectionRepository->method('selectedMatchIds')->willReturn($selectedMatchIds);

        return new CompetitionMatchProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CompetitionRepository::class),
            $selectionRepository,
        );
    }
}
