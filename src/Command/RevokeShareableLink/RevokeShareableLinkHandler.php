<?php

declare(strict_types=1);

namespace App\Command\RevokeShareableLink;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeShareableLinkHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeShareableLinkCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->revokeShareableLinkToken(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
