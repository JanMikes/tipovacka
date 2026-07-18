<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\Repository\GuessEvaluationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CompetitionRulesChangedAsyncHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(CompetitionRulesChanged $event): void
    {
        if (0 === $this->evaluationRepository->countForCompetition($event->competitionId)) {
            return;
        }

        $this->commandBus->dispatch(new RecalculateCompetitionPointsCommand(
            competitionId: $event->competitionId,
        ));
    }
}
