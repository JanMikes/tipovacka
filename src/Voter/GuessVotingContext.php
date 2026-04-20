<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\SportMatch;
use Symfony\Component\Uid\Uuid;

/**
 * Carrier DTO for Guess authorization decisions that need both a SportMatch
 * and the Group scope (membership) under which the guess is being performed.
 */
final readonly class GuessVotingContext
{
    public function __construct(
        public SportMatch $sportMatch,
        public Uuid $groupId,
    ) {
    }
}
