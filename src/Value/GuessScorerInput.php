<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\Player;
use App\Enum\MatchSide;
use App\Exception\InvalidScorerName;

/**
 * One scorer tip as submitted by a player: the team side of this match plus a
 * player name. The player is resolved (or created, same as the organizer's
 * score-entry sheet) in the source's roster pool — team name follows from the
 * side, so the (source, teamName, name) pool key is always well-formed.
 *
 * The name is normalized (trimmed) and guarded here: blank or over-long names
 * throw a 422 domain exception, so every call site (live form, batch pages,
 * on-behalf forms) is covered without repeating the check.
 */
final readonly class GuessScorerInput
{
    public string $playerName;

    public function __construct(
        public MatchSide $side,
        string $playerName,
    ) {
        $trimmed = trim($playerName);

        if ('' === $trimmed) {
            throw InvalidScorerName::blank();
        }

        if (mb_strlen($trimmed) > Player::NAME_MAX_LENGTH) {
            throw InvalidScorerName::tooLong();
        }

        $this->playerName = $trimmed;
    }
}
