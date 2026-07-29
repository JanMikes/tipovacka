<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetTeamForm\GetTeamForm;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „ARG · V2 R0 P0" — the win/draw/loss record under a team name on the match
 * detail page. Counted over the FINISHED matches the SOUTĚŽ includes, so it can
 * never disagree with the rest of the page.
 */
final class GetTeamFormQueryTest extends IntegrationTestCase
{
    public function testRecordOverTheCompetitionsFinishedMatches(): void
    {
        // The only finished fixture match is Bohemians 2:1 Jablonec.
        $result = $this->queryBus()->handle(new GetTeamForm(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            teamIds: [
                Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID),
                Uuid::fromString(AppFixtures::TEAM_JABLONEC_ID),
            ],
        ));

        $home = $result->for(Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID));
        self::assertNotNull($home);
        self::assertSame([1, 0, 0, 1], [$home->wins, $home->draws, $home->losses, $home->played]);

        $away = $result->for(Uuid::fromString(AppFixtures::TEAM_JABLONEC_ID));
        self::assertNotNull($away);
        self::assertSame([0, 0, 1, 1], [$away->wins, $away->draws, $away->losses, $away->played]);
    }

    /** A team with nothing behind it is ABSENT — never a zeroed „V0 R0 P0" row. */
    public function testTeamWithoutAFinishedMatchIsAbsent(): void
    {
        $result = $this->queryBus()->handle(new GetTeamForm(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            teamIds: [Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)],
        ));

        self::assertNull($result->for(Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)));
    }

    /** Scope comes from CompetitionMatchProvider: another source counts for nothing. */
    public function testCompetitionOnAnotherSourceSeesNoMatches(): void
    {
        $result = $this->queryBus()->handle(new GetTeamForm(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            teamIds: [Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID)],
        ));

        self::assertNull($result->for(Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID)));
    }

    public function testNoTeamsAsked(): void
    {
        $result = $this->queryBus()->handle(new GetTeamForm(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            teamIds: [],
        ));

        self::assertNull($result->for(Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID)));
    }
}
