<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\SportMatch;

/**
 * Two or more fixtures that look like the SAME real-world match arriving from
 * different zdroje — the classic case being a zápas a user also entered by hand
 * into „Moje zápasy" while a curated zdroj already carries it.
 *
 * There is no cross-source identity for a fixture: `externalId` is unique per
 * zdroj, and the same club resolves to a global directory Team in a curated
 * zdroj but a local one in a private zdroj. So this is deliberately a
 * HEURISTIC on team names and kickoff proximity, and deliberately only a
 * warning — a cup replay or a two-legged tie on adjacent days is a legitimate
 * pair the user must stay free to keep.
 *
 * Not a `readonly` class: the derived properties are virtual `get` hooks, which
 * PHP does not allow on readonly declarations.
 */
final class DuplicateFixtureGroup
{
    /**
     * @param list<SportMatch> $matches at least two, kickoff-ordered
     */
    public function __construct(
        public private(set) array $matches,
    ) {
    }

    public string $label {
        get {
            $first = $this->matches[0];

            return sprintf('%s – %s', $first->homeTeam->name, $first->awayTeam->name);
        }
    }

    /**
     * The zdroje the duplicates came from, in order — what the warning names so
     * the user knows which layer to drop.
     *
     * @var list<string>
     */
    public array $sourceNames {
        get {
            $names = [];

            foreach ($this->matches as $match) {
                $names[$match->matchSource->name] = true;
            }

            return array_keys($names);
        }
    }
}
