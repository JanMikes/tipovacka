<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\SportMatch;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\MatchNotInCompetition;
use App\Exception\TeamNotInSource;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Team\TeamResolver;
use Symfony\Component\Uid\Uuid;

/**
 * The single home of „what one scope layer's rows mean" on the WRITE side:
 * a Subset layer's hand-picked matches, a Teams layer's filter teams, and
 * throwing both away when the layer changes mode or leaves the soutěž.
 *
 * Three write paths share it — the per-layer screens
 * ({@see \App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionHandler},
 * {@see \App\Command\UpdateCompetitionTeamFilter\UpdateCompetitionTeamFilterHandler})
 * and the whole-basket one
 * ({@see \App\Command\UpdateCompetitionScope\UpdateCompetitionScopeHandler}) —
 * so the validation („only this zdroj's live matches", „only teams in this
 * zdroj's scope", „never end up empty") and the fairness anchoring below are
 * written down once.
 *
 * Every method validates the WHOLE desired set before mutating anything, so a
 * crafted request aborts the transaction instead of half-applying.
 */
final readonly class ScopeLayerWriter
{
    public function __construct(
        private CompetitionMatchSelectionRepository $selectionRepository,
        private CompetitionTeamFilterRepository $teamFilterRepository,
        private SportMatchRepository $sportMatchRepository,
        private TeamRepository $teamRepository,
        private TeamResolver $teamResolver,
        private GuessRepository $guessRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * Makes the layer's hand-picked matches exactly $selectedMatchIds.
     *
     * @param list<Uuid>          $selectedMatchIds
     * @param array<string, true> $establishedMatchIds match UUIDs (RFC 4122) that were ALREADY in the
     *                                                 competition's scope before this edit began — see
     *                                                 {@see anchorFor}
     *
     * @return bool whether anything actually changed
     */
    public function replaceSelections(
        CompetitionSource $layer,
        array $selectedMatchIds,
        \DateTimeImmutable $now,
        array $establishedMatchIds = [],
    ): bool {
        $competition = $layer->competition;
        $wanted = [];

        foreach ($selectedMatchIds as $sportMatchId) {
            $sportMatch = $this->sportMatchRepository->get($sportMatchId);

            // Only selectable matches of THIS layer's zdroj (same filter the UI
            // applies: not deleted, not cancelled) may be picked.
            if (!$sportMatch->matchSource->id->equals($layer->matchSource->id)
                || null !== $sportMatch->deletedAt
                || $sportMatch->isCancelled
            ) {
                throw MatchNotInCompetition::create();
            }

            $wanted[$sportMatch->id->toRfc4122()] = $sportMatch;
        }

        if ([] === $wanted) {
            throw new \DomainException('Vyberte prosím alespoň jeden zápas.');
        }

        $changed = false;

        foreach ($this->selectionRepository->listByLayer($layer->id) as $existing) {
            $key = $existing->sportMatch->id->toRfc4122();

            if (isset($wanted[$key])) {
                unset($wanted[$key]);

                continue;
            }

            $this->selectionRepository->remove($existing);
            $changed = true;
        }

        foreach ($wanted as $sportMatch) {
            $this->selectionRepository->save(new CompetitionMatchSelection(
                id: $this->identity->next(),
                competition: $competition,
                competitionSource: $layer,
                sportMatch: $sportMatch,
                addedAt: $this->anchorFor($layer, $sportMatch, $establishedMatchIds, $now),
            ));
            $changed = true;
        }

        return $changed;
    }

    /**
     * Makes the layer's filter teams exactly $teamIds. Duplicate ids collapse.
     *
     * @param list<Uuid> $teamIds
     *
     * @return bool whether anything actually changed
     */
    public function replaceTeamFilters(CompetitionSource $layer, array $teamIds, \DateTimeImmutable $now): bool
    {
        $matchSource = $layer->matchSource;
        $wanted = [];

        foreach ($teamIds as $teamId) {
            $team = $this->teamRepository->get($teamId);

            // Same hybrid rule as team resolution: a global directory team for a
            // curated zdroj, a local one for a private zdroj. Anything else is
            // someone else's team.
            if (!$this->teamResolver->belongsToSourceScope($matchSource, $team)) {
                throw TeamNotInSource::create($teamId, $matchSource->id);
            }

            $wanted[$team->id->toRfc4122()] = $team;
        }

        if ([] === $wanted) {
            throw new \DomainException('Vyberte prosím alespoň jeden tým.');
        }

        $changed = false;

        foreach ($this->teamFilterRepository->listByLayer($layer->id) as $existing) {
            $key = $existing->team->id->toRfc4122();

            if (isset($wanted[$key])) {
                unset($wanted[$key]);

                continue;
            }

            $this->teamFilterRepository->remove($existing);
            $changed = true;
        }

        foreach ($wanted as $team) {
            $this->teamFilterRepository->save(new CompetitionTeamFilter(
                id: $this->identity->next(),
                competition: $layer->competition,
                competitionSource: $layer,
                team: $team,
                addedAt: $now,
            ));
            $changed = true;
        }

        return $changed;
    }

    /**
     * Drops every row hanging off the layer — used when it switches to a mode
     * that has none („Všechny zápasy"), swaps Subset ↔ Teams, or leaves the
     * competition altogether (the rows carry a NOT NULL FK to it).
     *
     * @return bool whether anything actually changed
     */
    public function clearRows(CompetitionSource $layer): bool
    {
        $clearedSelections = $this->clearSelections($layer);

        return $this->clearTeamFilters($layer) || $clearedSelections;
    }

    /**
     * Writes whatever the layer's CURRENT mode needs and throws away what the
     * mode it LEFT had left behind — the whole-basket path's per-layer step.
     * Switching Subset ↔ Teams without this would leave the old rows lying
     * around, and the provider reads rows by layer, not by mode.
     *
     * @param list<Uuid>          $selectedMatchIds
     * @param list<Uuid>          $filterTeamIds
     * @param array<string, true> $establishedMatchIds see {@see replaceSelections}
     */
    public function writeRowsForMode(
        CompetitionSource $layer,
        array $selectedMatchIds,
        array $filterTeamIds,
        \DateTimeImmutable $now,
        array $establishedMatchIds = [],
    ): bool {
        // The cleanup of the mode the layer just LEFT is assigned first, so `||`
        // can never short-circuit past it.
        switch ($layer->selectionMode) {
            case CompetitionMatchSelectionMode::Subset:
                $written = $this->replaceSelections($layer, $selectedMatchIds, $now, $establishedMatchIds);

                return $this->clearTeamFilters($layer) || $written;
            case CompetitionMatchSelectionMode::Teams:
                $written = $this->replaceTeamFilters($layer, $filterTeamIds, $now);

                return $this->clearSelections($layer) || $written;
            case CompetitionMatchSelectionMode::All:
                return $this->clearRows($layer);
        }
    }

    /**
     * Férovost: kdy zápas do soutěže „vstupuje". „Pozdě přidaný" zápas dostává
     * vlastní uzávěrku ({@see \App\Service\EffectiveTipDeadlineResolver}), což je
     * správné jen pro zápas, který v soutěži DOSUD nebyl. Zápas, který v ní už
     * jednou byl — nese aktivní tipy, nebo prostě patřil do rozsahu, než se
     * začalo upravovat — se zakotví k založení soutěže, aby ho dál řídilo běžné
     * uzamčení; jinak by přepnutí režimu vrstvy znovu otevřelo už uzavřené tipy.
     *
     * @param array<string, true> $establishedMatchIds
     */
    private function anchorFor(
        CompetitionSource $layer,
        SportMatch $sportMatch,
        array $establishedMatchIds,
        \DateTimeImmutable $now,
    ): \DateTimeImmutable {
        if (isset($establishedMatchIds[$sportMatch->id->toRfc4122()])) {
            return $layer->competition->createdAt;
        }

        if ($this->guessRepository->hasActiveInCompetitionAndMatch($layer->competition->id, $sportMatch->id)) {
            return $layer->competition->createdAt;
        }

        return $now;
    }

    private function clearSelections(CompetitionSource $layer): bool
    {
        $changed = false;

        foreach ($this->selectionRepository->listByLayer($layer->id) as $selection) {
            $this->selectionRepository->remove($selection);
            $changed = true;
        }

        return $changed;
    }

    private function clearTeamFilters(CompetitionSource $layer): bool
    {
        $changed = false;

        foreach ($this->teamFilterRepository->listByLayer($layer->id) as $filter) {
            $this->teamFilterRepository->remove($filter);
            $changed = true;
        }

        return $changed;
    }
}
