<?php

declare(strict_types=1);

namespace App\Command\UpdateMatchSource;

use App\Exception\MatchSourceAlreadyFinished;
use App\Repository\MatchSourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateMatchSourceCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);

        if ($matchSource->isFinished) {
            throw MatchSourceAlreadyFinished::withId($matchSource->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource->updateDetails(
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            now: $now,
        );
    }
}
