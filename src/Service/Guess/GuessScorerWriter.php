<?php

declare(strict_types=1);

namespace App\Service\Guess;

use App\Entity\Guess;
use App\Entity\GuessScorer;
use App\Entity\Player;
use App\Enum\MatchSide;
use App\Exception\TooManyGuessScorers;
use App\Repository\PlayerRepository;
use App\Service\Identity\ProvideIdentity;
use App\Value\GuessScorerInput;

/**
 * Persists a guess's scorer tips with full-replace semantics: the submitted
 * list becomes the guess's exact scorer set. Free-typed names lazily create a
 * Player in the source's roster pool (case-insensitive, same as the organizer's
 * score-entry sheet via MatchEventWriter).
 *
 * The replace is a DIFF against the current collection (keep / remove / add by
 * player id) rather than clear-and-recreate — Doctrine flushes inserts before
 * orphan deletions, so re-adding a kept player would trip the
 * (guess_id, player_id) unique constraint.
 */
final readonly class GuessScorerWriter
{
    public function __construct(
        private PlayerRepository $playerRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * @param list<GuessScorerInput> $inputs
     */
    public function replace(Guess $guess, array $inputs, \DateTimeImmutable $now): void
    {
        $match = $guess->sportMatch;

        // Memoize per side and lowercased name (matching findOrCreate's
        // case-insensitive lookup, same pattern as MatchEventWriter).
        /** @var array<string, array<string, Player>> $memo */
        $memo = [];
        /** @var array<string, array{Player, MatchSide}> $desired player UUID → [Player, submitted side] */
        $desired = [];

        foreach ($inputs as $input) {
            $teamName = MatchSide::Home === $input->side ? $match->homeTeam : $match->awayTeam;
            $memoKey = mb_strtolower($input->playerName);

            $player = $memo[$input->side->value][$memoKey] ??= $this->playerRepository->findOrCreate(
                matchSource: $match->matchSource,
                teamName: $teamName,
                name: $input->playerName,
                identity: $this->identity,
                now: $now,
            );

            $desired[$player->id->toRfc4122()] = [$player, $input->side];
        }

        if (count($desired) > GuessScorer::MAX_PER_GUESS) {
            throw TooManyGuessScorers::create();
        }

        foreach ($guess->scorers->toArray() as $existing) {
            $key = $existing->player->id->toRfc4122();

            if (isset($desired[$key])) {
                unset($desired[$key]); // Kept — nothing to do.

                continue;
            }

            $guess->removeScorer($existing, $now);
        }

        foreach ($desired as [$player, $side]) {
            $guess->addScorer(new GuessScorer(
                id: $this->identity->next(),
                guess: $guess,
                player: $player,
                side: $side,
                createdAt: $now,
            ));
        }
    }

    /**
     * The current scorer tips of a guess as inputs — used by call sites with a
     * partial UI (batch pages, on-behalf forms) to pass the untouched scorer
     * part through a full-replace update.
     *
     * @return list<GuessScorerInput>
     */
    public function inputsFor(Guess $guess): array
    {
        $inputs = [];

        foreach ($guess->scorers as $scorer) {
            $inputs[] = new GuessScorerInput(
                side: $scorer->side,
                playerName: $scorer->player->name,
            );
        }

        return $inputs;
    }
}
