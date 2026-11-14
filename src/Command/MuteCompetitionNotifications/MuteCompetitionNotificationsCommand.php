<?php

declare(strict_types=1);

namespace App\Command\MuteCompetitionNotifications;

use Symfony\Component\Uid\Uuid;

/**
 * The player silences (or un-silences) one of their soutěže — see
 * {@see \App\Entity\Membership::$notificationsMuted} for what a mute covers.
 */
final readonly class MuteCompetitionNotificationsCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
        public bool $muted,
    ) {
    }
}
