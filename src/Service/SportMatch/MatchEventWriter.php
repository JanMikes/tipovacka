<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

use App\Entity\MatchEvent;
use App\Entity\SportMatch;
use App\Enum\MatchSide;
use App\Repository\MatchEventRepository;
use App\Repository\PlayerRepository;
use App\Service\Identity\ProvideIdentity;
use App\Value\MatchEventInput;

/**
 * Persists the score-entry event sheet: every save REPLACES the match's events
 * (delete + insert) and lazily creates missing players in the source's roster
 * pool (team name derived from the event side).
 */
final readonly class MatchEventWriter
{
    public function __construct(
        private MatchEventRepository $matchEventRepository,
        private PlayerRepository $playerRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * @param list<MatchEventInput> $events
     */
    public function replace(SportMatch $sportMatch, array $events, \DateTimeImmutable $now): void
    {
        $this->matchEventRepository->deleteByMatch($sportMatch->id);

        // Memoize per side and lowercased name (matching findOrCreate's
        // case-insensitive lookup): findOrCreate queries the database, which cannot
        // see players persisted-but-not-flushed earlier in this same save.
        $players = [];

        foreach ($events as $event) {
            $teamName = MatchSide::Home === $event->side ? $sportMatch->homeTeam : $sportMatch->awayTeam;
            $playerName = trim($event->playerName);
            $memoKey = mb_strtolower($playerName);

            $players[$event->side->value][$memoKey] ??= $this->playerRepository->findOrCreate(
                matchSource: $sportMatch->matchSource,
                teamName: $teamName,
                name: $playerName,
                identity: $this->identity,
                now: $now,
            );

            $this->matchEventRepository->save(new MatchEvent(
                id: $this->identity->next(),
                sportMatch: $sportMatch,
                type: $event->type,
                side: $event->side,
                minute: $event->minute,
                player: $players[$event->side->value][$memoKey],
                createdAt: $now,
            ));
        }
    }
}
