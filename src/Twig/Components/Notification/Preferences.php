<?php

declare(strict_types=1);

namespace App\Twig\Components\Notification;

use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryCommand;
use App\Command\MuteCompetitionNotifications\MuteCompetitionNotificationsCommand;
use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailFrequency;
use App\Enum\NotificationCategory;
use App\Enum\NotificationType;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationSettingsRepository;
use App\Service\Notification\NotificationPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * „Nastavení oznámení" — three questions, from the broadest to the finest:
 *
 *  1. how often we may e-mail at all ({@see EmailFrequency} + the hour of the
 *     daily summary),
 *  2. which soutěže are silenced ({@see \App\Entity\Membership::$notificationsMuted}),
 *  3. which types go to which channel (the per-type × V aplikaci / E-mail matrix).
 *
 * Every control saves at once through the command bus and re-renders; missing
 * rows show the defaults. What each setting MEANS is decided in
 * {@see NotificationPolicy} — this component only writes them.
 */
#[AsLiveComponent(name: 'Notification:Preferences')]
final class Preferences
{
    use DefaultActionTrait;

    /** The hours offered for the daily summary (Europe/Prague). */
    public const array DIGEST_HOURS = [6, 8, 10, 12, 15, 18, 20];

    /**
     * What the last change saved, shown in place — the switch flipping alone does not
     * say whether it was stored (a re-render that failed would flip nothing, but a
     * slow one looks the same for a moment). Not writable: only the actions set it.
     */
    #[LiveProp]
    public ?string $savedMessage = null;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly NotificationSettingsRepository $settingsRepository,
        private readonly MembershipRepository $membershipRepository,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public EmailFrequency $frequency {
        get => NotificationPolicy::frequencyOf($this->settings());
    }

    public int $digestHour {
        get => NotificationPolicy::digestHourOf($this->settings());
    }

    /** @var list<EmailFrequency> */
    public array $frequencies {
        get => EmailFrequency::cases();
    }

    /**
     * The offered hours, plus the player's own when it is not one of them (it can
     * only have been set elsewhere, but it must still show as the selected one).
     *
     * @var list<int>
     */
    public array $digestHours {
        get {
            $hours = self::DIGEST_HOURS;

            if (!in_array($this->digestHour, $hours, true)) {
                $hours[] = $this->digestHour;
                sort($hours);
            }

            return $hours;
        }
    }

    /**
     * The player's soutěže with their mute state, newest membership first.
     *
     * @var list<array{id: string, name: string, muted: bool}>
     */
    public array $competitions {
        get {
            $user = $this->currentUser();

            if (null === $user) {
                return [];
            }

            return array_map(
                static fn ($membership): array => [
                    'id' => $membership->competition->id->toRfc4122(),
                    'name' => $membership->competition->name,
                    'muted' => $membership->notificationsMuted,
                ],
                $this->membershipRepository->findMyActive($user->id),
            );
        }
    }

    /**
     * The matrix, one group per {@see NotificationCategory}.
     *
     * @var list<array{category: NotificationCategory, rows: list<array{type: NotificationType, inApp: bool, email: bool}>}>
     */
    public array $groups {
        get {
            $user = $this->currentUser();
            $map = null !== $user ? $this->preferenceRepository->mapForUser($user->id) : [];
            $groups = [];

            foreach (NotificationCategory::cases() as $category) {
                $rows = [];

                foreach ($category->types() as $type) {
                    $preference = $map[$type->value] ?? null;

                    $rows[] = [
                        'type' => $type,
                        'inApp' => null !== $preference ? $preference->inApp : $type->defaultInApp(),
                        'email' => null !== $preference ? $preference->email : $type->defaultEmail(),
                    ];
                }

                $groups[] = ['category' => $category, 'rows' => $rows];
            }

            return $groups;
        }
    }

    #[LiveAction]
    public function setFrequency(#[LiveArg] string $frequency): void
    {
        $user = $this->currentUser();
        $chosen = EmailFrequency::tryFrom($frequency);

        if (null === $user || null === $chosen) {
            return;
        }

        $this->commandBus->dispatch(new ChangeEmailDeliveryCommand($user->id, $chosen));

        $this->savedMessage = sprintf('Uloženo — e-maily: %s.', mb_strtolower($chosen->label()));
    }

    #[LiveAction]
    public function setDigestHour(#[LiveArg] int $hour): void
    {
        $user = $this->currentUser();

        if (null === $user || $hour < 0 || $hour > 23) {
            return;
        }

        $this->commandBus->dispatch(new ChangeEmailDeliveryCommand($user->id, $this->frequency, $hour));

        $this->savedMessage = sprintf('Uloženo — denní souhrn po %d:00.', $hour);
    }

    #[LiveAction]
    public function toggleMute(#[LiveArg] string $competition): void
    {
        $user = $this->currentUser();

        if (null === $user || !Uuid::isValid($competition)) {
            return;
        }

        $competitionId = Uuid::fromString($competition);
        $membership = $this->membershipRepository->findActiveMembership($user->id, $competitionId);

        if (null === $membership) {
            return;
        }

        $muted = !$membership->notificationsMuted;

        $this->commandBus->dispatch(new MuteCompetitionNotificationsCommand($user->id, $competitionId, $muted));

        $this->savedMessage = sprintf(
            'Uloženo — soutěž %s: %s.',
            $membership->competition->name,
            $muted ? 'ztlumena' : 'oznámení zapnuta',
        );
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $type, #[LiveArg] string $channel): void
    {
        $user = $this->currentUser();

        if (null === $user) {
            return;
        }

        $notificationType = NotificationType::tryFrom($type);

        if (null === $notificationType) {
            return;
        }

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

        $this->savedMessage = sprintf(
            'Uloženo — %s %s: %s.',
            $notificationType->label(),
            'inApp' === $channel ? 'v aplikaci' : 'e-mailem',
            ('inApp' === $channel ? $inApp : $email) ? 'zapnuto' : 'vypnuto',
        );
    }

    private function settings(): ?NotificationSettings
    {
        $user = $this->currentUser();

        return null !== $user ? $this->settingsRepository->findForUser($user->id) : null;
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
