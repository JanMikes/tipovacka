<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateSportMatchHandlerTest extends IntegrationTestCase
{
    public function testCreatesMatchInScheduledState(): void
    {
        $kickoff = new \DateTimeImmutable('2025-09-10 18:00:00 UTC');

        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Teplice',
            awayTeam: 'Mladá Boleslav',
            kickoffAt: $kickoff,
            venue: 'Stadion Na Stínadlech',
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.homeTeam = :home')
            ->setParameter('home', 'Teplice')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Scheduled, $match->state);
        self::assertEquals($kickoff, $match->kickoffAt);
        self::assertSame('Stadion Na Stínadlech', $match->venue);
    }
}
