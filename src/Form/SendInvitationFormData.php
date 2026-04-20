<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class SendInvitationFormData
{
    #[Assert\NotBlank(message: 'Zadejte e-mailovou adresu.')]
    #[Assert\Email(message: 'E-mailová adresa není platná.')]
    #[Assert\Length(max: 180, maxMessage: 'E-mailová adresa je příliš dlouhá.')]
    public string $email = '';
}
