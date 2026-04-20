<?php

declare(strict_types=1);

namespace App\Service\Invitation;

enum InvitationContextStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Accepted = 'accepted';
    case TournamentFinished = 'tournament_finished';
}
