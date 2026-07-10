<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class AdjustCreditsFormData
{
    #[Assert\NotNull(message: 'Zadejte prosím počet kreditů.')]
    #[Assert\NotEqualTo(value: 0, message: 'Úprava kreditů nesmí být nulová.')]
    #[Assert\Range(
        min: -100000,
        max: 100000,
        notInRangeMessage: 'Úprava musí být mezi {{ min }} a {{ max }} kredity.',
    )]
    public ?int $amount = null;

    #[Assert\NotBlank(message: 'Poznámka je povinná — proč kredity přidáváte?')]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Poznámka nesmí být delší než {{ limit }} znaků.',
    )]
    public ?string $note = null;
}
