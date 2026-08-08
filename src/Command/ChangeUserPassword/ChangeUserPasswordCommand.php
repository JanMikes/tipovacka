<?php

declare(strict_types=1);

namespace App\Command\ChangeUserPassword;

use Symfony\Component\Uid\Uuid;

/**
 * A signed-in user changing their own password.
 *
 * Unlike ResetUserPasswordCommand — which is reached only by proving ownership of the
 * mailbox — this one has no token behind it, so the current password IS the proof.
 */
final readonly class ChangeUserPasswordCommand
{
    public function __construct(
        public Uuid $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
