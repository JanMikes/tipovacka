<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\GroupMatchSetting;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Repository\GroupMatchSettingRepository;
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
        $group = $this->makeGroup(tipsDeadline: null);
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $repo = $this->createStub(GroupMatchSettingRepository::class);
        $repo->method('findByGroupAndMatch')->willReturn(null);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 18:00'),
            $resolver->resolve($group, $match),
        );
    }

    public function testResolveUsesGroupDefaultWhenNoOverride(): void
    {
        $group = $this->makeGroup(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $repo = $this->createStub(GroupMatchSettingRepository::class);
        $repo->method('findByGroupAndMatch')->willReturn(null);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-19 09:00'),
            $resolver->resolve($group, $match),
        );
    }

    public function testResolveOverrideWinsOverGroupDefault(): void
    {
        $group = $this->makeGroup(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $match = $this->makeMatch(kickoff: '2025-06-20 18:00');

        $override = new GroupMatchSetting(
            id: Uuid::v7(),
            group: $group,
            sportMatch: $match,
            deadline: new \DateTimeImmutable('2025-06-20 17:30'),
            createdAt: $this->now,
        );

        $repo = $this->createStub(GroupMatchSettingRepository::class);
        $repo->method('findByGroupAndMatch')->willReturn($override);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 17:30'),
            $resolver->resolve($group, $match),
        );
    }

    public function testResolveManyAppliesPerMatchOverrides(): void
    {
        $group = $this->makeGroup(tipsDeadline: new \DateTimeImmutable('2025-06-19 09:00'));
        $matchA = $this->makeMatch(kickoff: '2025-06-20 18:00', id: '01933333-0000-7000-8000-000000000a01');
        $matchB = $this->makeMatch(kickoff: '2025-06-21 18:00', id: '01933333-0000-7000-8000-000000000a02');

        $overrideA = new GroupMatchSetting(
            id: Uuid::v7(),
            group: $group,
            sportMatch: $matchA,
            deadline: new \DateTimeImmutable('2025-06-20 17:30'),
            createdAt: $this->now,
        );

        $repo = $this->createStub(GroupMatchSettingRepository::class);
        $repo->method('findByGroupAndMatches')->willReturn([
            $matchA->id->toRfc4122() => $overrideA,
        ]);

        $resolver = new EffectiveTipDeadlineResolver($repo);
        $deadlines = $resolver->resolveMany($group, [$matchA, $matchB]);

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
        $group = $this->makeGroup(tipsDeadline: null);
        $repo = $this->createStub(GroupMatchSettingRepository::class);

        $resolver = new EffectiveTipDeadlineResolver($repo);

        self::assertSame([], $resolver->resolveMany($group, []));
    }

    private function makeGroup(?\DateTimeImmutable $tipsDeadline): Group
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
            createdAt: $this->now,
        );
        $owner->popEvents();

        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $group->popEvents();

        if (null !== $tipsDeadline) {
            $group->updateDetails(
                name: $group->name,
                description: $group->description,
                hideOthersTipsBeforeDeadline: $group->hideOthersTipsBeforeDeadline,
                tipsDeadline: $tipsDeadline,
                now: $this->now,
            );
        }

        return $group;
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

        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        $m = new SportMatch(
            id: Uuid::fromString($id),
            tournament: $tournament,
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
