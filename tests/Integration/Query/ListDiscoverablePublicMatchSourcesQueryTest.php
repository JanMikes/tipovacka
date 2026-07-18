<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\Command\SoftDeleteMatchSource\SoftDeleteMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Query\ListDiscoverablePublicMatchSources\ListDiscoverablePublicMatchSources;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListDiscoverablePublicMatchSourcesQueryTest extends IntegrationTestCase
{
    public function testListsPublicMatchSourceForNonMember(): void
    {
        // VERIFIED_USER is not a member of any competition in the PUBLIC_SOURCE.
        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_ID, $result[0]->matchSourceId->toRfc4122());
        self::assertSame(AppFixtures::PUBLIC_SOURCE_NAME, $result[0]->name);
        // PUBLIC_COMPETITION (admin) + SUBSET_COMPETITION (second verified user).
        self::assertSame(2, $result[0]->competitionCount);
        self::assertSame(2, $result[0]->memberCount);
    }

    public function testExcludesMatchSourcesWhereUserIsMember(): void
    {
        // Admin owns PUBLIC_COMPETITION in PUBLIC_SOURCE, so it should be excluded.
        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $ids = array_map(static fn ($item) => $item->matchSourceId->toRfc4122(), $result);
        self::assertNotContains(
            AppFixtures::PUBLIC_SOURCE_ID,
            $ids,
            'MatchSources where the user has an active membership must be excluded.',
        );
    }

    public function testExcludesPrivateMatchSources(): void
    {
        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        foreach ($result as $item) {
            self::assertNotSame(AppFixtures::PRIVATE_SOURCE_ID, $item->matchSourceId->toRfc4122());
        }
    }

    public function testExcludesFinishedMatchSources(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: $publicId));

        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }

    public function testExcludesDeletedMatchSources(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);
        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(matchSourceId: $publicId));

        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }

    public function testExcludesMatchSourcesOwnedByUser(): void
    {
        // Admin owns PUBLIC_SOURCE — they should never see it in Discover,
        // even if they had no membership at all.
        $em = $this->entityManager();
        $membership = $em->find(Membership::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_OWNER_MEMBERSHIP_ID));
        self::assertNotNull($membership);
        $membership->leave(new \DateTimeImmutable('2025-06-16 09:00:00 UTC'));
        $em->flush();

        $result = $this->queryBus()->handle(new ListDiscoverablePublicMatchSources(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $ids = array_map(static fn ($item) => $item->matchSourceId->toRfc4122(), $result);
        self::assertNotContains(
            AppFixtures::PUBLIC_SOURCE_ID,
            $ids,
            'MatchSources owned by the user must not appear in Discover.',
        );
    }
}
