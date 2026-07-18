<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class SportMatchImportSession
{
    private const string KEY_PREFIX = 'sport_match_import_preview_';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param list<SportMatchImportRow> $rows
     */
    public function store(Uuid $matchSourceId, array $rows): void
    {
        $session = $this->requestStack->getSession();
        $serialisable = array_map(
            static fn (SportMatchImportRow $row): array => [
                'rowNumber' => $row->rowNumber,
                'homeTeam' => $row->homeTeam,
                'awayTeam' => $row->awayTeam,
                'kickoffAt' => $row->kickoffAt->format(\DateTimeInterface::ATOM),
                'venue' => $row->venue,
                'round' => $row->round,
                'isPlayoff' => $row->isPlayoff,
            ],
            $rows,
        );

        $session->set($this->key($matchSourceId), $serialisable);
    }

    /**
     * @return list<SportMatchImportRow>
     */
    public function consume(Uuid $matchSourceId): array
    {
        $session = $this->requestStack->getSession();
        $raw = $session->get($this->key($matchSourceId));
        $session->remove($this->key($matchSourceId));

        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kickoff = is_string($item['kickoffAt'] ?? null)
                ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $item['kickoffAt'])
                : null;

            if (!$kickoff instanceof \DateTimeImmutable) {
                continue;
            }

            $rows[] = new SportMatchImportRow(
                rowNumber: (int) ($item['rowNumber'] ?? 0),
                homeTeam: (string) ($item['homeTeam'] ?? ''),
                awayTeam: (string) ($item['awayTeam'] ?? ''),
                kickoffAt: $kickoff,
                venue: isset($item['venue']) && is_string($item['venue']) ? $item['venue'] : null,
                round: isset($item['round']) && is_string($item['round']) ? $item['round'] : null,
                isPlayoff: (bool) ($item['isPlayoff'] ?? false),
            );
        }

        return $rows;
    }

    public function clear(Uuid $matchSourceId): void
    {
        $this->requestStack->getSession()->remove($this->key($matchSourceId));
    }

    private function key(Uuid $matchSourceId): string
    {
        return self::KEY_PREFIX.$matchSourceId->toRfc4122();
    }
}
