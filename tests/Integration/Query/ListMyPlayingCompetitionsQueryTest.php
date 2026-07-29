<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Query\ListMyPlayingCompetitions\ListMyPlayingCompetitions;
use App\Query\ListMyPlayingCompetitions\PlayingCompetitionItem;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „Soutěže, kde tipuješ" — the standing plus the one next action per competition.
 */
final class ListMyPlayingCompetitionsQueryTest extends IntegrationTestCase
{
    public function testListsOnlyCompetitionsTheViewerIsAMemberOf(): void
    {
        $items = $this->queryBus()->handle(new ListMyPlayingCompetitions(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertSame([AppFixtures::VERIFIED_COMPETITION_NAME], array_map(
            static fn (PlayingCompetitionItem $item): string => $item->name,
            $items,
        ));
        self::assertTrue($items[0]->viewerIsOwner);
        self::assertSame(2, $items[0]->memberCount);
        self::assertFalse($items[0]->isFinished);
    }

    public function testSomeoneInNothingGetsAnEmptyList(): void
    {
        $items = $this->queryBus()->handle(new ListMyPlayingCompetitions(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $items);
    }

    public function testAnOpenUntippedMatchBecomesTheNextActionAndDisappearsOnceTipped(): void
    {
        $before = $this->onlyItem(AppFixtures::VERIFIED_USER_ID);

        // „Kámoši u piva" has exactly one scheduled match, still tippable.
        self::assertSame(1, $before->pendingTipCount);
        self::assertNotNull($before->nextDeadlineAt);
        self::assertNotNull($before->nextKickoffAt);

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $after = $this->onlyItem(AppFixtures::VERIFIED_USER_ID);

        self::assertSame(0, $after->pendingTipCount);
        self::assertNull($after->nextDeadlineAt);
        // The kickoff is still ahead — only the „něco k tipnutí" prompt is gone.
        self::assertNotNull($after->nextKickoffAt);
    }

    public function testRankAndPointsComeFromTheCompetitionsOwnMatchScope(): void
    {
        // SECOND_VERIFIED_USER is the sole member of the subset competition.
        $items = $this->queryBus()->handle(new ListMyPlayingCompetitions(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
        ));

        $subset = null;

        foreach ($items as $item) {
            if (AppFixtures::SUBSET_COMPETITION_NAME === $item->name) {
                $subset = $item;
            }
        }

        self::assertNotNull($subset);
        self::assertSame(1, $subset->rank);
        self::assertSame(1, $subset->memberCount);
        self::assertSame(0, $subset->totalPoints);
    }

    private function onlyItem(string $userId): PlayingCompetitionItem
    {
        $items = $this->queryBus()->handle(new ListMyPlayingCompetitions(userId: Uuid::fromString($userId)));
        self::assertCount(1, $items);

        return $items[0];
    }
}
