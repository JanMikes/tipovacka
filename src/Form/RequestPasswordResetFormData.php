<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class RequestPasswordResetFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím e-mail, se kterým jste se zaregistrovali.')]
    #[Assert\Email(message: 'Tohle nevypadá jako e-mailová adresa. Zkontrolujte ji — má tvar jmeno@domena.cz.')]
    #[Assert\Length(max: 180, maxMessage: 'E-mail může mít nejvýše {{ limit }} znaků.')]
    public string $email = '';
}
