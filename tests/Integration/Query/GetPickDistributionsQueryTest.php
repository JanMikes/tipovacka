<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Query\GetPickDistributions\GetPickDistributions;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\Uid\Uuid;

/**
 * The batch distribution query behind every match list: one round trip for the
 * whole page, correct per (competition, match) split, and no leakage between
 * competitions that share a match.
 */
final class GetPickDistributionsQueryTest extends IntegrationTestCase
{
    private function tip(string $userId, string $competitionId, string $matchId, int $home, int $away): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString($competitionId),
            sportMatchId: Uuid::fromString($matchId),
            homeScore: $home,
            awayScore: $away,
        ));
    }

    public function testSplitsAreGroupedPerCompetitionAndMatch(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));

        // Boosts competition: one home win + one draw on the scheduled match.
        $this->tip(AppFixtures::VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID, 3, 1);
        $this->tip(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID, 2, 2);

        $result = $this->queryBus()->handle(new GetPickDistributions(
            competitionIds: [Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID)],
            sportMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            ],
        ));

        $tipped = $result->for(
            Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        );
        self::assertSame(2, $tipped->total);
        self::assertSame(1, $tipped->homeWinCount);
        self::assertSame(1, $tipped->drawCount);
        self::assertSame(0, $tipped->awayWinCount);
        self::assertSame(50, $tipped->homeWinPercent);
        self::assertSame(50, $tipped->drawPercent);

        // A pair nobody tipped answers with an empty result, never a missing key.
        $untipped = $result->for(
            Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
        );
        self::assertSame(0, $untipped->total);
        self::assertSame(0, $untipped->homeWinPercent);
    }

    public function testWholePageCostsASingleQueryWhateverTheNumberOfPairs(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->tip(AppFixtures::VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID, 1, 0);

        $before = $this->executedStatements();

        $this->queryBus()->handle(new GetPickDistributions(
            competitionIds: [
                Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
                Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
                Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            ],
            sportMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
            ],
        ));

        // 3 competitions × 3 matches resolved by ONE statement — the whole point
        // of the batch query (a per-pair resolve would be nine).
        self::assertSame(1, $this->executedStatements() - $before, 'The batch must stay a single statement.');
    }

    private function executedStatements(): int
    {
        /** @var DebugDataHolder $holder */
        $holder = self::getContainer()->get('doctrine.debug_data_holder');

        $count = 0;

        foreach ($holder->getData() as $queries) {
            $count += count($queries);
        }

        return $count;
    }
}
