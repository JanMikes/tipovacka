<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\CompetitionTipReminder;

/**
 * „Who still owes which tips before a given horizon" — the one computation
 * behind the hourly guess reminder AND the „chybí natipovat" part of the daily
 * summary, so the two can never disagree about what counts.
 *
 * A missing tip is only one the player can enter RIGHT NOW: the tip window is
 * open at `$now` (not waiting for its opening, deadline not passed) and closes
 * before `$horizon`. A locked or not-yet-open match is never counted and never
 * nagged about. A soutěž the member muted is skipped entirely.
 *
 * Every (soutěž, Prague deadline-day) pair covered becomes one bucket key —
 * the granularity the reminder stamps its idempotency at.
 */
final readonly class TipReminderFinder
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private GuessRepository $guessRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
    ) {
    }

    /**
     * @param (\Closure(User): bool)|null $only restricts the search to these players BEFORE any
     *                                          per-match work is done for them
     *
     * @return array<string, array{user: User, reminders: list<CompetitionTipReminder>}> keyed by user UUID (RFC4122);
     *                                                                                   reminders most urgent first
     */
    public function find(\DateTimeImmutable $now, \DateTimeImmutable $horizon, ?\Closure $only = null): array
    {
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

                if ($membership->notificationsMuted || (null !== $only && !$only($user))) {
                    continue;
                }

                $deadlines = $this->missingDeadlines($competition, $user, $openMatches, $now, $horizon);

                if ([] === $deadlines) {
                    continue;
                }

                $userKey = $user->id->toRfc4122();
                $byUser[$userKey] ??= ['user' => $user, 'reminders' => []];
                $byUser[$userKey]['reminders'][] = new CompetitionTipReminder(
                    competition: $competition,
                    count: count($deadlines),
                    earliestDeadline: min($deadlines),
                    bucketKeys: $this->bucketKeys($competition, $deadlines),
                );
            }
        }

        foreach ($byUser as $userKey => $entry) {
            usort(
                $entry['reminders'],
                static fn (CompetitionTipReminder $a, CompetitionTipReminder $b): int => $a->earliestDeadline <=> $b->earliestDeadline,
            );
            $byUser[$userKey] = $entry;
        }

        return $byUser;
    }

    /**
     * Deadlines of the matches the user has NOT tipped and still CAN tip right
     * now — the window is open at $now and closes before $horizon.
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
     * One key per (competition, Prague day of the deadline).
     *
     * @param list<\DateTimeImmutable> $deadlines
     *
     * @return list<string>
     */
    private function bucketKeys(Competition $competition, array $deadlines): array
    {
        $keys = [];
        $timezone = new \DateTimeZone(self::PRAGUE_TIMEZONE);

        foreach ($deadlines as $deadline) {
            $day = $deadline->setTimezone($timezone)->format('Y-m-d');
            $keys[sprintf('guess_reminder:%s:%s', $competition->id->toRfc4122(), $day)] = true;
        }

        return array_keys($keys);
    }
}
