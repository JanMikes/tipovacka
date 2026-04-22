<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\MarkTournamentFinished\MarkTournamentFinishedCommand;
use App\Command\SoftDeleteTournament\SoftDeleteTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Query\ListDiscoverablePublicTournaments\ListDiscoverablePublicTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListDiscoverablePublicTournamentsQueryTest extends IntegrationTestCase
{
    public function testListsPublicTournamentForNonMember(): void
    {
        // VERIFIED_USER is not a member of any group in the PUBLIC_TOURNAMENT.
        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_ID, $result[0]->tournamentId->toRfc4122());
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $result[0]->name);
        self::assertSame(1, $result[0]->groupCount);
        self::assertSame(1, $result[0]->memberCount);
    }

    public function testExcludesTournamentsWhereUserIsMember(): void
    {
        // Admin owns PUBLIC_GROUP in PUBLIC_TOURNAMENT, so it should be excluded.
        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $ids = array_map(static fn ($item) => $item->tournamentId->toRfc4122(), $result);
        self::assertNotContains(
            AppFixtures::PUBLIC_TOURNAMENT_ID,
            $ids,
            'Tournaments where the user has an active membership must be excluded.',
        );
    }

    public function testExcludesPrivateTournaments(): void
    {
        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        foreach ($result as $item) {
            self::assertNotSame(AppFixtures::PRIVATE_TOURNAMENT_ID, $item->tournamentId->toRfc4122());
        }
    }

    public function testExcludesFinishedTournaments(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);
        $this->commandBus()->dispatch(new MarkTournamentFinishedCommand(tournamentId: $publicId));

        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }

    public function testExcludesDeletedTournaments(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);
        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(tournamentId: $publicId));

        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }

    public function testExcludesTournamentsOwnedByUser(): void
    {
        // Admin owns PUBLIC_TOURNAMENT — they should never see it in Discover,
        // even if they had no membership at all.
        $em = $this->entityManager();
        $membership = $em->find(Membership::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_OWNER_MEMBERSHIP_ID));
        self::assertNotNull($membership);
        $membership->leave(new \DateTimeImmutable('2025-06-16 09:00:00 UTC'));
        $em->flush();

        $result = $this->queryBus()->handle(new ListDiscoverablePublicTournaments(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $ids = array_map(static fn ($item) => $item->tournamentId->toRfc4122(), $result);
        self::assertNotContains(
            AppFixtures::PUBLIC_TOURNAMENT_ID,
            $ids,
            'Tournaments owned by the user must not appear in Discover.',
        );
    }
}
