<?php

declare(strict_types=1);

namespace App\Command\CreateCompetition;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Repository\CompetitionRepository;
use App\Repository\MatchSourceRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\PinGenerator;
use App\Service\Competition\ShareableLinkTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private PinGenerator $pinGenerator,
        private ShareableLinkTokenGenerator $linkTokenGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateCompetitionCommand $command): Competition
    {
        $owner = $this->userRepository->get($command->ownerId);
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $competition = new Competition(
            id: $this->identity->next(),
            matchSource: $matchSource,
            owner: $owner,
            name: $command->name,
            description: $command->description,
            pin: $command->withPin ? $this->pinGenerator->generate() : null,
            shareableLinkToken: $this->linkTokenGenerator->generate(),
            createdAt: $now,
        );

        $this->competitionRepository->save($competition);

        $membership = new Membership(
            id: $this->identity->next(),
            competition: $competition,
            user: $owner,
            joinedAt: $now,
        );

        $this->membershipRepository->save($membership);

        return $competition;
    }
}
