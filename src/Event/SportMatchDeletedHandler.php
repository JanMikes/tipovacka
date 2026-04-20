<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\VoidGuessesForMatch\VoidGuessesForMatchCommand;
use App\Repository\GuessEvaluationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SportMatchDeletedHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(SportMatchDeleted $event): void
    {
        $this->evaluationRepository->deleteByMatch($event->sportMatchId);
        $this->commandBus->dispatch(new VoidGuessesForMatchCommand(
            sportMatchId: $event->sportMatchId,
        ));
    }
}
