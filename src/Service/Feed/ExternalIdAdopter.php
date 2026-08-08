<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Repository\SportMatchRepository;
use App\Service\Team\TeamResolver;
use Psr\Clock\ClockInterface;

/**
 * One-time bridge from a source's OWN match identifiers to its provider's.
 *
 * Three of the seeded sources cannot simply be bound to a feed: their stored
 * `externalId` is from a different namespace (Premier League carries slugs like
 * „pl2627-arsenal-coventry-city", Chance Liga carries small integers) or absent
 * entirely (MSFL was maintained by hand). Binding them without a bridge would
 * make the very first sync treat every fixture as new and create a duplicate
 * season next to the one people are already tipping.
 *
 * Pairing is by IDENTITY, never by id: same home team, same away team, kickoff
 * within a tolerance (a seeded placeholder time is often hours off the real
 * one). Both teams are resolved through {@see TeamResolver} so the TeamAlias
 * directory does the work it already does for imports.
 *
 * Anything less than exactly one candidate on both sides is reported, not
 * guessed — a mis-adopted id would later write one match's result onto another.
 */
final readonly class ExternalIdAdopter
{
    /**
     * Default distance a stored kickoff may sit from the feed's and still be the
     * same match. Generous on purpose: placeholder kickoffs seeded at 00:00
     * Prague land a whole day away from the real one.
     *
     * Widening it is SAFE for a league: an ordered (home, away) pair meets once
     * per half-season, and any window catching two candidates is reported as
     * ambiguous rather than guessed. Chance Liga needed ~20 days — four rounds
     * were seeded on a placeholder date and one fixture had moved by 17 days.
     */
    public const int DEFAULT_KICKOFF_TOLERANCE_HOURS = 36;

    public function __construct(
        private SportMatchRepository $sportMatches,
        private TeamResolver $teamResolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<MatchSnapshot> $snapshots
     * @param int                 $kickoffToleranceHours how far a stored kickoff may sit from
     *                                                   the feed's and still be the same match
     */
    public function adopt(
        MatchSource $source,
        array $snapshots,
        bool $apply,
        int $kickoffToleranceHours = self::DEFAULT_KICKOFF_TOLERANCE_HOURS,
    ): ExternalIdAdoption {
        $adoption = new ExternalIdAdoption(dryRun: !$apply);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $tolerance = new \DateInterval(sprintf('PT%dH', max(1, $kickoffToleranceHours)));

        $candidates = $this->sportMatches->listByMatchSource($source->id);
        /** @var array<string, true> $consumed */
        $consumed = [];

        foreach ($snapshots as $snapshot) {
            $homeTeam = $this->teamResolver->findExisting($source, $snapshot->homeTeamName);
            $awayTeam = $this->teamResolver->findExisting($source, $snapshot->awayTeamName);

            if (!$homeTeam instanceof Team || !$awayTeam instanceof Team) {
                if (!$homeTeam instanceof Team) {
                    $adoption->addUnresolvedTeam($snapshot->homeTeamName);
                }
                if (!$awayTeam instanceof Team) {
                    $adoption->addUnresolvedTeam($snapshot->awayTeamName);
                }

                continue;
            }

            $fits = array_values(array_filter(
                $candidates,
                fn (SportMatch $match): bool => !isset($consumed[$match->id->toRfc4122()])
                    && $match->homeTeam->id->equals($homeTeam->id)
                    && $match->awayTeam->id->equals($awayTeam->id)
                    && $this->kickoffWithin($match, $snapshot, $tolerance),
            ));

            if ([] === $fits) {
                $adoption->addUnmatchedSnapshot($snapshot->label());

                continue;
            }

            if (count($fits) > 1) {
                // A home-and-away pair playing twice inside the tolerance window
                // (a two-legged tie at short notice) — a human decides.
                $adoption->addAmbiguous($snapshot->label());

                continue;
            }

            $match = $fits[0];
            $consumed[$match->id->toRfc4122()] = true;

            if ($match->externalId === $snapshot->externalId) {
                $adoption->addAlreadyLinked();

                continue;
            }

            if (null !== $match->externalId) {
                // Overwriting an id that came from somewhere else is exactly the
                // kind of silent damage this whole command exists to avoid — but
                // it IS the expected case when re-pointing a source at a new
                // provider, so it is applied and reported, not skipped.
                $adoption->addConflicting(sprintf('%s (was "%s")', $snapshot->label(), $match->externalId));
            }

            if ($apply) {
                $match->linkExternal($snapshot->externalId, $now);
            }

            $adoption->addAdopted($match->id->toRfc4122(), $snapshot->externalId, $snapshot->label());
        }

        foreach ($candidates as $match) {
            if (!isset($consumed[$match->id->toRfc4122()])) {
                $adoption->addUnmatchedMatch(sprintf(
                    '%s – %s (%s)',
                    $match->homeTeam->name,
                    $match->awayTeam->name,
                    $match->kickoffAt->format('Y-m-d H:i'),
                ));
            }
        }

        return $adoption;
    }

    private function kickoffWithin(SportMatch $match, MatchSnapshot $snapshot, \DateInterval $tolerance): bool
    {
        $earliest = $snapshot->kickoffUtc->sub($tolerance);
        $latest = $snapshot->kickoffUtc->add($tolerance);

        return $match->kickoffAt >= $earliest && $match->kickoffAt <= $latest;
    }
}
