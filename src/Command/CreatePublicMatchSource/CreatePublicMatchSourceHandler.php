<?php

declare(strict_types=1);

namespace App\Command\CreatePublicMatchSource;

use App\Entity\MatchSource;
use App\Enum\MatchSourceVisibility;
use App\Repository\MatchSourceRepository;
use App\Repository\SportRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePublicMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private SportRepository $sportRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreatePublicMatchSourceCommand $command): MatchSource
    {
        $admin = $this->userRepository->get($command->adminId);
        $football = $this->sportRepository->getByCode('football');
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $football,
            owner: $admin,
            visibility: MatchSourceVisibility::Public,
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
