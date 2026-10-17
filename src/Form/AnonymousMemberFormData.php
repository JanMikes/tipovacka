<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\UniqueNickname;
use Symfony\Component\Validator\Constraints as Assert;

final class AnonymousMemberFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím jméno.')]
    #[Assert\Length(max: 100, maxMessage: 'Jméno nesmí být delší než {{ limit }} znaků.')]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Zadejte prosím příjmení.')]
    #[Assert\Length(max: 100, maxMessage: 'Příjmení nesmí být delší než {{ limit }} znaků.')]
    public string $lastName = '';

    #[Assert\When(
        expression: 'this.nickname != null and this.nickname != ""',
        constraints: [
            new Assert\Sequentially([
                new Assert\Length(
                    min: 3,
                    max: 30,
                    minMessage: 'Přezdívka musí mít alespoň {{ limit }} znaky. Nebo ji nechte prázdnou a zobrazí se celé jméno.',
                    maxMessage: 'Přezdívka nesmí být delší než {{ limit }} znaků.',
                ),
                new Assert\Regex(
                    pattern: '/^[A-Za-z0-9_.\-]+$/',
                    message: 'Přezdívka smí obsahovat jen písmena bez diakritiky, číslice, tečku, pomlčku a podtržítko — bez mezer. Např. petr.novak.',
                ),
                new UniqueNickname(),
            ]),
        ],
    )]
    public ?string $nickname = null;
}
