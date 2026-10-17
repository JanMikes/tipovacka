<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\SportMatchState;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class SportMatchInvalidTransition extends \DomainException
{
    public static function from(SportMatchState $currentState, string $attemptedTransition): self
    {
        $state = match ($currentState) {
            SportMatchState::Scheduled => 'Zápas je naplánovaný',
            SportMatchState::Live => 'Zápas právě probíhá',
            SportMatchState::Finished => 'Zápas už skončil',
            SportMatchState::Postponed => 'Zápas je odložený',
            SportMatchState::Cancelled => 'Zápas je zrušený',
        };
        $action = match ($attemptedTransition) {
            'live' => 'zahájit',
            'live_score' => 'zapsat průběžné skóre',
            'postpone' => 'odložit',
            'reschedule' => 'přesunout zpět do plánu',
            'cancel' => 'zrušit',
            default => 'takto změnit',
        };

        return new self(sprintf(
            '%s, a tak ho teď nelze %s. Obnovte stránku — stav zápasu se mezitím nejspíš změnil.',
            $state,
            $action,
        ));
    }
}
