<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(500)]
final class RuleNotRegistered extends \RuntimeException
{
    public static function withIdentifier(string $ruleIdentifier): self
    {
        return new self(sprintf('Pravidlo s identifikátorem "%s" není registrováno.', $ruleIdentifier));
    }
}
