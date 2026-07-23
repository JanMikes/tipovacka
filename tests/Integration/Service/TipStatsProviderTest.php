<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\Uid\Uuid;

/**
 * The provider behind every „Rozložení tipů" surface. Its cost must scale with the
 * number of COMPETITIONS on the page, never with the number of matches — that is
 * the N+1 the per-match query used to invite.
 */
final class TipStatsProviderTest extends IntegrationTestCase
{
    private function provider(): TipStatsProvider
    {
        return self::getContainer()->get(TipStatsProvider::class);
    }

    private function boostsCompetition(): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function member(): User
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $user);

        return $user;
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

    /**
     * @param list<SportMatch> $matches
     */
    private function costOfResolving(Competition $competition, array $matches, User $viewer): int
    {
        // Both per-request caches (boost ownership, match selections) are warm for
        // both measurements, so what is left is the query cost of the pairs alone.
        $before = $this->executedStatements();
        $this->provider()->forCompetition($competition, $matches, $viewer);

        return $this->executedStatements() - $before;
    }

    public function testCostDoesNotGrowWithTheNumberOfMatches(): void
    {
        $competition = $this->boostsCompetition();
        $viewer = $this->member();
        $matches = self::getContainer()->get(CompetitionMatchProvider::class)->matchesFor($competition);

        self::assertGreaterThan(1, count($matches), 'The fixture competition needs several matches to make this meaningful.');

        // Warm-up pass: primes the per-request caches so the two measurements below
        // compare like with like.
        $this->provider()->forCompetition($competition, $matches, $viewer);

        $one = $this->costOfResolving($competition, [$matches[0]], $viewer);
        $all = $this->costOfResolving($competition, $matches, $viewer);

        self::assertSame($one, $all, sprintf(
            'Resolving %d matches cost %d statements vs %d for a single one — that is an N+1.',
            count($matches),
            $all,
            $one,
        ));
    }

    public function testMemberWithTheBoostSeesTheSplitWhileTheOrganizerDoesNot(): void
    {
        $competition = $this->boostsCompetition();
        // A match whose deadline has NOT passed — after it, the split is public to
        // everyone and neither entitlement matters.
        $match = $this->entityManager()->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);

        // SECOND_VERIFIED_USER owns OthersTips in fixtures (a superset of the bar).
        $memberStats = $this->provider()->forCompetition($competition, [$match], $this->member());
        self::assertTrue($memberStats[$match->id->toRfc4122()]->visible);

        // The owner (ADMIN) bought nothing — no free pass since 2026-07-23.
        $owner = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertInstanceOf(User::class, $owner);

        $ownerStats = $this->provider()->forCompetition($competition, [$match], $owner);
        self::assertFalse($ownerStats[$match->id->toRfc4122()]->visible);
        self::assertTrue($ownerStats[$match->id->toRfc4122()]->purchasable);
    }
}
