<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;

/**
 * Carrier DTO for "fill guess on behalf of another member" authorization.
 *
 * The acting user (token user) is the manager; the target user is the member
 * whose slot is being filled. The voter checks the acting user is owner/admin
 * of the competition and that target is an active member.
 */
final readonly class GuessOnBehalfContext
{
    public function __construct(
        public Competition $competition,
        public SportMatch $sportMatch,
        public User $targetUser,
    ) {
    }
}
