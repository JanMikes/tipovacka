<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How somebody arrived at a competition they may join.
 *
 * Only `Email` proves the visitor owns the mailbox it was addressed to — which is what
 * lets an e-mail invitation verify the account when it is accepted. A shareable link and
 * a PIN are secrets anybody may pass on, so they prove nothing about identity: they are
 * remembered as a pending join and honoured once the account verifies itself.
 */
enum InvitationKind: string
{
    case Email = 'email';
    case ShareableLink = 'shareable_link';
    case Pin = 'pin';
}
