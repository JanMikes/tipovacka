<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Query\GetCompetitionsPageStats\GetCompetitionsPageStats;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The hero of `/souteze` must never print a decorative number — every figure it
 * shows (and every sub-label under it) is measured here.
 */
final class GetCompetitionsPageStatsQueryTest extends IntegrationTestCase
{
    public function testAnonymousHeroDescribesThePublicList(): void
    {
        $stats = $this->queryBus()->handle(new GetCompetitionsPageStats());

        // Both global competitions, over the one curated source, ADMIN their sole member.
        self::assertSame(2, $stats->activeCompetitionCount);
        self::assertSame(1, $stats->playerCount);
        self::assertSame(1, $stats->matchSourceCount);
        // Scheduled + finished + live + playoff of the curated source; a match shared
        // by both competitions counts ONCE.
        self::assertSame(4, $stats->matchCount);
        self::assertSame(2, $stats->liveCompetitionCount, 'Both globals include the live match.');
    }

    public function testSignedInHeroDescribesTheViewersOwnWorld(): void
    {
        $stats = $this->queryBus()->handle(new GetCompetitionsPageStats(
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        // VERIFIED_USER owns and plays in exactly „Kámoši u piva" over the private source.
        self::assertSame(1, $stats->activeCompetitionCount);
        self::assertSame(1, $stats->matchSourceCount);
        self::assertSame(1, $stats->matchCount);
        // The competition has two active members (VERIFIED_USER + the anonymous one).
        self::assertSame(2, $stats->playerCount);
        self::assertSame(0, $stats->liveCompetitionCount);
    }

    public function testJoiningACompetitionMovesEverySignedInFigure(): void
    {
        $before = $this->queryBus()->handle(new GetCompetitionsPageStats(
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::PUBLIC_COMPETITION_LINK_TOKEN,
        ));

        $after = $this->queryBus()->handle(new GetCompetitionsPageStats(
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertSame($before->activeCompetitionCount + 1, $after->activeCompetitionCount);
        self::assertSame($before->matchSourceCount + 1, $after->matchSourceCount);
        self::assertGreaterThan($before->matchCount, $after->matchCount);
        self::assertGreaterThan($before->playerCount, $after->playerCount);
        // The join happened at the fixed clock ⇒ it counts as „tento týden".
        self::assertGreaterThan(0, $after->newPlayerCount);
    }

    public function testSomeoneInNothingGetsAnHonestZeroInsteadOfTheGlobalNumbers(): void
    {
        $stats = $this->queryBus()->handle(new GetCompetitionsPageStats(
            viewerId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame(0, $stats->activeCompetitionCount);
        self::assertSame(0, $stats->playerCount);
        self::assertSame(0, $stats->matchCount);
        self::assertSame(0, $stats->matchSourceCount);
    }
}
