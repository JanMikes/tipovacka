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

    #[Assert\NotNull]
    #[Assert\Range(
        min: 0,
        max: 1440,
        notInRangeMessage: 'Předstih musí být mezi {{ min }} a {{ max }} minutami.',
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
