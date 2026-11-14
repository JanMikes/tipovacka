<?php

declare(strict_types=1);

namespace App\Command\PruneEmailLog;

use App\Entity\EmailLog;
use App\Repository\EmailLogRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PruneEmailLogHandler
{
    public function __construct(
        private EmailLogRepository $emailLogRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PruneEmailLogCommand $command): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->emailLogRepository->deleteOlderThan($now->modify(sprintf('-%d days', EmailLog::RETENTION_DAYS)));
    }
}
