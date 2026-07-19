<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\GuessScorer;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Enum\MatchSide;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\TooManyGuessScorers;
use App\Service\Guess\GuessScorerWriter;
use App\Tests\Support\IntegrationTestCase;
use App\Value\GuessScorerInput;
use App\Value\PeriodScores;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * S06: feature toggles = rule enablement. PUBLIC_COMPETITION has all optional
 * rules disabled — every payload part must be rejected separately with a Czech
 * 422. SUBSET_COMPETITION (scorer + period rules on) accepts them, free-creates
 * players and fully replaces scorer rows on update.
 */
final class GuessTipFeaturesTest extends IntegrationTestCase
{
    public function testPeriodPayloadRejectedWhenPeriodRulesDisabled(): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Tato soutěž netipuje části zápasu.');

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                homeScore: 2,
                awayScore: 1,
                periodScores: PeriodScores::fromArray([[1, 0], [1, 1]]),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessFeatureNotEnabled::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testOvertimePayloadRejectedWhenOvertimeRuleDisabled(): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Tato soutěž netipuje prodloužení.');

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
            overtimeHomeScore: 2,
            overtimeAwayScore: 1,
        ));
    }

    public function testScorerPayloadRejectedWhenScorerRuleDisabled(): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Tato soutěž netipuje střelce.');

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            scorers: [new GuessScorerInput(MatchSide::Home, 'Jan Novák')],
        ));
    }

    public function testFreeTypedScorerCreatesPlayerInSourcePool(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1]]),
            scorers: [new GuessScorerInput(MatchSide::Home, 'Karel Nováček')],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Player|null $player */
        $player = $em->createQueryBuilder()
            ->select('p')
            ->from(Player::class, 'p')
            ->where('p.name = :name')
            ->setParameter('name', 'Karel Nováček')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Player::class, $player);
        // Home side of MATCH_SCHEDULED ⇒ pool key (PUBLIC source, Sparta Praha).
        self::assertSame('Sparta Praha', $player->teamName);
        self::assertTrue($player->matchSource->id->equals(Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);
        self::assertCount(1, $guess->scorers);
        self::assertSame([[1, 0], [1, 1]], $guess->periodScores?->toArray());
    }

    public function testUpdateFullyReplacesScorerRows(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            scorers: [
                new GuessScorerInput(MatchSide::Home, 'Karel Nováček'),
                new GuessScorerInput(MatchSide::Away, 'Ondřej Hostující'),
            ],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);

        // Keep Karel (case-insensitive match), drop Ondřej, add Pavel.
        $this->commandBus()->dispatch(new UpdateGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            guessId: $guess->id,
            homeScore: 3,
            awayScore: 1,
            scorers: [
                new GuessScorerInput(MatchSide::Home, 'karel nováček'),
                new GuessScorerInput(MatchSide::Home, 'Pavel Domácí'),
            ],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);

        $names = [];

        foreach ($guess->scorers as $scorer) {
            $names[] = $scorer->player->name;
        }

        sort($names);
        // „Karel Nováček" kept with first-seen casing (case-insensitive pool).
        self::assertSame(['Karel Nováček', 'Pavel Domácí'], $names);
        self::assertSame(3, $guess->homeScore);
    }

    public function testUpdateWithEmptyScorersClearsRows(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            scorers: [new GuessScorerInput(MatchSide::Home, 'Karel Nováček')],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new UpdateGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            guessId: $guess->id,
            homeScore: 2,
            awayScore: 1,
            scorers: [],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);
        self::assertCount(0, $guess->scorers);
    }

    public function testScorerCapRejectsSixTips(): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessage('Můžete tipnout nejvýše 5 střelců.');

        try {
            $this->commandBus()->dispatch(new SubmitGuessCommand(
                userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                homeScore: 2,
                awayScore: 1,
                scorers: [
                    new GuessScorerInput(MatchSide::Home, 'Hráč Jedna'),
                    new GuessScorerInput(MatchSide::Home, 'Hráč Dva'),
                    new GuessScorerInput(MatchSide::Home, 'Hráč Tři'),
                    new GuessScorerInput(MatchSide::Away, 'Hráč Čtyři'),
                    new GuessScorerInput(MatchSide::Away, 'Hráč Pět'),
                    new GuessScorerInput(MatchSide::Away, 'Hráč Šest'),
                ],
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(TooManyGuessScorers::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testScorerSideIsPersistedAndSurvivesTeamNameCasingDrift(): void
    {
        $em = $this->entityManager();

        /** @var MatchSource|null $source */
        $source = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);

        // Roster-pool row with drifted casing vs the match's 'Sparta Praha'.
        $drifted = new Player(
            id: Uuid::v7(),
            matchSource: $source,
            teamName: 'SPARTA PRAHA',
            name: 'Kasing Odolný',
            createdAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $em->persist($drifted);
        $em->flush();

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            scorers: [new GuessScorerInput(MatchSide::Home, 'Kasing Odolný')],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);
        $scorer = $guess->scorers->first();
        self::assertInstanceOf(GuessScorer::class, $scorer);

        // The drifted pool row was reused (case-insensitive lookup) …
        self::assertSame('SPARTA PRAHA', $scorer->player->teamName);
        self::assertNotSame($guess->sportMatch->homeTeam, $scorer->player->teamName);
        // … and the SUBMITTED side is persisted, never re-derived by comparing
        // the player's team name to the match's current home team string.
        self::assertSame(MatchSide::Home, $scorer->side);

        /** @var GuessScorerWriter $writer */
        $writer = self::getContainer()->get(GuessScorerWriter::class);
        $inputs = $writer->inputsFor($guess);
        self::assertCount(1, $inputs);
        self::assertSame(MatchSide::Home, $inputs[0]->side);

        // Round-trip: a pass-through update keeps the side stable (no flip).
        $this->commandBus()->dispatch(new UpdateGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            guessId: $guess->id,
            homeScore: 3,
            awayScore: 1,
            scorers: $inputs,
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);
        self::assertCount(1, $guess->scorers);
        $scorer = $guess->scorers->first();
        self::assertInstanceOf(GuessScorer::class, $scorer);
        self::assertSame(MatchSide::Home, $scorer->side);
        self::assertSame('SPARTA PRAHA', $scorer->player->teamName);
    }

    public function testDuplicateScorerInputsCollapseToOneRow(): void
    {
        // Same player twice (different casing) ⇒ single row, no constraint clash.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
            scorers: [
                new GuessScorerInput(MatchSide::Home, 'Karel Nováček'),
                new GuessScorerInput(MatchSide::Home, 'KAREL NOVÁČEK'),
            ],
        ));

        $guess = $this->findGuess(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::MATCH_SCHEDULED_ID);
        self::assertCount(1, $guess->scorers);
    }

    private function findGuess(string $userId, string $matchId): Guess
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('u', Uuid::fromString($userId))
            ->setParameter('m', Uuid::fromString($matchId))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Guess::class, $guess);

        return $guess;
    }
}
