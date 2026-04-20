<?php

declare(strict_types=1);

namespace App\Command\ResolveLeaderboardTies;

use App\Entity\LeaderboardTieResolution;
use App\Enum\UserRole;
use App\Event\LeaderboardTiesResolved;
use App\Exception\LeaderboardTieResolutionInvalid;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Repository\LeaderboardTieResolutionRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class ResolveLeaderboardTiesHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private LeaderboardTieResolutionRepository $resolutionRepository,
        private QueryBus $queryBus,
        private EntityManagerInterface $entityManager,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ResolveLeaderboardTiesCommand $command): void
    {
        if (count($command->orderedUserIds) < 2) {
            throw LeaderboardTieResolutionInvalid::notTied();
        }

        $group = $this->groupRepository->get($command->groupId);
        $resolver = $this->userRepository->get($command->resolverId);

        $isAdmin = in_array(UserRole::ADMIN->value, $resolver->getRoles(), true);
        $isOwner = $resolver->id->equals($group->owner->id);

        if (!$isAdmin && !$isOwner) {
            throw new AccessDeniedException('Pouze vlastník skupiny nebo administrátor může rozřazení uložit.');
        }

        $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $group->id));

        $pointsByUser = [];

        foreach ($leaderboard->rows as $row) {
            $pointsByUser[$row->userId->toRfc4122()] = $row->totalPoints;
        }

        $firstKey = $command->orderedUserIds[0]->toRfc4122();

        if (!isset($pointsByUser[$firstKey])) {
            throw LeaderboardTieResolutionInvalid::notTied();
        }

        $tiedPoints = $pointsByUser[$firstKey];

        foreach ($command->orderedUserIds as $userId) {
            $key = $userId->toRfc4122();

            if (!isset($pointsByUser[$key]) || $pointsByUser[$key] !== $tiedPoints) {
                throw LeaderboardTieResolutionInvalid::notTied();
            }
        }

        $baseRank = 1;

        foreach ($pointsByUser as $points) {
            if ($points > $tiedPoints) {
                ++$baseRank;
            }
        }

        $this->resolutionRepository->deleteForGroupAndUsers($group->id, $command->orderedUserIds);
        $this->entityManager->flush();

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($command->orderedUserIds as $index => $userId) {
            $user = $this->userRepository->get($userId);

            $resolution = new LeaderboardTieResolution(
                id: $this->identity->next(),
                group: $group,
                user: $user,
                rank: $baseRank + $index,
                resolvedAt: $now,
                resolvedBy: $resolver,
            );

            $this->resolutionRepository->save($resolution);
        }

        $this->eventBus->dispatch(new LeaderboardTiesResolved(
            groupId: $group->id,
            occurredOn: $now,
        ));
    }
}
