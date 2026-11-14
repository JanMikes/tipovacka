<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Competition;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Service\CzechPlural;
use App\Value\CompetitionTipReminder;
use App\Value\EmailDigest;
use App\Value\EmailDigestSection;
use App\Value\EmailItem;
use App\Value\UnsubscribeScope;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Folds one player's queued notifications — and, for the daily summary, the
 * tips they still owe — into ONE mail, grouped by soutěž: what the player
 * thinks in is „what is going on in my soutěž", not „which event type fired".
 *
 * Order of sections: soutěže with a tip to enter first (nearest deadline
 * first, because that is the one thing with a clock on it), then the rest by
 * name, news that belong to no soutěž last.
 */
final readonly class EmailDigestComposer
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    /** News lines shown per soutěž before the rest folds into „a další…". */
    private const int MAX_ITEMS_PER_SECTION = 6;

    private const array WEEKDAYS = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param list<Notification>           $rows      queued notifications, oldest first
     * @param list<CompetitionTipReminder> $reminders tips still owed, most urgent first
     */
    public function compose(array $rows, array $reminders, bool $daily): EmailDigest
    {
        // Keyed by soutěž UUID; '' is the bucket of news that belong to no soutěž.
        /** @var array<string, Competition|null> $competitions */
        $competitions = [];
        /** @var array<string, EmailItem> $missing */
        $missing = [];
        /** @var array<string, list<EmailItem>> $items */
        $items = [];
        $missingTotal = 0;

        foreach ($reminders as $reminder) {
            $key = $reminder->competition->id->toRfc4122();
            $competitions[$key] = $reminder->competition;
            $missingTotal += $reminder->count;
            $missing[$key] = new EmailItem(
                title: sprintf('Chybí %d %s', $reminder->count, CzechPlural::tipCount($reminder->count)),
                detail: sprintf('nejbližší uzávěrka %s', self::deadlineLabel($reminder->earliestDeadline)),
                url: $this->competitionUrl($key),
            );
        }

        foreach ($rows as $row) {
            $key = null !== $row->competition ? $row->competition->id->toRfc4122() : '';
            $competitions[$key] ??= $row->competition;
            $items[$key][] = self::itemFor($row);
        }

        // Soutěže with a deadline keep the finder's urgency order; the rest go by
        // name, and the soutěž-less bucket always closes the mail.
        $withDeadline = array_keys($missing);
        $rest = array_values(array_diff(array_keys($competitions), $withDeadline, ['']));
        usort($rest, static fn (string $a, string $b): int => strcoll((string) $competitions[$a]?->name, (string) $competitions[$b]?->name));
        $order = [...$withDeadline, ...$rest, ...(isset($items['']) ? [''] : [])];

        $sections = [];

        foreach ($order as $key) {
            $competition = $competitions[$key] ?? null;
            $sectionItems = $items[$key] ?? [];

            $sections[] = new EmailDigestSection(
                competitionId: $competition?->id,
                competitionName: $competition?->name,
                competitionUrl: null !== $competition ? $this->competitionUrl($key) : null,
                missingTips: $missing[$key] ?? null,
                items: array_slice($sectionItems, 0, self::MAX_ITEMS_PER_SECTION),
                hiddenItemCount: max(0, count($sectionItems) - self::MAX_ITEMS_PER_SECTION),
            );
        }

        $parts = self::summaryParts($missingTotal, $rows);

        return new EmailDigest(
            headline: self::capitalize($parts[0] ?? ''),
            summary: self::capitalize(implode(' · ', array_slice($parts, 1))),
            sections: $sections,
            actionLabel: $missingTotal > 0 ? 'Natipovat' : 'Otevřít přehled',
            actionUrl: $this->urlGenerator->generate($missingTotal > 0 ? 'matches' : 'dashboard', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            daily: $daily,
            unsubscribeScope: self::unsubscribeScope($rows, $daily),
        );
    }

    /** „so 15. 11. 13:30" — a deadline the way the mail states it, in Prague time. */
    public static function deadlineLabel(\DateTimeImmutable $deadline): string
    {
        $local = $deadline->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE));

        return sprintf('%s %s', self::WEEKDAYS[(int) $local->format('w')], $local->format('j. n. H:i'));
    }

    private static function itemFor(Notification $row): EmailItem
    {
        // An evaluated match states everything in its body („A 2:1 B: získáváte
        // 3 b., jste 2. v soutěži X") — its title would only repeat the score,
        // and the soutěž is already the heading of the section it sits in.
        if (NotificationType::MatchEvaluated === $row->type) {
            $body = null !== $row->competition
                ? str_replace(sprintf(' v soutěži %s', $row->competition->name), '', $row->body)
                : $row->body;

            return new EmailItem(title: $body, url: $row->url);
        }

        return new EmailItem(title: $row->title, detail: $row->body, url: $row->url);
    }

    /**
     * What is inside, most actionable first — „chybí vám 3 tipy", „vyhodnoceny
     * 3 zápasy", „1 novinka". The first becomes the heading, the rest the line
     * under it, and together they are the subject, instead of a generic „Váš souhrn".
     *
     * @param list<Notification> $rows
     *
     * @return list<string>
     */
    private static function summaryParts(int $missingTotal, array $rows): array
    {
        $evaluated = count(array_filter($rows, static fn (Notification $row): bool => NotificationType::MatchEvaluated === $row->type));
        $other = count($rows) - $evaluated;
        $parts = [];

        if ($missingTotal > 0) {
            $parts[] = sprintf('chybí vám %d %s', $missingTotal, CzechPlural::tipCount($missingTotal));
        }

        if ($evaluated > 0) {
            $parts[] = sprintf(
                '%s %d %s',
                match (true) {
                    1 === $evaluated => 'vyhodnocen',
                    $evaluated <= 4 => 'vyhodnoceny',
                    default => 'vyhodnoceno',
                },
                $evaluated,
                CzechPlural::zapas($evaluated),
            );
        }

        if ($other > 0) {
            $parts[] = sprintf('%d %s', $other, match (true) {
                1 === $other => 'novinka',
                $other <= 4 => 'novinky',
                default => 'novinek',
            });
        }

        return $parts;
    }

    private static function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * What „unsubscribe" in the mail client means for this mail. The daily
     * summary IS the player's e-mail from us, so leaving it means leaving
     * e-mail; a burst of one type switches off just that type.
     *
     * @param list<Notification> $rows
     */
    private static function unsubscribeScope(array $rows, bool $daily): UnsubscribeScope
    {
        if ($daily) {
            return UnsubscribeScope::all();
        }

        $types = array_unique(array_map(static fn (Notification $row): string => $row->type->value, $rows));

        return 1 === count($types) ? UnsubscribeScope::type($rows[0]->type) : UnsubscribeScope::all();
    }

    private function competitionUrl(string $competitionId): string
    {
        return $this->urlGenerator->generate('competition_detail', ['id' => $competitionId], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
