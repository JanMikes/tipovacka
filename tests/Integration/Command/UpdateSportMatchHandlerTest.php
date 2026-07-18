<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Exception\SportMatchTeamsLocked;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateSportMatchHandlerTest extends IntegrationTestCase
{
    public function testUpdatesMatchFields(): void
    {
        // MATCH_SCHEDULED has no recorded events — renaming a team is allowed.
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new UpdateSportMatchCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Brno',
            awayTeam: null,
            kickoffAt: null,
            venue: 'Lužánky',
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Brno', $match->homeTeam);
        self::assertSame('Lužánky', $match->venue);
    }

    public function testTeamRenameIsBlockedWhenMatchHasRecordedEvents(): void
    {
        // MATCH_FINISHED ships with three fixture events; renaming a team would
        // split the roster pool (players are keyed by team name).
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        try {
            $this->commandBus()->dispatch(new UpdateSportMatchCommand(
                sportMatchId: $matchId,
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                homeTeam: 'Bohemka',
                awayTeam: null,
                kickoffAt: null,
                venue: null,
            ));
            self::fail('Expected SportMatchTeamsLocked to be thrown.');
        } catch (HandlerFailedException $exception) {
            $wrapped = $this->firstWrappedException($exception);
            self::assertInstanceOf(SportMatchTeamsLocked::class, $wrapped);
            self::assertSame(
                'Název týmu nelze změnit — k zápasu už jsou zapsané události. Nejprve smažte střelce/karty.',
                $wrapped->getMessage(),
            );
        }

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Bohemians 1905', $match->homeTeam);
    }

    public function testNonRenameUpdateIsAllowedWhenMatchHasRecordedEvents(): void
    {
        // Submitting the unchanged team names alongside other edits must pass.
        $matchId = Uuid::fromString(AppFixtures::MATCH_FINISHED_ID);

        $this->commandBus()->dispatch(new UpdateSportMatchCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Bohemians 1905',
            awayTeam: 'Jablonec',
            kickoffAt: null,
            venue: 'Fortuna Arena',
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Fortuna Arena', $match->venue);
        self::assertSame('Bohemians 1905', $match->homeTeam);
    }
}
