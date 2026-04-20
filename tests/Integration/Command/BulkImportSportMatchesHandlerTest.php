<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\BulkImportSportMatches\BulkImportSportMatchesCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Service\SportMatch\SportMatchImportRow;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class BulkImportSportMatchesHandlerTest extends IntegrationTestCase
{
    public function testImportsMultipleMatches(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        $rows = [
            new SportMatchImportRow(2, 'Liberec', 'Slovácko', new \DateTimeImmutable('2025-09-01 18:00'), 'U Nisy'),
            new SportMatchImportRow(3, 'Karviná', 'Hradec Králové', new \DateTimeImmutable('2025-09-02 20:00'), null),
        ];

        $this->commandBus()->dispatch(new BulkImportSportMatchesCommand(
            tournamentId: $tournamentId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            rows: $rows,
        ));

        $em = $this->entityManager();
        $em->clear();

        $matches = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.homeTeam IN (:homes)')
            ->setParameter('homes', ['Liberec', 'Karviná'])
            ->getQuery()
            ->getResult();

        self::assertCount(2, $matches);
    }
}
