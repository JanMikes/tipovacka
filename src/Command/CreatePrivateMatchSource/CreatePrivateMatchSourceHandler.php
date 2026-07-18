<?php

declare(strict_types=1);

namespace App\Command\CreatePrivateMatchSource;

use App\Entity\MatchSource;
use App\Enum\MatchSourceVisibility;
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
        $football = $this->sportRepository->getByCode('football');
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $football,
            owner: $owner,
            visibility: MatchSourceVisibility::Private,
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            createdAt: $now,
            creationPin: $command->creationPin,
        );

        $this->matchSourceRepository->save($matchSource);

        return $matchSource;
    }
}
