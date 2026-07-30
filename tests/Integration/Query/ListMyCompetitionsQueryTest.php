<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyCompetitionsQueryTest extends IntegrationTestCase
{
    public function testOwnerSeesOwnCompetition(): void
    {
        $result = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $result[0]->competitionId->toRfc4122());
        self::assertTrue($result[0]->isOwner);
    }

    public function testNonMemberSeesNothing(): void
    {
        $result = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }

    /**
     * The „Chybí N tipů" badge (item 30, copy shortened by item 36). Only the
     * Nástěnka draws it, so the count is opt-in — /zebricek and the switcher must
     * not pay for it.
     */
    public function testMissingTipCountIsResolvedOnlyWhenTheCallerAsksForIt(): void
    {
        $withoutCounts = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertSame(0, $withoutCounts[0]->missingTipCount);

        // „Kámoši u piva" has exactly one scheduled match, still tippable.
        self::assertSame(1, $this->missingTipsIn(
            AppFixtures::VERIFIED_USER_ID,
            AppFixtures::VERIFIED_COMPETITION_ID,
        ));
    }

    /**
     * Criterion 2 — nothing outstanding means no badge at all, not a „0 zápasů" one.
     */
    public function testTippingTheLastOpenMatchLeavesNothingMissing(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        self::assertSame(0, $this->missingTipsIn(
            AppFixtures::VERIFIED_USER_ID,
            AppFixtures::VERIFIED_COMPETITION_ID,
        ));
    }

    /**
     * Criterion 3 — a match past its deadline is „Netipováno" (B5), a fact rather
     * than a call to action, so it never keeps the badge alive. The premium
     * competition spans all four curated matches; the live and the finished one
     * are never tipped here, and tipping the two that are still open must still
     * take the count to zero.
     */
    public function testMatchesPastTheirDeadlineAreNotMissingTips(): void
    {
        $before = $this->missingTipsIn(
            AppFixtures::SECOND_VERIFIED_USER_ID,
            AppFixtures::PREMIUM_COMPETITION_ID,
        );

        self::assertSame(2, $before);

        foreach ([AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID] as $matchId) {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
                sportMatchId: Uuid::fromString($matchId),
                homeScore: 1,
                awayScore: 0,
            ));
        }

        self::assertSame(0, $this->missingTipsIn(
            AppFixtures::SECOND_VERIFIED_USER_ID,
            AppFixtures::PREMIUM_COMPETITION_ID,
        ));
    }

    private function missingTipsIn(string $userId, string $competitionId): int
    {
        $items = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString($userId),
            withMissingTipCounts: true,
        ));

        foreach ($items as $item) {
            if ($item->competitionId->toRfc4122() === $competitionId) {
                return $item->missingTipCount;
            }
        }

        self::fail(sprintf('Competition %s is not in the viewer\'s list.', $competitionId));
    }
}
