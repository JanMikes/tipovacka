<?php

declare(strict_types=1);

namespace App\Command\SendEmailDigests;

use App\Entity\Notification;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailFrequency;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Repository\NotificationSettingsRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Notification\EmailDigestComposer;
use App\Service\Notification\NotificationMailer;
use App\Service\Notification\NotificationPolicy;
use App\Service\Notification\TipReminderFinder;
use App\Value\CompetitionTipReminder;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The hourly e-mail sweep — turns „many things happened" into ONE mail per
 * player. It serves both e-mail frequencies:
 *
 *  - **průběžně**: whatever the {@see \App\Service\Notification\Notifier} queued
 *    since the last run (burst types: evaluated matches, added matches, joined
 *    players) goes out now as one mail, so a feed finishing a whole round sends
 *    „Vyhodnoceno 8 zápasů", not eight mails;
 *  - **jednou denně**: nothing leaves until the player's hour
 *    ({@see NotificationSettings::$digestHour}, Europe/Prague), and then at most
 *    once per calendar day — everything queued since the last summary PLUS the
 *    tips still missing, computed at that moment (a reminder queued yesterday
 *    would lie today). A day with nothing to say sends nothing and stays „due",
 *    so news at 15:00 still reaches the player the same day.
 *
 * The daily summary looks {@see self::DAILY_REMINDER_WINDOW} ahead, wider than
 * the hourly reminder's 24 h: a mail that comes once a day has to cover the
 * morning after it, or a Saturday-10:15 deadline would be announced two hours
 * before it. A tip still missing the next day is simply listed again.
 *
 * A queued row is re-checked against the player's CURRENT preferences right
 * before sending: whoever unsubscribed last night does not get yesterday's
 * queue this morning. Such rows are settled without a mail.
 *
 * One player's failure never costs another their mail — each is rendered and
 * sent inside its own try/catch, logged, and retried by the next sweep (its
 * rows stay pending).
 */
#[AsMessageHandler]
final readonly class SendEmailDigestsHandler
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';
    private const string DAILY_REMINDER_WINDOW = '+36 hours';

    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationSettingsRepository $settingsRepository,
        private NotificationPreferenceRepository $preferenceRepository,
        private NotificationPolicy $policy,
        private TipReminderFinder $finder,
        private EmailDigestComposer $composer,
        private NotificationMailer $notificationMailer,
        private ProvideIdentity $identityProvider,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendEmailDigestsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $settingsByUser = $this->settingsRepository->mapAll();

        /** @var array<string, array{user: User, rows: list<Notification>}> $pending */
        $pending = [];

        foreach ($this->notificationRepository->listEmailPending() as $row) {
            $userKey = $row->user->id->toRfc4122();
            $pending[$userKey] ??= ['user' => $row->user, 'rows' => []];
            $pending[$userKey]['rows'][] = $row;
        }

        // Tips still owed, only for players whose daily summary can go out now
        // and who want the reminder by e-mail at all.
        $remindersOff = $this->preferenceRepository->userIdsWithEmailOff(NotificationType::GuessReminder);
        $reminders = $this->finder->find(
            $now,
            $now->modify(self::DAILY_REMINDER_WINDOW),
            fn (User $user): bool => !isset($remindersOff[$user->id->toRfc4122()])
                && NotificationPolicy::hasAddress($user)
                && $this->isDailyAndDue($settingsByUser[$user->id->toRfc4122()] ?? null, $now),
        );

        foreach ($pending as $userKey => $entry) {
            $this->serve($entry['user'], $entry['rows'], $reminders[$userKey]['reminders'] ?? [], $settingsByUser[$userKey] ?? null, $now);
        }

        foreach ($reminders as $userKey => $entry) {
            if (!isset($pending[$userKey])) {
                $this->serve($entry['user'], [], $entry['reminders'], $settingsByUser[$userKey] ?? null, $now);
            }
        }
    }

    /**
     * @param list<Notification>           $rows
     * @param list<CompetitionTipReminder> $reminders
     */
    private function serve(User $user, array $rows, array $reminders, ?NotificationSettings $settings, \DateTimeImmutable $now): void
    {
        try {
            $frequency = NotificationPolicy::frequencyOf($settings);
            $daily = EmailFrequency::Daily === $frequency;

            // Not their hour yet (or today's summary already went): the queue waits.
            if ($daily && !$this->isDailyAndDue($settings, $now)) {
                return;
            }

            $wanted = [];

            foreach ($rows as $row) {
                if (EmailFrequency::Never !== $frequency && $this->policy->stillWantsEmail($user, $row->type, $row->competition)) {
                    $wanted[] = $row;

                    continue;
                }

                // Opted out since it was queued — settled, never mailed.
                $row->settleEmail($now);
            }

            if (!$daily) {
                $reminders = [];
            }

            if ([] === $wanted && [] === $reminders) {
                return;
            }

            if (1 === count($wanted) && [] === $reminders) {
                // A single piece of news reads better as itself than as a „souhrn" of one.
                $row = $wanted[0];
                $this->notificationMailer->sendNotification($user, $row->type, $row->title, $row->body, $row->url);
            } else {
                $this->notificationMailer->sendDigest($user, $this->composer->compose($wanted, $reminders, $daily));
            }

            foreach ($wanted as $row) {
                $row->settleEmail($now);
            }

            if ($daily) {
                $this->stampDigest($user, $settings, $now);
            }
        } catch (\Throwable $e) {
            $this->logger->error('E-mail digest failed', [
                'userId' => $user->id->toRfc4122(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Whether this player is on the daily summary AND one may be sent now: their
     * hour has come (Prague time) and none went out yet this calendar day.
     */
    private function isDailyAndDue(?NotificationSettings $settings, \DateTimeImmutable $now): bool
    {
        if (EmailFrequency::Daily !== NotificationPolicy::frequencyOf($settings)) {
            return false;
        }

        $timezone = new \DateTimeZone(self::PRAGUE_TIMEZONE);
        $localNow = $now->setTimezone($timezone);

        if ((int) $localNow->format('G') < NotificationPolicy::digestHourOf($settings)) {
            return false;
        }

        $last = $settings?->lastDigestAt;

        return null === $last || $last->setTimezone($timezone)->format('Y-m-d') < $localNow->format('Y-m-d');
    }

    /**
     * Records today's summary. A player still on the defaults has no settings
     * row yet — the first summary creates it, spelling out the defaults they
     * were served with.
     */
    private function stampDigest(User $user, ?NotificationSettings $settings, \DateTimeImmutable $now): void
    {
        if (null === $settings) {
            $settings = new NotificationSettings(
                id: $this->identityProvider->next(),
                user: $user,
                emailFrequency: EmailFrequency::default(),
                digestHour: NotificationSettings::DEFAULT_DIGEST_HOUR,
                createdAt: $now,
            );
            $this->settingsRepository->save($settings);
        }

        $settings->markDigestSent($now);
    }
}
