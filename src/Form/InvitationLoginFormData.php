<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class InvitationLoginFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím heslo.')]
    public string $password = '';
}
