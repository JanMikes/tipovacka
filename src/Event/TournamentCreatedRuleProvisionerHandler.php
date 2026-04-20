<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\TournamentRepository;
use App\Service\Scoring\TournamentRuleConfigurationProvisioner;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TournamentCreatedRuleProvisionerHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentRuleConfigurationProvisioner $provisioner,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TournamentCreated $event): void
    {
        $tournament = $this->tournamentRepository->find($event->tournamentId);

        if (null === $tournament) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $this->provisioner->provision($tournament, $now);
    }
}
