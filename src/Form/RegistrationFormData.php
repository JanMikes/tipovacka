<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím e-mailovou adresu.')]
    #[Assert\Email(message: 'Zadejte prosím platnou e-mailovou adresu.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Zadejte prosím přezdívku.')]
    #[Assert\Length(
        min: 3,
        max: 30,
        minMessage: 'Přezdívka musí mít alespoň {{ limit }} znaky.',
        maxMessage: 'Přezdívka nesmí být delší než {{ limit }} znaků.',
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_.\-]+$/',
        message: 'Přezdívka smí obsahovat pouze písmena, čísla, podtržítko, tečku a pomlčku.',
    )]
    public string $nickname = '';

    #[Assert\NotBlank(message: 'Zadejte prosím heslo.')]
    #[Assert\Length(min: 8, minMessage: 'Heslo musí mít alespoň {{ limit }} znaků.')]
    public string $password = '';
}
