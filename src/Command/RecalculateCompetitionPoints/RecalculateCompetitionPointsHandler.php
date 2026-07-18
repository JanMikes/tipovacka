<?php

declare(strict_types=1);

namespace App\Command\RecalculateCompetitionPoints;

use App\Event\CompetitionPointsRecalculated;
use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessRepository;
use App\Service\Scoring\GuessEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RecalculateCompetitionPointsHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        private GuessRepository $guessRepository,
        private GuessEvaluator $evaluator,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RecalculateCompetitionPointsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // The rules very likely just changed — drop any cached config for this
        // competition so the evaluator re-reads the fresh rows.
        $this->evaluator->forgetCompetition($command->competitionId);

        // Flush deletions before inserting new evaluations to avoid unique-constraint
        // collisions on (guess_id) when the recalc replaces an existing row.
        $this->evaluationRepository->deleteAllForCompetition($command->competitionId);
        $this->entityManager->flush();

        $guesses = $this->guessRepository->findActiveForFinishedMatchesInCompetition($command->competitionId);

        foreach ($guesses as $guess) {
            $evaluation = $this->evaluator->evaluate($guess, $guess->sportMatch, $now);

            if (null === $evaluation) {
                continue;
            }

            $this->evaluationRepository->save($evaluation);
        }

        $this->eventBus->dispatch(new CompetitionPointsRecalculated(
            competitionId: $command->competitionId,
            occurredOn: $now,
        ));
    }
}
