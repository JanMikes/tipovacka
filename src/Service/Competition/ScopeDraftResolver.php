<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\SportMatch;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\SportMatchState;
use App\Value\CompetitionSourceSpec;
use App\Value\DuplicateFixtureGroup;
use App\Value\ScopeDraft;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Resolves a set of UNSAVED {@see CompetitionSourceSpec} layers into the
 * fixture list they would produce — the preview behind „Celkem 321 zápasů,
 * 15. 8. – 24. 5." while someone is still composing a soutěž.
 *
 * It deliberately mirrors {@see CompetitionMatchProvider}'s per-layer rules
 * rather than reusing it: the provider answers for a PERSISTED competition,
 * and a draft has no rows yet. The two must agree, and
 * ScopeDraftResolverTest pins them against each other.
 */
final readonly class ScopeDraftResolver
{
    /**
     * How far apart two same-teams fixtures may kick off and still be treated
     * as the same real-world match. A day is wide enough to catch „the curated
     * zdroj says 18:00, I typed 20:00 on the same evening" and a timezone slip,
     * narrow enough to leave a genuine two-legged tie alone.
     */
    private const string DUPLICATE_WINDOW = '24 hours';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<CompetitionSourceSpec> $specs
     */
    public function resolve(array $specs): ScopeDraft
    {
        $sourceIds = [];

        foreach ($specs as $spec) {
            if (null !== $spec->matchSourceId) {
                $sourceIds[$spec->matchSourceId->toRfc4122()] = $spec->matchSourceId;
            }
        }

        $bySource = $this->matchesBySource(array_values($sourceIds));

        $picked = [];
        $layerCounts = [];

        foreach ($specs as $spec) {
            if (null === $spec->matchSourceId) {
                // „Moje zápasy" that has no zdroj YET — its matches are entered
                // after the soutěž exists, so a draft contributes none. (The
                // manage screen points the spec at the zdroj it already has, so
                // there it goes down the normal branch.)
                $layerCounts[] = 0;

                continue;
            }

            $layerCount = 0;

            foreach ($bySource[$spec->matchSourceId->toRfc4122()] ?? [] as $match) {
                if ($this->accepts($spec, $match)) {
                    $picked[$match->id->toRfc4122()] = $match;
                    ++$layerCount;
                }
            }

            // What THIS layer contributes on its own — counted in the same pass
            // as the union, so a basket never costs one query per card.
            $layerCounts[] = $layerCount;
        }

        $matches = array_values($picked);
        usort($matches, static fn (SportMatch $a, SportMatch $b): int => [$a->kickoffAt, $a->id->toRfc4122()] <=> [$b->kickoffAt, $b->id->toRfc4122()]);

        return new ScopeDraft($matches, $this->duplicateGroups($matches), $layerCounts);
    }

    private function accepts(CompetitionSourceSpec $spec, SportMatch $match): bool
    {
        if (CompetitionMatchSelectionMode::Subset === $spec->selectionMode) {
            foreach ($spec->selectedMatchIds as $selectedId) {
                if ($selectedId->equals($match->id)) {
                    return true;
                }
            }

            return false;
        }

        if (CompetitionMatchSelectionMode::Teams === $spec->selectionMode) {
            foreach ($spec->filterTeamIds as $teamId) {
                if ($teamId->equals($match->homeTeam->id) || $teamId->equals($match->awayTeam->id)) {
                    return true;
                }
            }

            return false;
        }

        return $spec->includePlayoff || !$match->isPlayoff;
    }

    /**
     * Fixtures that look like the same real-world match: identical home and
     * away team NAMES (not ids — a club is a global directory team in a curated
     * zdroj and a local one in a private zdroj) kicking off within a day of
     * each other. Home/away is NOT normalised: the reverse fixture of a
     * two-legged tie is a different match.
     *
     * @param list<SportMatch> $matches kickoff-ordered
     *
     * @return list<DuplicateFixtureGroup>
     */
    private function duplicateGroups(array $matches): array
    {
        $byPairing = [];

        foreach ($matches as $match) {
            $key = mb_strtolower(trim($match->homeTeam->name).'|'.trim($match->awayTeam->name));
            $byPairing[$key][] = $match;
        }

        $groups = [];

        foreach ($byPairing as $candidates) {
            if (count($candidates) < 2) {
                continue;
            }

            // Candidates are already kickoff-ordered, so a run of fixtures each
            // within the window of its predecessor is one group.
            $run = [$candidates[0]];

            for ($i = 1, $count = count($candidates); $i < $count; ++$i) {
                $previous = $candidates[$i - 1];
                $limit = $previous->kickoffAt->modify('+'.self::DUPLICATE_WINDOW);

                if ($candidates[$i]->kickoffAt <= $limit) {
                    $run[] = $candidates[$i];

                    continue;
                }

                if (count($run) > 1) {
                    $groups[] = new DuplicateFixtureGroup($run);
                }

                $run = [$candidates[$i]];
            }

            if (count($run) > 1) {
                $groups[] = new DuplicateFixtureGroup($run);
            }
        }

        return $groups;
    }

    /**
     * @param list<Uuid> $sourceIds
     *
     * @return array<string, list<SportMatch>>
     */
    private function matchesBySource(array $sourceIds): array
    {
        if ([] === $sourceIds) {
            return [];
        }

        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager->createQueryBuilder()
            ->select('m', 'ht', 'at', 'ms')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.homeTeam', 'ht')
            ->innerJoin('m.awayTeam', 'at')
            ->innerJoin('m.matchSource', 'ms')
            ->where('m.matchSource IN (:sourceIds)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state != :cancelled')
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setParameter('sourceIds', $sourceIds)
            ->setParameter('cancelled', SportMatchState::Cancelled)
            ->getQuery()
            ->getResult();

        $bySource = [];

        foreach ($matches as $match) {
            $bySource[$match->matchSource->id->toRfc4122()][] = $match;
        }

        return $bySource;
    }
}
