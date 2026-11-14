<?php

declare(strict_types=1);

namespace App\Command\SendGuessReminders;

use App\Entity\Competition;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Service\CzechPlural;
use App\Service\Notification\EmailDigestComposer;
use App\Service\Notification\Notifier;
use App\Service\Notification\TipReminderFinder;
use App\Value\CompetitionTipReminder;
use App\Value\EmailItem;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The hourly reminder sweep — ONE aggregated notification per USER, never one
 * per competition (a player in ten soutěže gets one, not ten). Two rules shape
 * what it says, both owned by {@see TipReminderFinder}:
 *
 *  - a missing tip is only one the player can enter RIGHT NOW: the tip window
 *    is open at sweep time (not waiting for its opening, deadline not passed)
 *    and closes within the next 24 h. A locked or not-yet-open match is never
 *    counted and never nagged about;
 *  - it breaks down per soutěž with its own count and NEAREST deadline,
 *    because deadlines inside one reminder are staggered.
 *
 * Idempotency keeps the pre-digest granularity: every (competition, Prague
 * deadline-day) bucket is stamped once — the Notification row carries the
 * first new bucket key and the remaining ones become invisible marker rows
 * (see {@see Notifier}). A bucket therefore triggers at most one reminder ever,
 * while a NEW bucket (another soutěž, another day) still fires a fresh one
 * listing everything currently missing. A match added later on an
 * already-reminded day rides the existing stamp — no re-nag.
 *
 * On the e-mail channel this sweep only ever mails a player on the „průběžně"
 * frequency. A player on the daily summary gets the feed row from here and
 * reads about missing tips in that summary instead, recomputed when it is sent
 * ({@see \App\Command\SendEmailDigests\SendEmailDigestsHandler}) — the choice
 * is {@see \App\Service\Notification\NotificationPolicy}'s, not this handler's.
 */
#[AsMessageHandler]
final readonly class SendGuessRemindersHandler
{
    private const string REMINDER_WINDOW = '+24 hours';

    public function __construct(
        private TipReminderFinder $finder,
        private NotificationRepository $notificationRepository,
        private Notifier $notifier,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendGuessRemindersCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->finder->find($now, $now->modify(self::REMINDER_WINDOW)) as $entry) {
            $this->remindUser($entry['user'], $entry['reminders']);
        }
    }

    /**
     * @param list<CompetitionTipReminder> $reminders most urgent soutěž first
     */
    private function remindUser(User $user, array $reminders): void
    {
        $allKeys = [];

        foreach ($reminders as $reminder) {
            foreach ($reminder->bucketKeys as $key) {
                $allKeys[$key] = true;
            }
        }

        $allKeys = array_keys($allKeys);
        $newKeys = array_values(array_diff(
            $allKeys,
            $this->notificationRepository->existingDedupKeys($user->id, NotificationType::GuessReminder, $allKeys),
        ));

        // Every (competition, deadline-day) bucket was reminded already — an
        // hourly repeat would be spam, not news.
        if ([] === $newKeys) {
            return;
        }

        $total = array_sum(array_map(static fn (CompetitionTipReminder $reminder): int => $reminder->count, $reminders));
        $emailBody = null;
        $emailItems = [];

        if (1 === count($reminders)) {
            $competition = $reminders[0]->competition;
            $count = $reminders[0]->count;
            $title = sprintf('Chybí vám tipy v soutěži %s', $competition->name);
            $body = sprintf(
                'V soutěži %s vám chybí %s na %d %s, nejbližší uzávěrka %s.',
                $competition->name,
                CzechPlural::tip($count),
                $count,
                CzechPlural::zapas($count),
                $this->deadlineLabel($reminders[0]->earliestDeadline),
            );
            $url = $this->competitionUrl($competition);
        } else {
            $competition = null;
            $title = sprintf('Chybí vám %d %s', $total, CzechPlural::tipCount($total));
            $emailBody = 'Blíží se uzávěrky a ve vašich soutěžích vám chybí tipy:';
            $lines = [$emailBody];

            foreach ($reminders as $reminder) {
                $lines[] = sprintf(
                    '• %s — %d %s, nejbližší uzávěrka %s',
                    $reminder->competition->name,
                    $reminder->count,
                    CzechPlural::tipCount($reminder->count),
                    $this->deadlineLabel($reminder->earliestDeadline),
                );

                // The mail lists the same soutěže as rows, each a link of its own.
                $emailItems[] = new EmailItem(
                    title: $reminder->competition->name,
                    detail: sprintf(
                        '%d %s · uzávěrka %s',
                        $reminder->count,
                        CzechPlural::tipCount($reminder->count),
                        EmailDigestComposer::deadlineLabel($reminder->earliestDeadline),
                    ),
                    url: $this->competitionUrl($reminder->competition),
                );
            }

            $body = implode("\n", $lines);
            $url = $this->urlGenerator->generate('matches', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        }

        $this->notifier->notify(
            user: $user,
            type: NotificationType::GuessReminder,
            title: $title,
            body: $body,
            url: $url,
            competition: $competition,
            payload: ['missing' => $total, 'competitions' => count($reminders)],
            dedupKey: $newKeys[0],
            additionalDedupKeys: array_slice($newKeys, 1),
            emailBody: $emailBody,
            emailItems: $emailItems,
        );
    }

    private function competitionUrl(Competition $competition): string
    {
        return $this->urlGenerator->generate(
            'competition_detail',
            ['id' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    private function deadlineLabel(\DateTimeImmutable $deadline): string
    {
        return $deadline->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. H:i');
    }
}
