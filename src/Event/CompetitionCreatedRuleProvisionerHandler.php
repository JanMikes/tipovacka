<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\CompetitionRepository;
use App\Service\Scoring\CompetitionRuleConfigurationProvisioner;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CompetitionCreatedRuleProvisionerHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionRuleConfigurationProvisioner $provisioner,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CompetitionCreated $event): void
    {
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $competition) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $this->provisioner->provision($competition, $now);
    }
}
