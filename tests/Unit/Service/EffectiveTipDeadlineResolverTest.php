<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSetting;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceVisibility;
use App\Repository\CompetitionMatchSettingRepository;
use App\Service\EffectiveTipDeadlineResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EffectiveTipDeadlineResolverTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testResolveFallsBackToKickoffWhenNoOverrides(): void
    {
        $competition = $this->makeCompetition(tipsDeadline: null);
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $repo = $this->createStub(CompetitionMatchSettingRepository::class);
        $repo->method('findByCompetitionAndMatch')->willReturn(null);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 18:00'),
            $resolver->resolve($competition, $match),
        );
    }

    public function testResolveUsesCompetitionDefaultWhenNoOverride(): void
    {
        $competition = $this->makeCompetition(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $repo = $this->createStub(CompetitionMatchSettingRepository::class);
        $repo->method('findByCompetitionAndMatch')->willReturn(null);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-19 09:00'),
            $resolver->resolve($competition, $match),
        );
    }

    public function testResolveOverrideWinsOverCompetitionDefault(): void
    {
        $competition = $this->makeCompetition(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $override = new CompetitionMatchSetting(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $match,
            deadline: new \DateTimeImmutable('2025-06-20 17:30'),
            createdAt: $this->now,
        );

        $repo = $this->createStub(CompetitionMatchSettingRepository::class);
        $repo->method('findByCompetitionAndMatch')->willReturn($override);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 17:30'),
            $resolver->resolve($competition, $match),
        );
    }

    public function testResolveManyAppliesPerMatchOverrides(): void
    {
        $competition = $this->makeCompetition(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $matchA = $this->makeMatch(kickoff: '2025-06-20 18:00', id: '01933333-0000-7000-8000-000000000a01');
        $matchB = $this->makeMatch(kickoff: '2025-06-21 18:00', id: '01933333-0000-7000-8000-000000000a02');

        $overrideA = new CompetitionMatchSetting(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $matchA,
            deadline: new \DateTimeImmutable('2025-06-20 17:30'),
            createdAt: $this->now,
        );

        $repo = $this->createStub(CompetitionMatchSettingRepository::class);
        $repo->method('findByCompetitionAndMatches')->willReturn([
            $matchA->id->toRfc4122() => $overrideA,
        ]);

        $resolver = new EffectiveTipDeadlineResolver($repo);
        $deadlines = $resolver->resolveMany($competition, [$matchA, $matchB]);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 17:30'),
            $deadlines[$matchA->id->toRfc4122()],
        );
        self::assertEquals(
            new \DateTimeImmutable('2025-06-19 09:00'),
            $deadlines[$matchB->id->toRfc4122()],
        );
    }

    public function testResolveManyEmptyMatchesReturnsEmptyArray(): void
    {
        $competition = $this->makeCompetition(tipsDeadline: null);
        $repo = $this->createStub(CompetitionMatchSettingRepository::class);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertSame([], $resolver->resolveMany($competition, []));
    }

    private function makeCompetition(?\DateTimeImmutable $tipsDeadline): Competition
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
            createdAt: $this->now,
        );
        $owner->popEvents();

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: MatchSourceVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        if (null !== $tipsDeadline) {
            $competition->updateDetails(
                name: $competition->name,
                description: $competition->description,
                hideOthersTipsBeforeDeadline: $competition->hideOthersTipsBeforeDeadline,
                tipsDeadline: $tipsDeadline,
                now: $this->now,
            );
        }

        return $competition;
    }

    private function makeMatch(string $kickoff, string $id = AppFixtures::MATCH_SCHEDULED_ID): SportMatch
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
            createdAt: $this->now,
        );
        $owner->popEvents();

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: MatchSourceVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $m = new SportMatch(
            id: Uuid::fromString($id),
            matchSource: $matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
            createdAt: $this->now,
        );
        $m->popEvents();

        return $m;
    }
}
