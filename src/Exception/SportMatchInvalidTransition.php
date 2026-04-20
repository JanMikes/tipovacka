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
        return new self(sprintf(
            'Neplatný přechod stavu zápasu: z "%s" nelze provést "%s".',
            $currentState->value,
            $attemptedTransition,
        ));
    }
}
