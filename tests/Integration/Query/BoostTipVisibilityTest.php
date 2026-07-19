<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetCompetitionGuessMatrix\GetCompetitionGuessMatrix;
use App\Query\GetGuessesForMatchInCompetition\GetGuessesForMatchInCompetition;
use App\Query\GetGuessesForMatchInCompetition\GuessForMatchItem;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Per-viewer tip visibility in a `boosts` competition: the OthersTips holder
 * (SECOND_VERIFIED_USER, fixture) sees concrete tips before the deadline; a
 * member without the boost (VERIFIED_USER, joined here) does not; post-deadline
 * everyone sees. See .docs/DOMAIN.md §Tips visibility + the 2026-07-19 decision.
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
     * @return array<string, GuessForMatchItem> keyed by user RFC4122
     */
    private function guessesFor(string $viewerId, string $matchId): array
    {
        $result = $this->queryBus()->handle(new GetGuessesForMatchInCompetition(
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString($matchId),
            viewerId: Uuid::fromString($viewerId),
        ));

        $byUser = [];
        foreach ($result->items as $item) {
            $byUser[$item->userId->toRfc4122()] = $item;
        }

        return $byUser;
    }

    public function testOthersTipsHolderSeesConcreteTipsBeforeDeadline(): void
    {
        $this->seedScheduledTips();

        $items = $this->guessesFor(self::HOLDER, AppFixtures::MATCH_SCHEDULED_ID);

        // The OthersTips holder sees the plain member's concrete tip.
        self::assertFalse($items[self::PLAIN]->hidden);
        self::assertSame(0, $items[self::PLAIN]->homeScore);
        self::assertSame(0, $items[self::PLAIN]->awayScore);
    }

    public function testNonEntitledMemberDoesNotSeeOthersTipsBeforeDeadline(): void
    {
        $this->seedScheduledTips();

        $items = $this->guessesFor(self::PLAIN, AppFixtures::MATCH_SCHEDULED_ID);

        // Own tip visible…
        self::assertFalse($items[self::PLAIN]->hidden);
        self::assertTrue($items[self::PLAIN]->isMine);
        // …but the holder's tip is hidden (no boost, before the deadline).
        self::assertTrue($items[self::HOLDER]->hidden);
        self::assertNull($items[self::HOLDER]->homeScore);
    }

    public function testAfterDeadlineEveryoneSees(): void
    {
        // MATCH_FINISHED (2025-06-10) is past its deadline for everyone. Seed tips
        // directly (the submit command would reject a past-deadline match).
        $this->persistGuess(self::HOLDER, 2, 2);
        $this->persistGuess(self::PLAIN, 1, 0);

        // The non-entitled viewer still sees everyone's concrete tips post-deadline.
        $items = $this->guessesFor(self::PLAIN, AppFixtures::MATCH_FINISHED_ID);

        self::assertFalse($items[self::HOLDER]->hidden);
        self::assertSame(2, $items[self::HOLDER]->homeScore);
    }

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

    private function persistGuess(string $userId, int $home, int $away): void
    {
        $em = $this->entityManager();
        $user = $em->find(User::class, Uuid::fromString($userId));
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertInstanceOf(Competition::class, $competition);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: $home,
            awayScore: $away,
            submittedAt: new \DateTimeImmutable('2025-06-09 10:00:00 UTC'),
        );
        $guess->popEvents();
        $em->persist($guess);
        $em->flush();
        $em->clear();
    }
}
