<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * THE single answer to „which round (kolo/fáze) is this competition in right
 * now" — `SportMatch::$round` is a free-text label, so a round is simply the set
 * of the competition's matches sharing one label.
 *
 * Resolution (deliberately deadline- and result-independent):
 *   1. the round of the LATEST already-kicked-off match that carries a label;
 *   2. otherwise the round of the EARLIEST upcoming match that carries a label
 *      (nothing has started yet — the competition's first round is „current");
 *   3. otherwise null — the competition has no round-labelled match at all.
 *
 * Matches without a label are skipped rather than treated as an unnamed round:
 * a round is a name, and a nameless match belongs to none. Cancelled matches are
 * skipped too (they are never played, so they can never be „current").
 *
 * Membership always goes through {@see CompetitionMatchProvider}, so the answer
 * respects the competition's selection mode and playoff setting.
 */
final readonly class CompetitionRoundResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionMatchProvider $matchProvider,
        private ClockInterface $clock,
    ) {
    }

    public function currentRound(Competition $competition): ?string
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->firstRound($competition, started: true, now: $now)
            ?? $this->firstRound($competition, started: false, now: $now);
    }

    /**
     * How many of the competition's matches belong to the given round, and how
     * many of those are already finished. Both counts respect the competition's
     * match scope.
     *
     * @return array{matchCount: int, finishedMatchCount: int}
     */
    public function roundProgress(Competition $competition, string $round): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'COUNT(m.id) AS matchCount',
                'SUM(CASE WHEN m.state = :cr_finished THEN 1 ELSE 0 END) AS finishedMatchCount',
            )
            ->from(SportMatch::class, 'm')
            ->andWhere('m.round = :cr_round')
            ->andWhere('m.state != :cr_cancelled')
            ->setParameter('cr_round', $round)
            ->setParameter('cr_finished', SportMatchState::Finished)
            ->setParameter('cr_cancelled', SportMatchState::Cancelled);

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $competition);

        /** @var array{matchCount: int|string, finishedMatchCount: int|string|null} $row */
        $row = $qb->getQuery()->getSingleResult();

        return [
            'matchCount' => (int) $row['matchCount'],
            'finishedMatchCount' => (int) ($row['finishedMatchCount'] ?? 0),
        ];
    }

    /**
     * The round label of the latest started (`$started = true`, kickoff DESC) or
     * earliest upcoming (`$started = false`, kickoff ASC) labelled match.
     */
    private function firstRound(Competition $competition, bool $started, \DateTimeImmutable $now): ?string
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m.round')
            ->from(SportMatch::class, 'm')
            ->andWhere('m.round IS NOT NULL')
            ->andWhere('m.state != :cr_cancelled')
            ->andWhere(sprintf('m.kickoffAt %s :cr_now', $started ? '<=' : '>'))
            ->orderBy('m.kickoffAt', $started ? 'DESC' : 'ASC')
            ->addOrderBy('m.id', $started ? 'DESC' : 'ASC')
            ->setMaxResults(1)
            ->setParameter('cr_cancelled', SportMatchState::Cancelled)
            ->setParameter('cr_now', $now);

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $competition);

        /** @var list<array{round: string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $rows[0]['round'] ?? null;
    }
}
