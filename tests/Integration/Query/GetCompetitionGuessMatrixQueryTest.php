<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetition\UpdateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Query\GetCompetitionGuessMatrix\GetCompetitionGuessMatrix;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetCompetitionGuessMatrixQueryTest extends IntegrationTestCase
{
    public function testWithoutHidingAllCellsAreVisible(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();

        $matrix = $this->queryBus()->handle(new GetCompetitionGuessMatrix(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            requestingUserId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
        ));

        $matchKey = AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

        $hiddenCount = 0;
        foreach ($matrix->members as $row) {
            if (isset($row->cells[$matchKey]) && $row->cells[$matchKey]->hidden) {
                ++$hiddenCount;
            }
        }
        self::assertSame(0, $hiddenCount, 'No cells should be hidden when the competition does not hide others'."'".' tips.');
    }

    public function testHidingMasksOtherMembersBeforeDeadline(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();
        $this->commandBus()->dispatch(new UpdateCompetitionCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            name: AppFixtures::VERIFIED_COMPETITION_NAME,
            description: null,
            hideOthersTipsBeforeDeadline: true,
        ));

        $matrix = $this->queryBus()->handle(new GetCompetitionGuessMatrix(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            requestingUserId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
        ));

        $matchKey = AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

        $byUser = [];
        foreach ($matrix->members as $row) {
            if (isset($row->cells[$matchKey])) {
                $byUser[$row->userId->toRfc4122()] = $row->cells[$matchKey];
            }
        }

        self::assertArrayHasKey(AppFixtures::VERIFIED_USER_ID, $byUser, 'Owner should appear in the matrix.');
        self::assertArrayHasKey(AppFixtures::ANONYMOUS_USER_ID, $byUser, 'Requesting member should appear in the matrix.');

        self::assertTrue($byUser[AppFixtures::VERIFIED_USER_ID]->hidden, 'Other member tip is hidden for the requesting member.');
        self::assertNull($byUser[AppFixtures::VERIFIED_USER_ID]->homeScore);
        self::assertNull($byUser[AppFixtures::VERIFIED_USER_ID]->awayScore);

        self::assertFalse($byUser[AppFixtures::ANONYMOUS_USER_ID]->hidden, 'The requesting user always sees their own tip.');
        self::assertSame(2, $byUser[AppFixtures::ANONYMOUS_USER_ID]->homeScore);
    }

    public function testOwnerAlsoHasOthersTipsHiddenBeforeTheDeadline(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();
        $this->commandBus()->dispatch(new UpdateCompetitionCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            name: AppFixtures::VERIFIED_COMPETITION_NAME,
            description: null,
            hideOthersTipsBeforeDeadline: true,
        ));

        // Being the organizer buys no early look at anyone's tips (2026-07-23) —
        // only the viewer's OWN cell stays visible, everybody else's is hidden.
        $matrix = $this->queryBus()->handle(new GetCompetitionGuessMatrix(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            requestingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $matchKey = AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;
        $seenOwnCell = false;
        $seenHiddenCell = false;

        foreach ($matrix->members as $row) {
            if (!isset($row->cells[$matchKey])) {
                continue;
            }

            if (AppFixtures::VERIFIED_USER_ID === $row->userId->toRfc4122()) {
                $seenOwnCell = true;
                self::assertFalse($row->cells[$matchKey]->hidden, 'The owner always sees their own tip.');

                continue;
            }

            $seenHiddenCell = true;
            self::assertTrue($row->cells[$matchKey]->hidden, 'Another member’s tip is hidden from the owner too.');
        }

        self::assertTrue($seenOwnCell, 'The owner’s own cell should be in the matrix.');
        self::assertTrue($seenHiddenCell, 'Another member’s cell should be in the matrix.');
    }

    public function testFullNameSubtitleBranches(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);
        $em->flush();

        $matrix = $this->queryBus()->handle(new GetCompetitionGuessMatrix(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            requestingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $byUser = [];
        foreach ($matrix->members as $row) {
            $byUser[$row->userId->toRfc4122()] = $row;
        }

        self::assertArrayHasKey(AppFixtures::VERIFIED_USER_ID, $byUser);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $byUser[AppFixtures::VERIFIED_USER_ID]->nickname);
        self::assertSame('Jan Tipař', $byUser[AppFixtures::VERIFIED_USER_ID]->fullName);

        self::assertArrayHasKey(AppFixtures::ANONYMOUS_USER_ID, $byUser);
        self::assertSame(
            AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME,
            $byUser[AppFixtures::ANONYMOUS_USER_ID]->nickname,
        );
        self::assertNull($byUser[AppFixtures::ANONYMOUS_USER_ID]->fullName);
    }

    private function seedTwoTipsOnPrivateScheduledMatch(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 3,
            awayScore: 0,
        ));
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 2,
        ));
    }
}
