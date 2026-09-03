<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Repository\CompetitionRepository;
use App\Repository\MatchSourceRepository;
use App\Service\Identity\ProvideIdentity;

/**
 * „Vlastní zápasy" — the private zdroj a soutěž keeps FOR ITSELF, and the single
 * answer to the question that makes in-competition match editing safe:
 *
 *   may this organizer add, change and delete matches from inside the soutěž
 *   without any other soutěž noticing?
 *
 * Yes for a zdroj that is (a) private, (b) owned by the competition's owner and
 * (c) drawn from by NO other competition. A curated zdroj is shared with the
 * whole app; a private zdroj that a second soutěž also draws from is shared with
 * that one — both are edited from the zdroj's own page, deliberately, never as a
 * side effect of editing a soutěž. See .docs/features/competition-scope.md.
 *
 * The exclusivity is DERIVED, not a schema invariant: reusing your own private
 * zdroj in a second soutěž stays legal (two partičky tipping the same custom
 * turnaj), and the moment it happens this service stops calling it „vlastní" and
 * the in-competition editor closes itself.
 */
final readonly class OwnMatchesSource
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MatchSourceRepository $matchSourceRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * The competition's own zdroj, or null when it draws only from zdroje it
     * shares with somebody.
     */
    public function of(Competition $competition): ?MatchSource
    {
        return $this->layerOf($competition)?->matchSource;
    }

    /** The layer feeding the competition from its own zdroj, if it has one. */
    public function layerOf(Competition $competition): ?CompetitionSource
    {
        foreach ($competition->sources as $layer) {
            if ($this->isOwnedExclusivelyBy($layer->matchSource, $competition)) {
                return $layer;
            }
        }

        return null;
    }

    public function isOwnedExclusivelyBy(MatchSource $matchSource, Competition $competition): bool
    {
        if (MatchSourceKind::Private !== $matchSource->kind) {
            return false;
        }

        if (!$matchSource->owner->id->equals($competition->owner->id)) {
            return false;
        }

        return 0 === $this->competitionRepository->countOtherCompetitionsUsingMatchSource(
            $matchSource->id,
            $competition->id,
        );
    }

    /**
     * A brand-new private zdroj for this soutěž. Its sport is the competition's
     * (every layer shares one) and its owner is the organizer, so the existing
     * zdroj/zápas voters already grant exactly the right person exactly the
     * right rights.
     */
    public function createFor(Competition $competition, \DateTimeImmutable $now): MatchSource
    {
        return $this->create($competition->headlineSource->sport, $competition->owner, $competition->name, $now);
    }

    /**
     * The same zdroj for a soutěž that does not exist yet — the create wizard's
     * „Vytvořit soutěž od začátku" / „Vlastní zápasy" path, which has the sport,
     * the owner and the name in hand before there is a Competition to ask.
     */
    public function create(Sport $sport, User $owner, string $competitionName, \DateTimeImmutable $now, bool $hasOvertime = false): MatchSource
    {
        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: $competitionName,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
            hasOvertime: $hasOvertime,
        );

        $this->matchSourceRepository->save($matchSource);

        return $matchSource;
    }
}
