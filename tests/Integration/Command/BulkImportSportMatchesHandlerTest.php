<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\BulkImportSportMatches\BulkImportSportMatchesCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Service\SportMatch\SportMatchImportRow;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class BulkImportSportMatchesHandlerTest extends IntegrationTestCase
{
    public function testImportsMultipleMatches(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID);

        $rows = [
            new SportMatchImportRow(2, 'Liberec', 'Slovácko', new \DateTimeImmutable('2025-09-01 18:00'), 'U Nisy'),
            new SportMatchImportRow(3, 'Karviná', 'Hradec Králové', new \DateTimeImmutable('2025-09-02 20:00'), null),
        ];

        $this->commandBus()->dispatch(new BulkImportSportMatchesCommand(
            matchSourceId: $matchSourceId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            rows: $rows,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matches = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->join('m.homeTeam', 't')
            ->where('t.name IN (:homes)')
            ->setParameter('homes', ['Liberec', 'Karviná'])
            ->getQuery()
            ->getResult();

        self::assertCount(2, $matches);
    }

    /**
     * Regression: a team playing several matches in ONE import (a round-robin, where
     * „Artis Brno B" is home in one row and away in another) must resolve to a single
     * Team. Before the batch-dedup fix each appearance queried the DB, missed the
     * team persisted earlier in the same unflushed transaction, and created a fresh
     * row — colliding on the (match_source_id, name) unique index on flush.
     */
    public function testTeamPlayingMultipleMatchesIsCreatedOnce(): void
    {
        // Private source → LOCAL teams → the uniq_teams_local_source_name index that broke in prod.
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $rows = [
            new SportMatchImportRow(2, 'Artis Brno B', 'Kohouti', new \DateTimeImmutable('2025-09-01 18:00'), null),
            new SportMatchImportRow(3, 'Draci', 'Artis Brno B', new \DateTimeImmutable('2025-09-02 20:00'), null),
            new SportMatchImportRow(4, 'Artis Brno B', 'Draci', new \DateTimeImmutable('2025-09-03 20:00'), null),
        ];

        $this->commandBus()->dispatch(new BulkImportSportMatchesCommand(
            matchSourceId: $matchSourceId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            rows: $rows,
        ));

        $em = $this->entityManager();
        $em->clear();

        $artis = $em->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.matchSource = :source')
            ->andWhere('t.name = :name')
            ->setParameter('source', $matchSourceId)
            ->setParameter('name', 'Artis Brno B')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $artis, 'The repeated team must be created exactly once.');
        $artisId = $artis[0]->id;

        // All three imported matches point at that single Team (home in 2, away in 1).
        $matches = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.homeTeam = :artis OR m.awayTeam = :artis')
            ->setParameter('artis', $artisId)
            ->getQuery()
            ->getResult();

        self::assertCount(3, $matches);
    }

    /**
     * Case-insensitive dedup: „Artis Brno B" and „artis brno b" in the same import are
     * one team (mirrors the resolver's LOWER(name) = LOWER(name) lookup).
     */
    public function testTeamNameDedupIsCaseInsensitiveWithinBatch(): void
    {
        $matchSourceId = Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID);

        $rows = [
            new SportMatchImportRow(2, 'Sokol Řečkovice', 'Tatran', new \DateTimeImmutable('2025-09-01 18:00'), null),
            new SportMatchImportRow(3, 'Tatran', 'sokol řečkovice', new \DateTimeImmutable('2025-09-02 20:00'), null),
        ];

        $this->commandBus()->dispatch(new BulkImportSportMatchesCommand(
            matchSourceId: $matchSourceId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            rows: $rows,
        ));

        $em = $this->entityManager();
        $em->clear();

        $sokol = $em->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.matchSource = :source')
            ->andWhere('LOWER(t.name) = :name')
            ->setParameter('source', $matchSourceId)
            ->setParameter('name', 'sokol řečkovice')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $sokol);
    }
}
