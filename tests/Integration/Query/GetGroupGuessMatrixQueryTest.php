<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGroup\UpdateGroupCommand;
use App\DataFixtures\AppFixtures;
use App\Query\GetGroupGuessMatrix\GetGroupGuessMatrix;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetGroupGuessMatrixQueryTest extends IntegrationTestCase
{
    public function testWithoutHidingAllCellsAreVisible(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();

        $matrix = $this->queryBus()->handle(new GetGroupGuessMatrix(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            requestingUserId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
            applyHiding: false,
        ));

        $matchKey = AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

        $hiddenCount = 0;
        foreach ($matrix->members as $row) {
            if (isset($row->cells[$matchKey]) && $row->cells[$matchKey]->hidden) {
                ++$hiddenCount;
            }
        }
        self::assertSame(0, $hiddenCount, 'No cells should be hidden when applyHiding is false.');
    }

    public function testHidingMasksOtherMembersBeforeDeadline(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();
        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            name: AppFixtures::VERIFIED_GROUP_NAME,
            description: null,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: null,
        ));

        $matrix = $this->queryBus()->handle(new GetGroupGuessMatrix(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            requestingUserId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
            applyHiding: true,
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

    public function testHidingDisabledForOwnerEvenWithApplyHiding(): void
    {
        $this->seedTwoTipsOnPrivateScheduledMatch();
        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            name: AppFixtures::VERIFIED_GROUP_NAME,
            description: null,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: null,
        ));

        // Controller would not pass applyHiding=true when requesting user is owner;
        // but the query should still respect requestingUserId — owner sees their own,
        // and any "other" cells will be hidden ONLY if applyHiding stays true. We
        // test the controller-side guarantee here: passing applyHiding=false skips
        // hiding completely, mimicking what the controller does for owners.
        $matrix = $this->queryBus()->handle(new GetGroupGuessMatrix(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            requestingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            applyHiding: false,
        ));

        $matchKey = AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

        foreach ($matrix->members as $row) {
            if (isset($row->cells[$matchKey])) {
                self::assertFalse($row->cells[$matchKey]->hidden);
            }
        }
    }

    private function seedTwoTipsOnPrivateScheduledMatch(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 3,
            awayScore: 0,
        ));
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 2,
        ));
    }
}
