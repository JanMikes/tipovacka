<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class SetFinalScoreFormData
{
    #[Assert\NotNull(message: 'Zadejte prosím skóre domácích.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $homeScore = null;

    #[Assert\NotNull(message: 'Zadejte prosím skóre hostů.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $awayScore = null;
}
