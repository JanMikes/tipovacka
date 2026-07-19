<?php

declare(strict_types=1);

namespace App\Twig\Components\Notification;

use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The per-type × (V aplikaci / E-mail) preference matrix. Each toggle instantly
 * upserts a {@see \App\Entity\NotificationPreference} through the command bus and
 * re-renders. Missing rows show the type's defaults.
 */
#[AsLiveComponent(name: 'Notification:Preferences')]
final class Preferences
{
    use DefaultActionTrait;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /** @var list<array{type: NotificationType, inApp: bool, email: bool}> */
    public array $rows {
        get {
            $user = $this->currentUser();
            $map = null !== $user ? $this->preferenceRepository->mapForUser($user->id) : [];

            $rows = [];

            foreach (NotificationType::cases() as $type) {
                $preference = $map[$type->value] ?? null;

                $rows[] = [
                    'type' => $type,
                    'inApp' => null !== $preference ? $preference->inApp : $type->defaultInApp(),
                    'email' => null !== $preference ? $preference->email : $type->defaultEmail(),
                ];
            }

            return $rows;
        }
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $type, #[LiveArg] string $channel): void
    {
        $user = $this->currentUser();

        if (null === $user) {
            return;
        }

        $notificationType = NotificationType::from($type);
        $preference = $this->preferenceRepository->findOne($user->id, $notificationType);

        $inApp = null !== $preference ? $preference->inApp : $notificationType->defaultInApp();
        $email = null !== $preference ? $preference->email : $notificationType->defaultEmail();

        if ('inApp' === $channel) {
            $inApp = !$inApp;
        } elseif ('email' === $channel) {
            $email = !$email;
        } else {
            return;
        }

        $this->commandBus->dispatch(new SetNotificationPreferenceCommand(
            userId: $user->id,
            type: $notificationType,
            inApp: $inApp,
            email: $email,
        ));
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
