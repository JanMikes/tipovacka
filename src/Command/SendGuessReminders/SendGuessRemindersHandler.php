<?php

declare(strict_types=1);

namespace App\Command\SendGuessReminders;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\NotificationRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\CzechPlural;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Notification\Notifier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The hourly reminder sweep — ONE aggregated notification/e-mail per USER,
 * never one per competition (a player in ten soutěže gets one digest, not ten
 * mails). Two rules shape what it says:
 *
 *  - a missing tip is only one the player can enter RIGHT NOW: the tip window
 *    is open at sweep time (not waiting for its opening, deadline not passed)
 *    and closes within the next 24 h. A locked or not-yet-open match is never
 *    counted and never nagged about;
 *  - the digest breaks down per soutěž with its own count and NEAREST deadline,
 *    because deadlines inside one digest are staggered.
 *
 * Idempotency keeps the pre-digest granularity: every (competition, Prague
 * deadline-day) bucket is stamped once — the digest's Notification row carries
 * the first new bucket key and the remaining ones become invisible marker rows
 * (see {@see Notifier}). A bucket therefore triggers at most one digest ever,
 * while a NEW bucket (another soutěž, another day) still fires a fresh digest
 * listing everything currently missing. A match added later on an
 * already-reminded day rides the existing stamp — no re-nag.
 */
#[AsMessageHandler]
final readonly class SendGuessRemindersHandler
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';
    private const string REMINDER_WINDOW = '+24 hours';

    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private GuessRepository $guessRepository,
        private NotificationRepository $notificationRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private Notifier $notifier,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendGuessRemindersCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $horizon = $now->modify(self::REMINDER_WINDOW);

        /** @var array<string, array{user: User, items: list<array{competition: Competition, count: int, earliest: \DateTimeImmutable, bucketKeys: list<string>}>}> $byUser */
        $byUser = [];

        foreach ($this->competitionRepository->findAllActive() as $competition) {
            $members = $this->membershipRepository->findActiveByCompetition($competition->id);

            if ([] === $members) {
                continue;
            }

            // Only matches still open for guesses with a future kickoff can have a
            // reminder-worthy deadline; windows are then resolved per member.
            $openMatches = array_values(array_filter(
                $this->matchProvider->matchesFor($competition),
                static fn (SportMatch $match): bool => $match->isOpenForGuesses && $match->kickoffAt > $now,
            ));

            if ([] === $openMatches) {
                continue;
            }

            foreach ($members as $membership) {
                $user = $membership->user;
                $missing = $this->missingDeadlines($competition, $user, $openMatches, $now, $horizon);

                if ([] === $missing) {
                    continue;
                }

                $userKey = $user->id->toRfc4122();
                $byUser[$userKey] ??= ['user' => $user, 'items' => []];
                $byUser[$userKey]['items'][] = [
                    'competition' => $competition,
                    'count' => count($missing),
                    'earliest' => min($missing),
                    'bucketKeys' => $this->bucketKeys($competition, $missing),
                ];
            }
        }

        foreach ($byUser as $data) {
            $this->remindUser($data['user'], $data['items']);
        }
    }

    /**
     * Deadlines of the matches the user has NOT tipped and still CAN tip right
     * now — the window is open at $now (a not-yet-open or already-closed tip is
     * not a missing tip) and closes within the next 24 h.
     *
     * @param list<SportMatch> $openMatches
     *
     * @return list<\DateTimeImmutable>
     */
    private function missingDeadlines(
        Competition $competition,
        User $user,
        array $openMatches,
        \DateTimeImmutable $now,
        \DateTimeImmutable $horizon,
    ): array {
        $windows = $this->deadlineResolver->windowsFor($competition, $openMatches, $user);
        $deadlines = [];

        foreach ($openMatches as $match) {
            $window = $windows[$match->id->toRfc4122()];

            if (!$window->isOpen($now) || $window->deadline > $horizon) {
                continue;
            }

            if (null !== $this->guessRepository->findActiveByUserMatchCompetition($user->id, $match->id, $competition->id)) {
                continue;
            }

            $deadlines[] = $window->deadline;
        }

        return $deadlines;
    }

    /**
     * @param list<array{competition: Competition, count: int, earliest: \DateTimeImmutable, bucketKeys: list<string>}> $items
     */
    private function remindUser(User $user, array $items): void
    {
        if ([] === $items) {
            return;
        }

        $allKeys = [];

        foreach ($items as $item) {
            foreach ($item['bucketKeys'] as $key) {
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

        // Most urgent soutěž first.
        usort($items, static fn (array $a, array $b): int => $a['earliest'] <=> $b['earliest']);

        $total = 0;

        foreach ($items as $item) {
            $total += $item['count'];
        }

        if (1 === count($items)) {
            $competition = $items[0]['competition'];
            $count = $items[0]['count'];
            $title = sprintf('Chybí vám tipy v soutěži %s', $competition->name);
            $body = sprintf(
                'V soutěži %s vám chybí %s na %d %s, nejbližší uzávěrka %s.',
                $competition->name,
                CzechPlural::tip($count),
                $count,
                CzechPlural::zapas($count),
                $this->deadlineLabel($items[0]['earliest']),
            );
            $url = $this->urlGenerator->generate(
                'competition_detail',
                ['id' => $competition->id->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } else {
            $competition = null;
            $title = sprintf('Chybí vám %d %s', $total, CzechPlural::tipCount($total));
            $lines = ['Blíží se uzávěrky a ve vašich soutěžích vám chybí tipy:'];

            foreach ($items as $item) {
                $lines[] = sprintf(
                    '• %s — %d %s, nejbližší uzávěrka %s',
                    $item['competition']->name,
                    $item['count'],
                    CzechPlural::tipCount($item['count']),
                    $this->deadlineLabel($item['earliest']),
                );
            }

            $body = implode("\n", $lines);
            $url = $this->urlGenerator->generate('matches', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $this->notifier->notify(
            user: $user,
            type: NotificationType::GuessReminder,
            title: $title,
            body: $body,
            url: $url,
            competition: $competition,
            payload: ['missing' => $total, 'competitions' => count($items)],
            dedupKey: $newKeys[0],
            additionalDedupKeys: array_slice($newKeys, 1),
        );
    }

    /**
     * One key per (competition, Prague day of the deadline) — the granularity a
     * digest is stamped at, unchanged from the pre-digest reminder so already
     * sent reminders stay deduplicated.
     *
     * @param list<\DateTimeImmutable> $deadlines
     *
     * @return list<string>
     */
    private function bucketKeys(Competition $competition, array $deadlines): array
    {
        $keys = [];

        foreach ($deadlines as $deadline) {
            $keys[sprintf('guess_reminder:%s:%s', $competition->id->toRfc4122(), $this->pragueDay($deadline))] = true;
        }

        return array_keys($keys);
    }

    private function deadlineLabel(\DateTimeImmutable $deadline): string
    {
        return $deadline->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))->format('j. n. H:i');
    }

    private function pragueDay(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))->format('Y-m-d');
    }
}
