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
 *
 * `GlobalCompetition` is the odd one out and deliberately so: it carries NO secret at
 * all. Its „token" is the competition's own UUID, because a global competition is public
 * — anybody may browse to it and join by paying the entry fee. The link is a shortcut to
 * that page for somebody who does not have an account yet, not a key to anything, and the
 * join it ends in is the ordinary paid one.
 */
enum InvitationKind: string
{
    case Email = 'email';
    case ShareableLink = 'shareable_link';
    case Pin = 'pin';
    case GlobalCompetition = 'global';
}
