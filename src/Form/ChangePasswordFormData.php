<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordFormData
{
    public const int MIN_LENGTH = 8;

    #[Assert\NotBlank(message: 'Zadejte prosím současné heslo.')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'Zadejte prosím nové heslo.')]
    #[Assert\Length(
        min: self::MIN_LENGTH,
        max: 4096,
        minMessage: 'Nové heslo musí mít alespoň {{ limit }} znaků — přidejte prosím další znaky.',
        maxMessage: 'Nové heslo může mít nejvýše {{ limit }} znaků.',
    )]
    #[Assert\NotEqualTo(
        propertyPath: 'currentPassword',
        message: 'Nové heslo je stejné jako současné — zvolte prosím jiné.',
    )]
    public ?string $newPassword = null;
}
