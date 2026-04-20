<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class JoinByPinFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím PIN skupiny.')]
    #[Assert\Regex(
        pattern: '/^\d{8}$/',
        message: 'PIN musí mít přesně 8 číslic.',
    )]
    public string $pin = '';
}
