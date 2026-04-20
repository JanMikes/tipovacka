<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\RecalculateTournamentPoints\RecalculateTournamentPointsCommand;
use App\Repository\GuessEvaluationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class TournamentRulesChangedAsyncHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(TournamentRulesChanged $event): void
    {
        if (0 === $this->evaluationRepository->countForTournament($event->tournamentId)) {
            return;
        }

        $this->commandBus->dispatch(new RecalculateTournamentPointsCommand(
            tournamentId: $event->tournamentId,
        ));
    }
}
