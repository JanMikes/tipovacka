<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\MatchSourceRepository;
use App\Service\Scoring\MatchSourceRuleConfigurationProvisioner;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MatchSourceCreatedRuleProvisionerHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private MatchSourceRuleConfigurationProvisioner $provisioner,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MatchSourceCreated $event): void
    {
        $matchSource = $this->matchSourceRepository->find($event->matchSourceId);

        if (null === $matchSource) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $this->provisioner->provision($matchSource, $now);
    }
}
