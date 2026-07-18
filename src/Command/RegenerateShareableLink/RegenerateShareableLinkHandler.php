<?php

declare(strict_types=1);

namespace App\Command\RegenerateShareableLink;

use App\Repository\CompetitionRepository;
use App\Service\Competition\ShareableLinkTokenGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateShareableLinkHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ShareableLinkTokenGenerator $tokenGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegenerateShareableLinkCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->setShareableLinkToken(
            $this->tokenGenerator->generate(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
