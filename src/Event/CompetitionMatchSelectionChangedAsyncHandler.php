<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CompetitionMatchSelectionChangedAsyncHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        private GuessRepository $guessRepository,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(CompetitionMatchSelectionChanged $event): void
    {
        // A selection change can invalidate existing evaluations (a deselected
        // match's points must stop counting) OR make new ones necessary (a
        // re-added finished match's guesses must be evaluated again after a
        // recalc purged them). Recalculate when either side has work to do;
        // stay silent for guessless competitions.
        if (0 === $this->evaluationRepository->countForCompetition($event->competitionId)
            && 0 === $this->guessRepository->countActiveForFinishedMatchesInCompetition($event->competitionId)
        ) {
            return;
        }

        $this->commandBus->dispatch(new RecalculateCompetitionPointsCommand(
            competitionId: $event->competitionId,
        ));
    }
}
