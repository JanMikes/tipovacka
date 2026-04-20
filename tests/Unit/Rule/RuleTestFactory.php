<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use Symfony\Component\Uid\Uuid;

final class RuleTestFactory
{
    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public static function user(): User
    {
        $user = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'u@test.com',
            password: 'hash',
            nickname: 'u',
            createdAt: self::now(),
        );
        $user->popEvents();

        return $user;
    }

    public static function tournament(): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: self::user(),
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: self::now(),
        );
        $tournament->popEvents();

        return $tournament;
    }

    public static function scheduledMatch(): SportMatch
    {
        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            tournament: self::tournament(),
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00'),
            venue: null,
            createdAt: self::now(),
        );
        $match->popEvents();

        return $match;
    }

    public static function finishedMatch(int $home, int $away): SportMatch
    {
        $match = self::scheduledMatch();
        $match->setFinalScore($home, $away, self::now());
        $match->popEvents();

        return $match;
    }

    public static function guess(int $home, int $away): Guess
    {
        $user = self::user();
        $tournament = self::tournament();

        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $user,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: self::now(),
        );
        $group->popEvents();

        $match = self::scheduledMatch();

        $guess = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            group: $group,
            homeScore: $home,
            awayScore: $away,
            submittedAt: self::now(),
        );
        $guess->popEvents();

        return $guess;
    }
}
