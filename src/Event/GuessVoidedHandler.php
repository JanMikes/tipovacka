<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\GuessEvaluationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuessVoidedHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
    ) {
    }

    public function __invoke(GuessVoided $event): void
    {
        $this->evaluationRepository->deleteByGuess($event->guessId);
    }
}
