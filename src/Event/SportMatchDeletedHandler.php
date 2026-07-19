<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\VoidGuessesForMatch\VoidGuessesForMatchCommand;
use App\Repository\CompetitionRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SportMatchDeletedHandler
{
    public function __construct(
        private GuessEvaluationRepository $evaluationRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionRepository $competitionRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
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

        // Deleting the match that defined a competition's automatic lock moment
        // would move the live first-kickoff forward and reopen already closed
        // tips — pin the reached moment instead. The soft-deleted match is still
        // loadable (find() applies no deletedAt filter) and its kickoff is
        // unchanged by the delete, so it doubles as the PRE-change kickoff.
        $match = $this->sportMatchRepository->get($event->sportMatchId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findByMatchSource($event->matchSourceId) as $competition) {
            $pinAt = $this->deadlineResolver->lockMomentToPinAfterDefiningMatchLeft(
                $competition,
                $match,
                $match->kickoffAt,
                $now,
            );

            if (null !== $pinAt) {
                $competition->pinTipsLockMoment($pinAt, $now);
            }

            $this->deadlineResolver->forgetCompetition($competition->id);
        }
    }
}
