<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionTeamFilter;

use App\Entity\CompetitionTeamFilter;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\TeamNotInSource;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Identity\ProvideIdentity;
use App\Service\Team\TeamResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCompetitionTeamFilterHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionTeamFilterRepository $teamFilterRepository,
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private TeamResolver $teamResolver,
        private CompetitionMatchProvider $matchProvider,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionTeamFilterCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);

        // TODO(C3): take the layer id from the command so a multi-source
        // competition can edit each of its team-filter layers separately.
        $layer = $competition->sources[0] ?? throw new \DomainException('Soutěž nemá žádný zdroj zápasů.');

        if (CompetitionMatchSelectionMode::Teams !== $layer->selectionMode) {
            throw new \DomainException('Filtr týmů lze upravovat jen u soutěží se zápasy vybraných týmů.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Validate the whole desired set BEFORE mutating anything: a team filter
        // must never end up empty, and every team must belong to the source's
        // resolution scope (same guard as creation). A crafted POST with a
        // foreign / cross-sport team id aborts the whole update.
        $wanted = [];

        foreach ($command->teamIds as $teamId) {
            $team = $this->teamRepository->get($teamId);

            if (!$this->teamResolver->belongsToSourceScope($layer->matchSource, $team)) {
                throw TeamNotInSource::create($teamId, $layer->matchSource->id);
            }

            $wanted[$team->id->toRfc4122()] = $team;
        }

        if ([] === $wanted) {
            throw new \DomainException('Vyberte prosím alespoň jeden tým.');
        }

        $changed = false;

        foreach ($this->teamFilterRepository->listByCompetition($competition->id) as $existing) {
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
                competition: $competition,
                competitionSource: $layer,
                team: $team,
                addedAt: $now,
            ));
            $changed = true;
        }

        if ($changed) {
            $competition->recordMatchSelectionChanged($editor, $now);
            $this->matchProvider->forgetTeamFilters($competition->id);
        }
    }
}
