<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class RuleConfigurationEntryFormData
{
    public bool $enabled = true;

    #[Assert\NotNull(message: 'Zadejte prosím počet bodů.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Počet bodů nesmí být záporný.')]
    #[Assert\LessThanOrEqual(value: 1000, message: 'Počet bodů nesmí přesáhnout {{ compared_value }}.')]
    public int $points = 0;
}
