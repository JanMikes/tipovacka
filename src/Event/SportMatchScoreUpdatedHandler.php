<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Service\Scoring\GuessEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SportMatchScoreUpdatedHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private GuessRepository $guessRepository,
        private GuessEvaluationRepository $evaluationRepository,
        private GuessEvaluator $evaluator,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SportMatchScoreUpdated $event): void
    {
        $match = $this->sportMatchRepository->find($event->sportMatchId);

        if (null === $match || !$match->isFinished) {
            return;
        }

        // Drop existing evaluations (flush so unique-constraint on guess_id is free
        // before we insert replacement rows).
        $this->evaluationRepository->deleteByMatch($event->sportMatchId);
        $this->entityManager->flush();

        $guesses = $this->guessRepository->findActiveByMatch($event->sportMatchId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($guesses as $guess) {
            $evaluation = $this->evaluator->evaluate($guess, $match, $now);

            if (null === $evaluation) {
                continue;
            }

            $this->evaluationRepository->save($evaluation);
        }
    }
}
