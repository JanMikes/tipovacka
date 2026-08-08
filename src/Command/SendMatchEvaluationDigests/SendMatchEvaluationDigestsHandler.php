<?php

declare(strict_types=1);

namespace App\Command\SendMatchEvaluationDigests;

use App\Entity\Competition;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationDelivery;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Service\CzechPlural;
use App\Service\Notification\Notifier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The e-mail half of `match_evaluated` — ONE mail per user for everything
 * evaluated since their last digest, never one per zápas.
 *
 * Why it exists: {@see \App\Event\NotifyMatchEvaluatedHandler} writes an in-app
 * row the instant a match is evaluated, which is right for the feed. Mailing on
 * the same trigger was survivable only while a human entered results one at a
 * time; an automated feed finishes a whole round in one pass, so the same code
 * would send eight mails in eight seconds. This sweep collapses that into
 * „Vyhodnoceno 8 zápasů", exactly the shape the guess reminder already uses.
 *
 * Idempotency mirrors the reminder's: each already-written in-app row's dedup
 * key gets a `digest:`-prefixed twin. The mail stamps every key it covered, so
 * a match is mailed about at most once while a NEWLY evaluated one still
 * triggers a fresh digest listing only what has not been mailed yet.
 */
#[AsMessageHandler]
final readonly class SendMatchEvaluationDigestsHandler
{
    /**
     * How far back a sweep looks. Comfortably wider than the hourly cadence so
     * a skipped run (deploy, box reboot) still catches up, while the dedup keys
     * keep the overlap from re-mailing anything.
     */
    private const string WINDOW = '-24 hours';

    public function __construct(
        private NotificationRepository $notificationRepository,
        private Notifier $notifier,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendMatchEvaluationDigestsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $since = $now->modify(self::WINDOW);

        /** @var array<string, array{user: User, rows: list<Notification>}> $byUser */
        $byUser = [];

        foreach ($this->notificationRepository->listSince(NotificationType::MatchEvaluated, $since) as $row) {
            $dedupKey = $row->dedupKey;

            // Only the per-match rows are digest material; the digest's own
            // marker rows carry the `digest:` prefix and must not feed back in.
            if (null === $dedupKey || !str_starts_with($dedupKey, 'match_evaluated:')) {
                continue;
            }

            $userKey = $row->user->id->toRfc4122();
            $byUser[$userKey] ??= ['user' => $row->user, 'rows' => []];
            $byUser[$userKey]['rows'][] = $row;
        }

        foreach ($byUser as $entry) {
            $this->digestUser($entry['user'], $entry['rows']);
        }
    }

    /**
     * @param list<Notification> $rows
     */
    private function digestUser(User $user, array $rows): void
    {
        $keysByRow = [];

        foreach ($rows as $row) {
            $keysByRow[sprintf('digest:%s', (string) $row->dedupKey)] = $row;
        }

        $newKeys = array_values(array_diff(
            array_keys($keysByRow),
            $this->notificationRepository->existingDedupKeys(
                $user->id,
                NotificationType::MatchEvaluated,
                array_keys($keysByRow),
            ),
        ));

        if ([] === $newKeys) {
            return;
        }

        $pending = array_map(static fn (string $key): Notification => $keysByRow[$key], $newKeys);
        $competitions = $this->distinctCompetitions($pending);
        $count = count($pending);

        [$title, $body, $url, $competition] = 1 === $count
            ? $this->singleMatch($pending[0])
            : $this->manyMatches($count, $competitions);

        $this->notifier->notify(
            user: $user,
            type: NotificationType::MatchEvaluated,
            title: $title,
            body: $body,
            url: $url,
            competition: $competition,
            payload: ['matches' => $count, 'competitions' => count($competitions)],
            dedupKey: $newKeys[0],
            additionalDedupKeys: array_slice($newKeys, 1),
            // The in-app rows already exist, one per match — this delivery is
            // purely the mail, and its rows are invisible dedup markers.
            delivery: NotificationDelivery::EmailOnly,
        );
    }

    /**
     * @return array{string, string, string, Competition|null}
     */
    private function singleMatch(Notification $pending): array
    {
        return [
            $pending->title,
            $pending->body,
            $pending->url ?? $this->urlGenerator->generate('matches', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            $pending->competition,
        ];
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return array{string, string, string, Competition|null}
     */
    private function manyMatches(int $count, array $competitions): array
    {
        $lines = ['Vyhodnotili jsme zápasy, na které jste tipovali:'];

        foreach ($competitions as $competition) {
            $lines[] = sprintf('• %s', $competition->name);
        }

        $lines[] = 'Podrobnosti a body najdete v přehledu.';

        return [
            sprintf('Vyhodnoceno %d %s', $count, CzechPlural::zapas($count)),
            implode("\n", $lines),
            $this->urlGenerator->generate('matches', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            1 === count($competitions) ? $competitions[0] : null,
        ];
    }

    /**
     * @param list<Notification> $pending
     *
     * @return list<Competition>
     */
    private function distinctCompetitions(array $pending): array
    {
        $competitions = [];

        foreach ($pending as $row) {
            $competition = $row->competition;

            if (null !== $competition) {
                $competitions[$competition->id->toRfc4122()] = $competition;
            }
        }

        return array_values($competitions);
    }
}
