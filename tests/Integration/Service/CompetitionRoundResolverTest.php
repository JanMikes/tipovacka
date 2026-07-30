<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Service\Competition\CompetitionRoundResolver;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „Which round (kolo/fáze) is this competition in right now" — the resolution
 * built by item 02. Item 15 retired the leaderboard's „Poslední kolo" board and
 * the `GetCompetitionCurrentRound` query with it, but the resolver itself is
 * still live: the competition detail page renders its eyebrow („zdroj · kolo")
 * from it, so the rules below still describe something true.
 *
 * Fixture baseline (clock 2025-06-15 12:00 UTC, PUBLIC_COMPETITION, mode All):
 *   MATCH_FINISHED  06-10 18:00  round „Základní skupina"  ← latest STARTED labelled match
 *   MATCH_LIVE      06-15 11:00  round NULL                 (started, unlabelled → skipped)
 *   MATCH_SCHEDULED 06-20 18:00  round „Čtvrtfinále"
 *   MATCH_PLAYOFF   06-22 18:00  round „Playoff"
 * ⇒ current round = „Základní skupina".
 */
final class CompetitionRoundResolverTest extends IntegrationTestCase
{
    private const string PUBLIC_ID = AppFixtures::PUBLIC_COMPETITION_ID;

    public function testCurrentRoundIsTheLatestStartedLabelledMatch(): void
    {
        self::assertSame('Základní skupina', $this->currentRound());
    }

    public function testCurrentRoundSkipsUnlabelledMatchesRatherThanReportingAnEmptyRound(): void
    {
        // Give the (already started, later) live match a round of its own — it now
        // becomes the latest started LABELLED match, so it wins.
        $em = $this->entityManager();
        $live = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_LIVE_ID));
        self::assertNotNull($live);
        $live->updateDetails(null, null, null, null, $this->now(), round: 'Osmifinále');
        $live->popEvents();
        $em->flush();

        self::assertSame('Osmifinále', $this->currentRound());
    }

    public function testCurrentRoundFallsBackToTheEarliestUpcomingRoundWhenNothingStarted(): void
    {
        // Strip the labels off everything already played ⇒ only future matches
        // carry a round; the earliest of those („Čtvrtfinále", 06-20) is current.
        $em = $this->entityManager();
        $finished = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finished);
        $finished->updateDetails(null, null, null, null, $this->now(), round: null);
        $finished->popEvents();
        $em->flush();

        self::assertSame('Čtvrtfinále', $this->currentRound());
    }

    public function testCompetitionWithoutAnyRoundReportsNoRound(): void
    {
        $em = $this->entityManager();
        foreach ([AppFixtures::MATCH_FINISHED_ID, AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID] as $id) {
            $match = $em->find(SportMatch::class, Uuid::fromString($id));
            self::assertNotNull($match);
            $match->updateDetails(null, null, null, null, $this->now(), round: null);
            $match->popEvents();
        }
        $em->flush();

        self::assertNull($this->currentRound());
    }

    private function currentRound(): ?string
    {
        /** @var CompetitionRoundResolver $resolver */
        $resolver = self::getContainer()->get(CompetitionRoundResolver::class);

        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(self::PUBLIC_ID));
        self::assertNotNull($competition);

        return $resolver->currentRound($competition);
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock()->now());
    }
}
