<?php

declare(strict_types=1);

namespace App\Command\CreatePrivateMatchSource;

use App\Entity\MatchSource;
use App\Enum\MatchSourceKind;
use App\Repository\MatchSourceRepository;
use App\Repository\SportRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePrivateMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private SportRepository $sportRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreatePrivateMatchSourceCommand $command): MatchSource
    {
        $owner = $this->userRepository->get($command->ownerId);
        $sport = $this->sportRepository->get($command->sportId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            createdAt: $now,
        );

        $this->matchSourceRepository->save($matchSource);

        return $matchSource;
    }
}
