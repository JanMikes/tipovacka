<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class JoinByPinFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím PIN soutěže — 8 číslic, které vám dal organizátor.')]
    #[Assert\Regex(
        pattern: '/^\d{8}$/',
        message: 'PIN tvoří přesně 8 číslic (jen čísla 0–9). Zkontrolujte, že jste opsali všechny.',
    )]
    public string $pin = '';
}
