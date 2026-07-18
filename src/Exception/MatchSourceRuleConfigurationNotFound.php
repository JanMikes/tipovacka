<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(404)]
final class MatchSourceRuleConfigurationNotFound extends \RuntimeException
{
    public static function forMatchSourceAndRule(Uuid $matchSourceId, string $ruleIdentifier): self
    {
        return new self(sprintf(
            'Konfigurace pravidla "%s" pro turnaj "%s" nebyla nalezena.',
            $ruleIdentifier,
            $matchSourceId->toRfc4122(),
        ));
    }
}
