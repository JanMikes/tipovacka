<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\MissingTips;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * THE single answer to „how many matches does this viewer still owe a tip for in
 * competition C" — the number behind the „Chybí N tipů" badge on both card
 * surfaces (Nástěnka „Moje soutěže" and /souteze „Soutěže, kde tipuješ").
 *
 * The rule lives in {@see forIncludedMatches} and nowhere else, so the two pages
 * can never disagree: a match counts iff
 *
 * 1. {@see CompetitionMatchProvider} says the competition includes it,
 * 2. it is open for guesses (`SportMatch::$isOpenForGuesses`),
 * 3. the viewer has no guess on it in that competition, and
 * 4. its effective deadline ({@see EffectiveTipDeadlineResolver}, per match and
 *    per viewer — it honours the „Měnit tip" entitlement and manager overrides)
 *    is still ahead of `$now`.
 *
 * Rule 4 is what keeps the badge off a finished or locked soutěž: an untipped
 * match past its deadline is „Netipováno" (B5) — a fact, not a call to action.
 *
 * **Batched, never per card.** {@see forCompetitions} resolves a whole page in a
 * constant number of statements plus the deadline resolution, which only runs for
 * competitions that still have an untipped OPEN match — a finished soutěž costs
 * nothing. Callers that already hold the competition's matches (the /souteze list
 * query loads them for its other aggregates) call {@see forIncludedMatches}
 * directly instead of paying for them twice.
 */
final readonly class MissingTipCounter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
    ) {
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return array<string, MissingTips> competition UUID (RFC4122) → its missing tips
     */
    public function forCompetitions(User $user, array $competitions, \DateTimeImmutable $now): array
    {
        if ([] === $competitions) {
            return [];
        }

        $matchesBySource = $this->matchesBySource($competitions);
        $tippedByCompetition = $this->tippedMatchIds($user, $competitions);

        $result = [];

        foreach ($competitions as $competition) {
            $key = $competition->id->toRfc4122();
            $sourceKey = $competition->matchSource->id->toRfc4122();

            $included = array_values(array_filter(
                $matchesBySource[$sourceKey] ?? [],
                fn (SportMatch $m): bool => $this->matchProvider->includes($competition, $m),
            ));

            $result[$key] = $this->forIncludedMatches(
                $competition,
                $included,
                $tippedByCompetition[$key] ?? [],
                $user,
                $now,
            );
        }

        return $result;
    }

    /**
     * The rule itself, for a competition whose included matches and tipped match
     * ids the caller already has.
     *
     * @param list<SportMatch>    $includedMatches matches {@see CompetitionMatchProvider} includes in $competition
     * @param array<string, true> $tippedMatchIds  match UUIDs (RFC4122) the viewer already tipped in $competition
     */
    public function forIncludedMatches(
        Competition $competition,
        array $includedMatches,
        array $tippedMatchIds,
        User $user,
        \DateTimeImmutable $now,
    ): MissingTips {
        $untipped = array_values(array_filter(
            $includedMatches,
            static fn (SportMatch $m): bool => $m->isOpenForGuesses && !isset($tippedMatchIds[$m->id->toRfc4122()]),
        ));

        if ([] === $untipped) {
            return MissingTips::none();
        }

        $windows = $this->deadlineResolver->windowsFor($competition, $untipped, $user);

        $count = 0;
        $earliest = null;

        foreach ($untipped as $match) {
            $window = $windows[$match->id->toRfc4122()] ?? null;

            // „Chybí" must be actionable: a match whose tipping has not opened yet
            // owes the player nothing, and its deadline must not become the
            // „nejbližší uzávěrka" they are urged to beat.
            if (null === $window || $window->isWaiting($now)) {
                continue;
            }

            $deadline = $window->deadline;

            if ($now >= $deadline) {
                continue;
            }

            ++$count;

            if (null === $earliest || $deadline < $earliest) {
                $earliest = $deadline;
            }
        }

        return new MissingTips($count, $earliest);
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return array<string, list<SportMatch>> match source UUID → its (non-deleted) matches
     */
    private function matchesBySource(array $competitions): array
    {
        $sourceIds = [];

        foreach ($competitions as $competition) {
            $sourceIds[$competition->matchSource->id->toRfc4122()] = $competition->matchSource->id;
        }

        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.matchSource IN (:sourceIds)')
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setParameter('sourceIds', array_values($sourceIds))
            ->getQuery()
            ->getResult();

        $bySource = [];

        foreach ($matches as $match) {
            $bySource[$match->matchSource->id->toRfc4122()][] = $match;
        }

        return $bySource;
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return array<string, array<string, true>> competition UUID → tipped match UUID set
     */
    private function tippedMatchIds(User $user, array $competitions): array
    {
        $competitionIds = array_map(static fn (Competition $c): Uuid => $c->id, $competitions);

        /** @var list<array{competitionId: string, sportMatchId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.competition) AS competitionId', 'IDENTITY(g.sportMatch) AS sportMatchId')
            ->from(Guess::class, 'g')
            ->where('g.competition IN (:competitionIds)')
            ->andWhere('g.user = :userId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionIds', $competitionIds)
            ->setParameter('userId', $user->id)
            ->getQuery()
            ->getArrayResult();

        $tipped = [];

        foreach ($rows as $row) {
            $tipped[$row['competitionId']][$row['sportMatchId']] = true;
        }

        return $tipped;
    }
}
