<?php

declare(strict_types=1);

namespace App\Command\CreateCuratedMatchSource;

use App\Entity\MatchSource;
use App\Enum\MatchSourceKind;
use App\Repository\MatchSourceRepository;
use App\Repository\SportRepository;
use App\Repository\UserRepository;
use App\Service\Competition\GlobalCompetitionComposer;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCuratedMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private SportRepository $sportRepository,
        private GlobalCompetitionComposer $globalCompetitionComposer,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateCuratedMatchSourceCommand $command): MatchSource
    {
        $admin = $this->userRepository->get($command->adminId);
        $sport = $this->sportRepository->get($command->sportId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $sport,
            owner: $admin,
            kind: MatchSourceKind::Curated,
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            createdAt: $now,
        );

        $this->matchSourceRepository->save($matchSource);

        // Optional one-shot: also stand up a global competition over the new source
        // in the same transaction (both commit together or roll back together).
        if ($command->createGlobalCompetition) {
            $name = null !== $command->globalCompetitionName && '' !== trim($command->globalCompetitionName)
                ? $command->globalCompetitionName
                : $command->name;

            $this->globalCompetitionComposer->compose(
                matchSource: $matchSource,
                admin: $admin,
                name: $name,
                entryFeeCredits: $command->globalCompetitionEntryFee,
                monetization: $command->globalCompetitionMonetization,
                ruleChanges: [],
                now: $now,
            );
        }

        return $matchSource;
    }
}
