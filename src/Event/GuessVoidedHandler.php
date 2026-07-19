<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessScorerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuessVoidedHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        private GuessScorerRepository $guessScorerRepository,
    ) {
    }

    public function __invoke(GuessVoided $event): void
    {
        // Same cleanup style for both satellites of a voided guess: the
        // evaluation and the scorer tips are hard-deleted, the guess itself
        // stays soft-deleted.
        $this->evaluationRepository->deleteByGuess($event->guessId);
        $this->guessScorerRepository->deleteByGuess($event->guessId);
    }
}
