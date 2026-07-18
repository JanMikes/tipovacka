<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionMatchSelectionEntityTest extends TestCase
{
    public function testStoresCompetitionMatchAndAddedAt(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $owner->popEvents();

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Zdroj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $matchSource->popEvents();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: 'token-x',
            createdAt: $now,
        );
        $competition->popEvents();

        $sportMatch = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: null,
            createdAt: $now,
        );
        $sportMatch->popEvents();

        $selectionId = Uuid::fromString(AppFixtures::SUBSET_SELECTION_SCHEDULED_ID);
        $selection = new CompetitionMatchSelection(
            id: $selectionId,
            competition: $competition,
            sportMatch: $sportMatch,
            addedAt: $now,
        );

        self::assertSame($selectionId, $selection->id);
        self::assertSame($competition, $selection->competition);
        self::assertSame($sportMatch, $selection->sportMatch);
        self::assertSame($now, $selection->addedAt);
    }
}
