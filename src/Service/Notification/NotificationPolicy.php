<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Competition;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailFrequency;
use App\Enum\EmailPlan;
use App\Enum\EmailTiming;
use App\Enum\NotificationType;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationSettingsRepository;
use App\Value\NotificationDecision;

/**
 * THE single answer to „what does this notification do for this player" — on
 * which channels, and whether its mail goes now or waits for a digest. Four
 * things decide, in this order:
 *
 *  1. **Muted soutěž** ({@see \App\Entity\Membership::$notificationsMuted}) —
 *     total silence about that soutěž, both channels. Money alerts
 *     ({@see EmailTiming::Urgent}) ignore the mute.
 *  2. **Per-type switches** ({@see \App\Entity\NotificationPreference}, else the
 *     type's defaults) — which channels the player wants this type on at all.
 *  3. **E-mail frequency** ({@see EmailFrequency}) — „žádné e-maily" closes the
 *     e-mail channel for everything; „jednou denně" turns mails into lines of
 *     one summary.
 *  4. **The type's timing** ({@see EmailTiming}) — urgent goes at once, bursts
 *     always queue, a live statement is never queued.
 *
 * The digest sweep asks {@see stillWantsEmail} again right before it sends, so a
 * player who opted out after a row was queued does not get it the next morning.
 */
final readonly class NotificationPolicy
{
    public function __construct(
        private NotificationPreferenceRepository $preferenceRepository,
        private NotificationSettingsRepository $settingsRepository,
        private MembershipRepository $membershipRepository,
    ) {
    }

    public function decide(User $user, NotificationType $type, ?Competition $competition): NotificationDecision
    {
        $timing = $type->emailTiming();

        if ($this->isMuted($user, $type, $competition)) {
            return NotificationDecision::silent();
        }

        $preference = $this->preferenceRepository->findOne($user->id, $type);
        $wantsInApp = null !== $preference ? $preference->inApp : $type->defaultInApp();
        $wantsEmail = null !== $preference ? $preference->email : $type->defaultEmail();

        if (!$wantsEmail || !self::hasAddress($user)) {
            return new NotificationDecision($wantsInApp, EmailPlan::None);
        }

        $frequency = self::frequencyOf($this->settingsRepository->findForUser($user->id));

        if (EmailFrequency::Never === $frequency) {
            return new NotificationDecision($wantsInApp, EmailPlan::None);
        }

        $daily = EmailFrequency::Daily === $frequency;

        $plan = match ($timing) {
            EmailTiming::Urgent => EmailPlan::SendNow,
            EmailTiming::Burst => EmailPlan::Queue,
            EmailTiming::Live => $daily ? EmailPlan::None : EmailPlan::SendNow,
            EmailTiming::Standard => $daily ? EmailPlan::Queue : EmailPlan::SendNow,
        };

        return new NotificationDecision($wantsInApp, $plan);
    }

    /**
     * Re-checks a QUEUED row at send time: does the player still want mail about
     * this type and this soutěž? (Frequency is the sweep's own concern.).
     */
    public function stillWantsEmail(User $user, NotificationType $type, ?Competition $competition): bool
    {
        if (!self::hasAddress($user) || $this->isMuted($user, $type, $competition)) {
            return false;
        }

        $preference = $this->preferenceRepository->findOne($user->id, $type);

        return null !== $preference ? $preference->email : $type->defaultEmail();
    }

    /** The frequency a settings row stands for — a missing row is the default. */
    public static function frequencyOf(?NotificationSettings $settings): EmailFrequency
    {
        return null !== $settings ? $settings->emailFrequency : EmailFrequency::default();
    }

    public static function digestHourOf(?NotificationSettings $settings): int
    {
        return null !== $settings ? $settings->digestHour : NotificationSettings::DEFAULT_DIGEST_HOUR;
    }

    public static function hasAddress(User $user): bool
    {
        return null !== $user->email && '' !== $user->email;
    }

    private function isMuted(User $user, NotificationType $type, ?Competition $competition): bool
    {
        return null !== $competition
            && EmailTiming::Urgent !== $type->emailTiming()
            && $this->membershipRepository->hasMutedNotifications($user->id, $competition->id);
    }
}
