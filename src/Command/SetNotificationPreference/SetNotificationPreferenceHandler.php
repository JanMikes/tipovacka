<?php

declare(strict_types=1);

namespace App\Command\SetNotificationPreference;

use App\Entity\NotificationPreference;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetNotificationPreferenceHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationPreferenceRepository $preferenceRepository,
        private ProvideIdentity $identityProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetNotificationPreferenceCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $preference = $this->preferenceRepository->findOne($command->userId, $command->type);

        if (null !== $preference) {
            $preference->change($command->inApp, $command->email, $now);

            return;
        }

        $this->preferenceRepository->save(new NotificationPreference(
            id: $this->identityProvider->next(),
            user: $this->userRepository->get($command->userId),
            type: $command->type,
            inApp: $command->inApp,
            email: $command->email,
            createdAt: $now,
        ));
    }
}
