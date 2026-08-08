<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\FeedProvider;
use App\Enum\MatchSourceKind;
use Symfony\Component\Uid\Uuid;

/** A feed-bound curated source, the one argument every MatchDataProvider takes. */
final class FeedSourceFactory
{
    public static function create(
        FeedProvider $provider,
        string $feedRef,
        ?\DateTimeImmutable $startAt = null,
        ?\DateTimeImmutable $endAt = null,
    ): MatchSource {
        $now = new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'));

        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );

        $owner = new User(
            id: Uuid::fromString(AppFixtures::ADMIN_ID),
            email: 'admin@example.com',
            password: null,
            nickname: 'a',
            createdAt: $now,
        );
        $owner->popEvents();

        $source = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Curated,
            name: 'Feed test source',
            description: null,
            startAt: $startAt,
            endAt: $endAt,
            createdAt: $now,
        );
        $source->popEvents();
        $source->bindFeed($provider, $feedRef, $now);
        $source->popEvents();

        return $source;
    }
}
