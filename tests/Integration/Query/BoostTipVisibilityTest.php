<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetCompetitionGuessMatrix\GetCompetitionGuessMatrix;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Query\GetMatchRanking\MatchRankingRow;
use App\Service\Competition\TipVisibilityGate;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Per-viewer tip visibility in a `boosts` competition: the OthersTips holder
 * (SECOND_VERIFIED_USER, fixture) reads concrete tips while a match is still ahead;
 * a member without the boost (VERIFIED_USER, joined here) does not. The deadline is
 * not a door (2026-07-30) — see .docs/DOMAIN.md §Tips visibility.
 *
 * **What this file is for since ui-nav item 33.** It used to assert on
 * `GetGuessesForMatchInCompetition`, a read model that masked other members' rows
 * itself. Item 22 folded that surface into „Pořadí za zápas" and item 33 deleted the
 * query, so the architecture is now split: {@see GetMatchRanking} does **not** gate
 * (it has no viewer at all) and the caller owes the decision — the match page asks
 * {@see TipVisibilityGate} and then either hands the whole board to the template or
 * none of it.
 *
 * That split is what the first test pins, and it is worth pinning precisely because
 * the page-level paywall test can pass VACUOUSLY: „no table rendered" is equally
 * true of a board that was withheld and of a board that was empty. Here the board is
 * fetched ungated and shown to really carry the other member's concrete tip, so the
 * gate closing over it is provably load-bearing.
 *
 * Everything else this file used to cover now lives closer to the user and is not
 * repeated (item 33, case by case):
 * - an entitled holder READING the board → `Portal\Competition\CompetitionMatchDetailFlowTest::testRankingIsVisibleWithTheOthersTipsBoost`,
 *   same soutěž, same match, asserted on the rendered table;
 * - a played match opening the board to everyone → `Service\TipVisibilityGateTest`
 *   plus `CompetitionMatchDetailFlowTest::testTheRankingCarriesTheOptionalTipPartsOfTheFoldedAwayList`,
 *   which renders real rows for a viewer entitled to nothing.
 */
final class BoostTipVisibilityTest extends IntegrationTestCase
{
    /** The OthersTips holder (fixture member). */
    private const string HOLDER = AppFixtures::SECOND_VERIFIED_USER_ID;
    /** A non-entitled member joined on the fly. */
    private const string PLAIN = AppFixtures::VERIFIED_USER_ID;

    private function seedScheduledTips(): void
    {
        // VERIFIED_USER joins as a second, non-entitled member.
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(self::PLAIN),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));

        // MATCH_SCHEDULED (2025-06-20, future) is late-added to BOOSTS_COMPETITION
        // (created 2025-06-15, after the source's first kickoff) ⇒ its deadline is
        // its own kickoff ⇒ still open, tips hidden by default before it.
        $this->submit(self::HOLDER, 3, 1);
        $this->submit(self::PLAIN, 0, 0);
    }

    private function submit(string $userId, int $home, int $away): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: $home,
            awayScore: $away,
        ));
    }

    /**
     * The entitlement is the ONLY thing between a non-entitled member and the other
     * member's concrete tip on an unplayed match — and there really is one to leak.
     *
     * Both halves matter. The gate answering `false` is worth nothing if the board it
     * guards is empty, and the board carrying the tip is worth nothing if something
     * other than the gate happens to be hiding it. So the ungated board is read first
     * (it holds both tips, concrete), and the gate is then shown to open for the
     * holder and shut for the plain member on that very same pair.
     */
    public function testTheEntitlementIsTheOnlyThingWithholdingARealBoardOnAnUnplayedMatch(): void
    {
        $this->seedScheduledTips();

        // 1. The board itself. `GetMatchRanking` takes no viewer and gates nothing,
        //    so this is what a leak would expose, in full.
        $rows = $this->rankingRowsByUser();

        self::assertArrayHasKey(self::HOLDER, $rows);
        self::assertArrayHasKey(self::PLAIN, $rows);
        self::assertSame(3, $rows[self::HOLDER]->guessHome);
        self::assertSame(1, $rows[self::HOLDER]->guessAway);

        // 2. The gate over it, per viewer, on the same (soutěž, zápas) pair.
        $gate = $this->gate();
        $competition = $this->competition();
        $sportMatch = $this->sportMatch();

        self::assertTrue(
            $gate->canSeeOthersTips($competition, $this->user(self::HOLDER), $sportMatch),
            'The OthersTips boost reads the board before the match is played — that is what it sells.',
        );
        self::assertFalse(
            $gate->canSeeOthersTips($competition, $this->user(self::PLAIN), $sportMatch),
            'Being a member of the soutěž buys no free look at an unplayed match.',
        );
    }

    /**
     * The matrix read model still masks per row (it renders every member × every
     * match at once, so an all-or-nothing answer would be useless), and it makes that
     * decision per viewer with the same gate.
     */
    public function testMatrixGatesOtherCellsPerViewer(): void
    {
        $this->seedScheduledTips();
        $matchKey = AppFixtures::MATCH_SCHEDULED_ID;

        // OthersTips holder sees the other member's cell…
        $asHolder = $this->matrixCells(self::HOLDER);
        self::assertFalse($asHolder[self::PLAIN][$matchKey]->hidden);

        // …a member without the boost does not.
        $asPlain = $this->matrixCells(self::PLAIN);
        self::assertTrue($asPlain[self::HOLDER][$matchKey]->hidden);
    }

    /**
     * @return array<string, MatchRankingRow> keyed by user RFC4122
     */
    private function rankingRowsByUser(): array
    {
        $result = $this->queryBus()->handle(new GetMatchRanking(
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        $byUser = [];
        foreach ($result->rows as $row) {
            $byUser[$row->userId->toRfc4122()] = $row;
        }

        return $byUser;
    }

    /**
     * @return array<string, array<string, \App\Query\GetCompetitionGuessMatrix\MatrixCell>>
     */
    private function matrixCells(string $viewerId): array
    {
        $matrix = $this->queryBus()->handle(new GetCompetitionGuessMatrix(
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            requestingUserId: Uuid::fromString($viewerId),
        ));

        $byUser = [];
        foreach ($matrix->members as $row) {
            $byUser[$row->userId->toRfc4122()] = $row->cells;
        }

        return $byUser;
    }

    private function gate(): TipVisibilityGate
    {
        /* @var TipVisibilityGate */
        return self::getContainer()->get(TipVisibilityGate::class);
    }

    private function competition(): Competition
    {
        $competition = $this->entityManager()->find(
            Competition::class,
            Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
        );
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function sportMatch(): SportMatch
    {
        $sportMatch = $this->entityManager()->find(
            SportMatch::class,
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        );
        self::assertInstanceOf(SportMatch::class, $sportMatch);

        return $sportMatch;
    }

    private function user(string $id): User
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString($id));
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
