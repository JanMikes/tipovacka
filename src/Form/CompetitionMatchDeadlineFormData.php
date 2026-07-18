<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompetitionMatchSetting;

final class CompetitionMatchDeadlineFormData
{
    public ?\DateTimeImmutable $deadline = null;

    public static function fromSetting(?CompetitionMatchSetting $setting): self
    {
        $formData = new self();
        $formData->deadline = $setting?->deadline;

        return $formData;
    }
}
