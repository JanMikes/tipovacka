<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Competition;
use Symfony\Component\Validator\Constraints as Assert;

final class PremiumSettingsFormData
{
    public bool $showDistribution = false;

    public bool $showOthersTips = false;

    public bool $allowTipChanges = false;

    #[Assert\NotNull(message: 'Zadejte předstih v minutách, např. 60.')]
    #[Assert\Range(
        min: 0,
        max: 1440,
        notInRangeMessage: 'Předstih musí být mezi {{ min }} a {{ max }} minutami (nejvýš 24 hodin). Zadejte např. 60.',
    )]
    public int $tipChangeOffsetMinutes = 60;

    public static function fromCompetition(Competition $competition): self
    {
        $formData = new self();
        $formData->showDistribution = $competition->premiumShowDistribution;
        $formData->showOthersTips = $competition->premiumShowOthersTips;
        $formData->allowTipChanges = $competition->premiumAllowTipChanges;
        $formData->tipChangeOffsetMinutes = $competition->tipChangeOffsetMinutes;

        return $formData;
    }
}
