<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessScorer;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Service\Scoring\MatchContext;
use App\Value\PeriodScores;
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

    /**
     * Finished match with full result detail (football sport: exactly 2 periods
     * summing to the final score; OT only on a regular-time draw).
     *
     * @param list<array{int, int}>|null $periods
     */
    public static function finishedMatchWithDetails(
        int $home,
        int $away,
        ?array $periods = null,
        ?int $overtimeHome = null,
        ?int $overtimeAway = null,
    ): SportMatch {
        $match = self::scheduledMatch();
        $match->setFinalScore(
            $home,
            $away,
            null === $periods ? null : PeriodScores::fromArray($periods),
            $overtimeHome,
            $overtimeAway,
            self::now(),
        );
        $match->popEvents();

        return $match;
    }

    public static function guess(int $home, int $away): Guess
    {
        return self::guessWithDetails($home, $away);
    }

    /**
     * @param list<array{int, int}>|null $periods
     */
    public static function guessWithDetails(
        int $home,
        int $away,
        ?array $periods = null,
        ?int $overtimeHome = null,
        ?int $overtimeAway = null,
    ): Guess {
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
            periodScores: null === $periods ? null : PeriodScores::fromArray($periods),
            overtimeHomeScore: $overtimeHome,
            overtimeAwayScore: $overtimeAway,
        );
        $guess->popEvents();

        return $guess;
    }

    public static function player(string $name, ?string $teamName = null): Player
    {
        return new Player(
            id: Uuid::v7(),
            matchSource: self::matchSource(),
            teamName: $teamName ?? 'A',
            name: $name,
            createdAt: self::now(),
        );
    }

    /**
     * Attaches scorer tips to a guess (one row per player).
     *
     * @param list<Player> $players
     */
    public static function withScorerTips(Guess $guess, array $players, MatchSide $side = MatchSide::Home): Guess
    {
        foreach ($players as $player) {
            $guess->addScorer(new GuessScorer(
                id: Uuid::v7(),
                guess: $guess,
                player: $player,
                side: $side,
                createdAt: self::now(),
            ));
        }

        return $guess;
    }

    /**
     * @param list<Player> $goalScorers
     */
    public static function contextWithGoals(array $goalScorers): MatchContext
    {
        return new MatchContext(
            goalScorerPlayerIds: array_map(static fn (Player $player) => $player->id, $goalScorers),
        );
    }
}
