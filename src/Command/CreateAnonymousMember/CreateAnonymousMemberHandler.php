<?php

declare(strict_types=1);

namespace App\Command\CreateAnonymousMember;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\NicknameAlreadyTaken;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class CreateAnonymousMemberHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateAnonymousMemberCommand $command): User
    {
        $group = $this->groupRepository->get($command->groupId);
        $actor = $this->userRepository->get($command->actorId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actor->getRoles(), true);

        if (!$isAdmin && !$actor->id->equals($group->owner->id)) {
            throw new AccessDeniedException('Pouze vlastník skupiny nebo administrátor může přidávat tipující bez e-mailu.');
        }

        $nickname = null !== $command->nickname && '' !== trim($command->nickname)
            ? trim($command->nickname)
            : null;

        if (null !== $nickname && null !== $this->userRepository->findByNickname($nickname)) {
            throw NicknameAlreadyTaken::withNickname($nickname);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $user = new User(
            id: $this->identity->next(),
            email: null,
            password: null,
            nickname: $nickname,
            createdAt: $now,
        );

        $user->updateProfile(
            firstName: $command->firstName,
            lastName: $command->lastName,
            phone: null,
            now: $now,
        );

        $this->userRepository->save($user);

        $this->membershipRepository->save(new Membership(
            id: $this->identity->next(),
            group: $group,
            user: $user,
            joinedAt: $now,
        ));

        return $user;
    }
}
