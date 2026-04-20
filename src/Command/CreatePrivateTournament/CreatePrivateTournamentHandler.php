<?php

declare(strict_types=1);

namespace App\Command\CreatePrivateTournament;

use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Repository\SportRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePrivateTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private UserRepository $userRepository,
        private SportRepository $sportRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreatePrivateTournamentCommand $command): Tournament
    {
        $owner = $this->userRepository->get($command->ownerId);
        $football = $this->sportRepository->getByCode('football');
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $tournament = new Tournament(
            id: $this->identity->next(),
            sport: $football,
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            createdAt: $now,
            creationPin: $command->creationPin,
        );

        $this->tournamentRepository->save($tournament);

        return $tournament;
    }
}
