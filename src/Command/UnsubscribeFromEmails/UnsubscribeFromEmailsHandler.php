<?php

declare(strict_types=1);

namespace App\Command\UnsubscribeFromEmails;

use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryCommand;
use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryHandler;
use App\Command\MuteCompetitionNotifications\MuteCompetitionNotificationsCommand;
use App\Command\MuteCompetitionNotifications\MuteCompetitionNotificationsHandler;
use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\Command\SetNotificationPreference\SetNotificationPreferenceHandler;
use App\Enum\EmailFrequency;
use App\Repository\NotificationPreferenceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Translates a scope into the setting it stands for and delegates to the
 * handler that owns that setting — so „odhlásit" from a mail and the switch in
 * the settings page are the same write, not two implementations of it.
 */
#[AsMessageHandler]
final readonly class UnsubscribeFromEmailsHandler
{
    public function __construct(
        private NotificationPreferenceRepository $preferenceRepository,
        private SetNotificationPreferenceHandler $setPreference,
        private MuteCompetitionNotificationsHandler $muteCompetition,
        private ChangeEmailDeliveryHandler $changeDelivery,
    ) {
    }

    public function __invoke(UnsubscribeFromEmailsCommand $command): void
    {
        $scope = $command->scope;

        if (null !== $scope->type) {
            // Only the e-mail half changes; the in-app switch keeps its value.
            $preference = $this->preferenceRepository->findOne($command->userId, $scope->type);

            ($this->setPreference)(new SetNotificationPreferenceCommand(
                userId: $command->userId,
                type: $scope->type,
                inApp: null !== $preference ? $preference->inApp : $scope->type->defaultInApp(),
                email: $command->resubscribe,
            ));

            return;
        }

        if (null !== $scope->competitionId) {
            ($this->muteCompetition)(new MuteCompetitionNotificationsCommand(
                userId: $command->userId,
                competitionId: $scope->competitionId,
                muted: !$command->resubscribe,
            ));

            return;
        }

        // Everything. Coming back lands on the default frequency — we do not
        // remember what it was before, and the default is the gentle one.
        ($this->changeDelivery)(new ChangeEmailDeliveryCommand(
            userId: $command->userId,
            frequency: $command->resubscribe ? EmailFrequency::default() : EmailFrequency::Never,
        ));
    }
}
