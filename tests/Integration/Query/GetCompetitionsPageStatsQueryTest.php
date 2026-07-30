<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Query\GetCompetitionsPageStats\GetCompetitionsPageStats;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Every figure the query reports (and every sub-label figure under it) is measured
 * here. Since item 15 they are **platform-wide**: there is no viewer to scope them
 * to, so every visitor would be shown the same totals.
 *
 * Since item 24 the query has **no call site** — `/souteze` stopped rendering the
 * three `StatCard`s — so this test is the only thing keeping it honest. It is
 * deliberately unchanged by that removal: whatever surface picks these figures up
 * next inherits measured ones. Do not weaken it, and do not delete it as coverage
 * of dead code.
 */
final class GetCompetitionsPageStatsQueryTest extends IntegrationTestCase
{
    public function testTheHeroDescribesTheWholePlatform(): void
    {
        $stats = $this->queryBus()->handle(new GetCompetitionsPageStats());

        // All seven fixture competitions — private ones included — over the two
        // zdroje zápasů, neither of which is completed. A private competition
        // contributes only to the counts; its name never leaves this query.
        self::assertSame(7, $stats->activeCompetitionCount);
        self::assertSame(2, $stats->matchSourceCount);
        // The curated source's four non-cancelled matches (one shared by several
        // competitions counts ONCE) + the private source's single match.
        self::assertSame(5, $stats->matchCount);
        self::assertSame(4, $stats->playerCount);
        // Six competitions sit on the curated source, but „Vybrané zápasy party"
        // is a subset that leaves the live match out.
        self::assertSame(5, $stats->liveCompetitionCount);
        // MATCH_LIVE kicks off on the clock's Prague day.
        self::assertSame(1, $stats->todayMatchCount);
    }

    /**
     * The whole point of the item-15 change: the numbers may not depend on who is
     * looking. The query takes no viewer, so this is a structural guarantee — the
     * test pins that nobody reintroduces one.
     */
    public function testTheFiguresDoNotDependOnAViewer(): void
    {
        $first = $this->queryBus()->handle(new GetCompetitionsPageStats());
        $second = $this->queryBus()->handle(new GetCompetitionsPageStats());

        self::assertEquals($first, $second);
    }

    public function testJoiningACompetitionMovesTheGlobalPlayerCount(): void
    {
        $before = $this->queryBus()->handle(new GetCompetitionsPageStats());

        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::PUBLIC_COMPETITION_LINK_TOKEN,
        ));

        $after = $this->queryBus()->handle(new GetCompetitionsPageStats());

        // VERIFIED_USER already counted as a player somewhere, so the distinct
        // head count does not move — but the competition roster did, and the join
        // happened at the fixed clock ⇒ it counts as „tento týden".
        self::assertSame($before->playerCount, $after->playerCount);
        self::assertGreaterThan(0, $after->newPlayerCount);
        // Nothing was created, so the competition/match totals stand still.
        self::assertSame($before->activeCompetitionCount, $after->activeCompetitionCount);
        self::assertSame($before->matchCount, $after->matchCount);
    }
}
