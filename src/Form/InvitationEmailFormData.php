<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class InvitationEmailFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím e-mailovou adresu.')]
    #[Assert\Email(message: 'Zadejte prosím platnou e-mailovou adresu.')]
    #[Assert\Length(max: 180, maxMessage: 'E-mail je příliš dlouhý.')]
    public string $email = '';
}
