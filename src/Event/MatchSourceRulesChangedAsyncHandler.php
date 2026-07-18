<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\RecalculateMatchSourcePoints\RecalculateMatchSourcePointsCommand;
use App\Repository\GuessEvaluationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class MatchSourceRulesChangedAsyncHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(MatchSourceRulesChanged $event): void
    {
        if (0 === $this->evaluationRepository->countForMatchSource($event->matchSourceId)) {
            return;
        }

        $this->commandBus->dispatch(new RecalculateMatchSourcePointsCommand(
            matchSourceId: $event->matchSourceId,
        ));
    }
}
