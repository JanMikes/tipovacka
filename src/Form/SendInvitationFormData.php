<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class SendInvitationFormData
{
    #[Assert\NotBlank(message: 'Zadejte e-mailovou adresu člověka, kterého chcete pozvat.')]
    #[Assert\Email(message: 'Tohle není platná e-mailová adresa. Zkontrolujte zavináč a doménu, např. jmeno@seznam.cz.')]
    #[Assert\Length(max: 180, maxMessage: 'E-mailová adresa může mít nejvýš {{ limit }} znaků.')]
    public string $email = '';
}
