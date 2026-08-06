<?php

declare(strict_types=1);

namespace App\Command\InviteToGlobalCompetition;

use Symfony\Component\Uid\Uuid;

/**
 * „Pošli kamarádovi odkaz na tuhle globální soutěž." The global twin of
 * {@see \App\Command\SendCompetitionInvitation\SendCompetitionInvitationCommand}, and
 * deliberately much less: it persists nothing, grants nothing and expires never — it puts
 * the public invitation page into somebody's mailbox. The seat is bought on arrival.
 */
final readonly class InviteToGlobalCompetitionCommand
{
    public function __construct(
        public Uuid $inviterId,
        public Uuid $competitionId,
        public string $email,
    ) {
    }
}
