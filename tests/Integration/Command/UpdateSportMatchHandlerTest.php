<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\MatchSide;
use App\Exception\SportMatchTeamsLocked;
use App\Tests\Support\IntegrationTestCase;
use App\Value\GuessScorerInput;
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
        self::assertSame('Brno', $match->homeTeam->name);
        self::assertSame('Lužánky', $match->venue);
    }

    public function testTeamReassignIsBlockedWhenMatchHasRecordedEvents(): void
    {
        // MATCH_FINISHED ships with three fixture events whose players belong to
        // the current teams; reassigning the match to a DIFFERENT team ('Bohemka'
        // resolves to a brand-new team) would orphan those rows.
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
            self::assertStringStartsWith('Tým zápasu nelze změnit', $wrapped->getMessage());
        }

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Bohemians 1905', $match->homeTeam->name);
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
        self::assertSame('Bohemians 1905', $match->homeTeam->name);
    }

    public function testTeamReassignIsBlockedWhenMatchHasScorerTips(): void
    {
        // MATCH_SCHEDULED has no recorded events, but a standing scorer TIP also
        // locks the teams — GuessScorer rows point at roster players of the
        // current teams, so reassigning to a different team would detach them.
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: $matchId,
            homeScore: 2,
            awayScore: 1,
            scorers: [new GuessScorerInput(MatchSide::Home, 'Střelec Zamykací')],
        ));

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
            self::assertInstanceOf(SportMatchTeamsLocked::class, $this->firstWrappedException($exception));
        }

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Sparta Praha', $match->homeTeam->name);
    }
}
