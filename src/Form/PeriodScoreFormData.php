<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One period row (poločas / třetina) of the score-entry form.
 */
final class PeriodScoreFormData
{
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $homeScore = null;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $awayScore = null;

    public bool $isEmpty {
        get => null === $this->homeScore && null === $this->awayScore;
    }

    public bool $isComplete {
        get => null !== $this->homeScore && null !== $this->awayScore;
    }
}
