<?php

declare(strict_types=1);

namespace App\Command\SendBulkGroupInvitations;

/**
 * @phpstan-type Invalid array{email: string, reason: string}
 */
final readonly class BulkInvitationResult
{
    /**
     * @param list<string>  $invited        addresses that received a fresh invitation
     * @param list<string>  $alreadyMembers skipped — email belongs to an active group member
     * @param list<string>  $alreadyPending skipped — an unexpired, unaccepted invitation already exists
     * @param list<Invalid> $invalid        entries that failed validation (malformed email, too long)
     */
    public function __construct(
        public array $invited,
        public array $alreadyMembers,
        public array $alreadyPending,
        public array $invalid,
    ) {
    }
}
