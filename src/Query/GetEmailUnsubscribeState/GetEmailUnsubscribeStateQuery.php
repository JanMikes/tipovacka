<?php

declare(strict_types=1);

namespace App\Query\GetEmailUnsubscribeState;

use App\Enum\EmailFrequency;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationSettingsRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationPolicy;
use App\Value\UnsubscribeScope;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetEmailUnsubscribeStateQuery
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationSettingsRepository $settingsRepository,
        private NotificationPreferenceRepository $preferenceRepository,
        private MembershipRepository $membershipRepository,
    ) {
    }

    public function __invoke(GetEmailUnsubscribeState $query): ?GetEmailUnsubscribeStateResult
    {
        $user = $this->userRepository->find($query->userId);

        if (null === $user || null === $user->email || '' === $user->email) {
            return null;
        }

        $settings = $this->settingsRepository->findForUser($user->id);
        $frequency = NotificationPolicy::frequencyOf($settings);

        [$label, $consequence, $subscribed, $scope] = $this->describe($user->id, $query->scope, $frequency);

        return new GetEmailUnsubscribeStateResult(
            maskedEmail: self::mask($user->email),
            frequency: $frequency,
            digestHour: NotificationPolicy::digestHourOf($settings),
            scopeSlug: $scope?->toSlug(),
            scopeLabel: $label,
            scopeConsequence: $consequence,
            scopeSubscribed: $subscribed,
            scopeIsAll: null !== $scope && $scope->isAll,
        );
    }

    /**
     * @return array{string|null, string|null, bool, UnsubscribeScope|null}
     */
    private function describe(Uuid $userId, ?UnsubscribeScope $scope, EmailFrequency $frequency): array
    {
        if (null === $scope) {
            return [null, null, false, null];
        }

        if (null !== $scope->type) {
            $preference = $this->preferenceRepository->findOne($userId, $scope->type);

            return [
                sprintf('oznámení „%s“', $scope->type->label()),
                'Tento typ oznámení vám přestane chodit e-mailem. V aplikaci ho uvidíte dál.',
                null !== $preference ? $preference->email : $scope->type->defaultEmail(),
                $scope,
            ];
        }

        if (null !== $scope->competitionId) {
            $membership = $this->membershipRepository->findActiveMembership($userId, $scope->competitionId);

            // No longer a member: the link points at nothing — fall back to the general page.
            if (null === $membership) {
                return [null, null, false, null];
            }

            return [
                sprintf('soutěž %s', $membership->competition->name),
                'Ze soutěže vám přestanou chodit oznámení i e-maily a její zápasy vynecháme z připomínek tipů. Hrajete v ní dál.',
                !$membership->notificationsMuted,
                $scope,
            ];
        }

        return [
            'všechny e-maily s oznámeními',
            'Přestaneme vám posílat připomínky, výsledky i souhrny. E-maily k účtu (heslo, platby) chodí dál.',
            EmailFrequency::Never !== $frequency,
            $scope,
        ];
    }

    /** „k•••@example.com" — enough to recognise your own address, not enough to learn someone else's. */
    private static function mask(string $email): string
    {
        $at = strrpos($email, '@');

        if (false === $at || 0 === $at) {
            return '•••';
        }

        return mb_substr($email, 0, 1).'•••'.substr($email, $at);
    }
}
