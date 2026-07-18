<?php

declare(strict_types=1);

namespace App\Command\RegenerateCompetitionPin;

use App\Repository\CompetitionRepository;
use App\Service\Competition\PinGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateCompetitionPinHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private PinGenerator $pinGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegenerateCompetitionPinCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->setPin(
            $this->pinGenerator->generate(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
