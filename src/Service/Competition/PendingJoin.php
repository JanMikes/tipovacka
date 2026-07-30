<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Enum\InvitationKind;

/**
 * „This visitor is about to join a competition" — the kind of proof they arrived with
 * and the secret that proves it (invitation token, shareable-link token or PIN).
 */
final readonly class PendingJoin
{
    public function __construct(
        public InvitationKind $kind,
        public string $token,
    ) {
    }
}
