<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\UniqueNickname;
use App\Validator\UniqueUserEmail;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormData
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím e-mailovou adresu.'),
        new Assert\Email(message: 'Zadejte prosím platnou e-mailovou adresu.'),
        new UniqueUserEmail(),
    ])]
    public string $email = '';

    #[Assert\NotBlank(message: 'Zadejte prosím jméno.')]
    #[Assert\Length(max: 100, maxMessage: 'Jméno nesmí být delší než {{ limit }} znaků.')]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Zadejte prosím příjmení.')]
    #[Assert\Length(max: 100, maxMessage: 'Příjmení nesmí být delší než {{ limit }} znaků.')]
    public string $lastName = '';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím přezdívku.'),
        new Assert\Length(
            min: 3,
            max: 30,
            minMessage: 'Přezdívka musí mít alespoň {{ limit }} znaky.',
            maxMessage: 'Přezdívka nesmí být delší než {{ limit }} znaků.',
        ),
        new Assert\Regex(
            pattern: '/^[A-Za-z0-9_.\-]+$/',
            message: 'Přezdívka smí obsahovat pouze písmena, čísla, podtržítko, tečku a pomlčku.',
        ),
        new UniqueNickname(),
    ])]
    public string $nickname = '';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím heslo.'),
        new Assert\Length(min: 8, minMessage: 'Heslo musí mít alespoň {{ limit }} znaků.'),
    ])]
    public ?string $password = null;
}
