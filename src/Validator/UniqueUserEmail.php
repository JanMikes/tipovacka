<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UniqueUserEmail extends Constraint
{
    public string $message = 'Tento e-mail je již zaregistrován.';
}
