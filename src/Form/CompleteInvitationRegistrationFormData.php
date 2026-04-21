<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class CompleteInvitationRegistrationFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím heslo.')]
    #[Assert\Length(
        min: 8,
        max: 4096,
        minMessage: 'Heslo musí mít alespoň {{ limit }} znaků.',
    )]
    #[Assert\PasswordStrength(
        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
        message: 'Heslo je příliš slabé. Zvolte prosím silnější heslo.',
    )]
    public ?string $password = null;
}
