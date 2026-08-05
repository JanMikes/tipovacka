<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\SportMatch;

/**
 * What a set of {@see CompetitionSourceSpec} layers would actually add up to —
 * the resolved fixture list plus the two things a person composing a soutěž
 * from several zdroje needs to see: how much they have built, and where they
 * have accidentally taken the same fixture twice.
 *
 * Not a `readonly` class: the derived properties are virtual `get` hooks, which
 * PHP does not allow on readonly declarations.
 */
final class ScopeDraft
{
    /**
     * @param list<SportMatch>            $matches    kickoff-ordered, deduplicated by identity
     * @param list<DuplicateFixtureGroup> $duplicates fixtures that look like the same real-world match
     */
    public function __construct(
        public private(set) array $matches,
        public private(set) array $duplicates,
    ) {
    }

    public int $matchCount {
        get => count($this->matches);
    }

    public ?\DateTimeImmutable $firstKickoff {
        get => ($this->matches[0] ?? null)?->kickoffAt;
    }

    public ?\DateTimeImmutable $lastKickoff {
        get => ($this->matches[count($this->matches) - 1] ?? null)?->kickoffAt;
    }

    public bool $hasDuplicates {
        get => [] !== $this->duplicates;
    }
}
