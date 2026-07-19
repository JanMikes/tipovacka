<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionMatchSelection;

use App\Entity\CompetitionMatchSelection;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\MatchNotInCompetition;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCompetitionMatchSelectionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionMatchSelectionRepository $selectionRepository,
        private SportMatchRepository $sportMatchRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private GuessRepository $guessRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionMatchSelectionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);

        if (CompetitionMatchSelectionMode::Subset !== $competition->selectionMode) {
            throw new \DomainException('Výběr zápasů lze upravovat jen u soutěží s vybranými zápasy.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Validate the whole desired set BEFORE mutating anything: a Subset
        // competition must never end up empty, and only selectable matches of
        // the competition's own source (same filter the UI applies: not
        // deleted, not cancelled) may be selected. A crafted POST containing a
        // foreign / deleted / cancelled match id aborts the whole update.
        $wantedMatches = [];

        foreach ($command->selectedMatchIds as $sportMatchId) {
            $sportMatch = $this->sportMatchRepository->get($sportMatchId);

            if (!$sportMatch->matchSource->id->equals($competition->matchSource->id)
                || null !== $sportMatch->deletedAt
                || $sportMatch->isCancelled
            ) {
                throw MatchNotInCompetition::create();
            }

            $wantedMatches[$sportMatch->id->toRfc4122()] = $sportMatch;
        }

        if ([] === $wantedMatches) {
            throw new \DomainException('Vyberte prosím alespoň jeden zápas.');
        }

        $changed = false;

        foreach ($this->selectionRepository->listByCompetition($competition->id) as $existing) {
            $key = $existing->sportMatch->id->toRfc4122();

            if (isset($wantedMatches[$key])) {
                unset($wantedMatches[$key]);

                continue;
            }

            $this->selectionRepository->remove($existing);
            $changed = true;
        }

        foreach ($wantedMatches as $sportMatch) {
            // Férovost: zápas, který v této soutěži už nese aktivní tipy z dřívějšího
            // zařazení (byl odebrán a teď se přidává zpět), nesmí být považován za
            // „pozdě přidaný" — to by mu obnovilo uzávěrku a znovu otevřelo už
            // zveřejněné tipy. Zakotvíme jeho vstup k založení soutěže, aby ho řídilo
            // běžné uzamčení. Zápas bez tipů vstupuje teď.
            $addedAt = $this->guessRepository->hasActiveInCompetitionAndMatch($competition->id, $sportMatch->id)
                ? $competition->createdAt
                : $now;

            $this->selectionRepository->save(new CompetitionMatchSelection(
                id: $this->identity->next(),
                competition: $competition,
                sportMatch: $sportMatch,
                addedAt: $addedAt,
            ));
            $changed = true;
        }

        if ($changed) {
            $competition->recordMatchSelectionChanged($editor, $now);
            $this->matchProvider->forgetSelections($competition->id);
        }
    }
}
