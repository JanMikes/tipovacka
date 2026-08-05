<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Repository\SportRepository;
use App\Repository\TeamRepository;
use App\Service\PragueCalendar;
use App\Value\CompetitionSourceSpec;
use App\Value\ScopeDraft;
use Symfony\Component\Uid\Uuid;

/**
 * Everything the „Zápasy soutěže" basket editor READS: which zdroje a user may
 * compose from, what is inside one, and what a basket would add up to.
 *
 * It exists so the create wizard and the post-creation manage screen ask the
 * same questions of the same service instead of each wiring four repositories —
 * see {@see \App\Twig\Components\Competition\ComposesMatchScope}, the trait both
 * components use.
 */
final readonly class MatchScopeCatalog
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private SportMatchRepository $sportMatchRepository,
        private SportRepository $sportRepository,
        private TeamRepository $teamRepository,
        private ScopeDraftResolver $scopeDraftResolver,
    ) {
    }

    /**
     * The zdroje this user may put in a basket: every active curated one, plus
     * their own active private ones unless the basket is being composed for a
     * GLOBAL competition (which must sit on curated zdroje only).
     *
     * @return list<MatchSource>
     */
    public function composableSources(User $user, bool $curatedOnly): array
    {
        $sources = array_values($this->matchSourceRepository->findActiveCurated());

        if ($curatedOnly) {
            return $sources;
        }

        foreach ($this->matchSourceRepository->findPrivateByOwner($user->id) as $private) {
            if ($private->isActive) {
                $sources[] = $private;
            }
        }

        return $sources;
    }

    /**
     * Matches of a zdroj that a basket may pick from — cancelled ones are not
     * offered — grouped by round (fallback: kickoff date, Prague), groups in
     * first-kickoff order, matches kickoff-ordered within.
     *
     * @return array<string, list<SportMatch>>
     */
    public function selectableMatchesByRound(MatchSource $matchSource): array
    {
        $groups = [];

        foreach ($this->sportMatchRepository->listByMatchSource($matchSource->id) as $match) {
            if ($match->isCancelled) {
                continue;
            }

            $group = $match->round ?? $match->kickoffAt
                ->setTimezone(PragueCalendar::timezone())
                ->format('j. n. Y');
            $groups[$group][] = $match;
        }

        return $groups;
    }

    /**
     * Everything a zdroj holds, kickoff-ordered — the rozpis view behind
     * „Vlastní zápasy", where a cancelled match still has to be visible.
     *
     * @return list<SportMatch>
     */
    public function matchesIn(MatchSource $matchSource): array
    {
        return $this->sportMatchRepository->listByMatchSource($matchSource->id);
    }

    /**
     * The pool the team-filter picker offers for a zdroj, and what a selection
     * is validated against.
     *
     * @return list<Team>
     */
    public function teamsIn(MatchSource $matchSource): array
    {
        return $this->teamRepository->listTeamsInSource($matchSource->id);
    }

    /** @return list<Sport> */
    public function sports(): array
    {
        return $this->sportRepository->listAll();
    }

    public function sport(Uuid $sportId): Sport
    {
        return $this->sportRepository->get($sportId);
    }

    public function findSport(Uuid $sportId): ?Sport
    {
        return $this->sportRepository->find($sportId);
    }

    public function matchSource(Uuid $matchSourceId): MatchSource
    {
        return $this->matchSourceRepository->get($matchSourceId);
    }

    /**
     * What a basket adds up to: the fixture list, its span, and any fixture
     * taken twice from different zdroje.
     *
     * @param list<CompetitionSourceSpec> $specs
     */
    public function resolveDraft(array $specs): ScopeDraft
    {
        return $this->scopeDraftResolver->resolve($specs);
    }
}
