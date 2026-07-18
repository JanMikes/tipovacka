<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
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

    public static function matchSource(): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: self::user(),
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: self::now(),
        );
        $matchSource->popEvents();

        return $matchSource;
    }

    public static function competition(): Competition
    {
        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: self::matchSource(),
            owner: self::user(),
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: self::now(),
        );
        $competition->popEvents();

        return $competition;
    }

    public static function scheduledMatch(): SportMatch
    {
        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: self::matchSource(),
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
        $match->setFinalScore($home, $away, null, null, null, self::now());
        $match->popEvents();

        return $match;
    }

    public static function guess(int $home, int $away): Guess
    {
        $user = self::user();
        $matchSource = self::matchSource();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $user,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: self::now(),
        );
        $competition->popEvents();

        $match = self::scheduledMatch();

        $guess = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: $home,
            awayScore: $away,
            submittedAt: self::now(),
        );
        $guess->popEvents();

        return $guess;
    }
}
