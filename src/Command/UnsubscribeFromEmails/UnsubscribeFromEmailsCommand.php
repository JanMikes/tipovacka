<?php

declare(strict_types=1);

namespace App\Command\UnsubscribeFromEmails;

use App\Value\UnsubscribeScope;
use Symfony\Component\Uid\Uuid;

/**
 * What an unsubscribe link does — switch off one notification type's e-mail,
 * silence one soutěž, or stop notification e-mail altogether, depending on the
 * {@see UnsubscribeScope}. `$resubscribe` is the „vrátit zpět" of the very same
 * thing, offered right after on the landing page.
 */
final readonly class UnsubscribeFromEmailsCommand
{
    public function __construct(
        public Uuid $userId,
        public UnsubscribeScope $scope,
        public bool $resubscribe = false,
    ) {
    }
}
