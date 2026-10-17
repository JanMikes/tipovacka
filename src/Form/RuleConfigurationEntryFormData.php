<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\Scoring\RulePresetProvider;
use Symfony\Component\Validator\Constraints as Assert;

final class RuleConfigurationEntryFormData
{
    public bool $enabled = true;

    #[Assert\NotNull(message: 'Zadejte prosím počet bodů (0 až '.RulePresetProvider::MAX_POINTS.').')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Počet bodů nemůže být záporný. Zadejte 0 až '.RulePresetProvider::MAX_POINTS.'.')]
    #[Assert\LessThanOrEqual(value: RulePresetProvider::MAX_POINTS, message: 'Jedno pravidlo může dát nejvýš {{ compared_value }} bodů. Zadejte 0 až {{ compared_value }}.')]
    public int $points = 0;
}
