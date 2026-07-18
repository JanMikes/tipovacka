<?php

declare(strict_types=1);

namespace App\Command\CreateAnonymousMember;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\NicknameAlreadyTaken;
use App\Repository\CompetitionRepository;
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
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateAnonymousMemberCommand $command): User
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $actor = $this->userRepository->get($command->actorId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actor->getRoles(), true);

        if (!$isAdmin && !$actor->id->equals($competition->owner->id)) {
            throw new AccessDeniedException('Pouze vlastník soutěže nebo administrátor může přidávat tipující bez e-mailu.');
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
            competition: $competition,
            user: $user,
            joinedAt: $now,
        ));

        return $user;
    }
}
