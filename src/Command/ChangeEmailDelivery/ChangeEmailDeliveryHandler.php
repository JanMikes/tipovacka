<?php

declare(strict_types=1);

namespace App\Command\ChangeEmailDelivery;

use App\Entity\NotificationSettings;
use App\Repository\NotificationSettingsRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Notification\NotificationPolicy;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeEmailDeliveryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationSettingsRepository $settingsRepository,
        private ProvideIdentity $identityProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeEmailDeliveryCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $settings = $this->settingsRepository->findForUser($command->userId);
        $digestHour = $command->digestHour ?? NotificationPolicy::digestHourOf($settings);

        if (null !== $settings) {
            $settings->changeEmailDelivery($command->frequency, $digestHour, $now);

            return;
        }

        $this->settingsRepository->save(new NotificationSettings(
            id: $this->identityProvider->next(),
            user: $this->userRepository->get($command->userId),
            emailFrequency: $command->frequency,
            digestHour: $digestHour,
            createdAt: $now,
        ));
    }
}
