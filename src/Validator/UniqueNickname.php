<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UniqueNickname extends Constraint
{
    public string $message = 'Tato přezdívka je již obsazena.';
}
