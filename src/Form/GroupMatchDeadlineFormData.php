<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GroupMatchSetting;

final class GroupMatchDeadlineFormData
{
    public ?\DateTimeImmutable $deadline = null;

    public static function fromSetting(?GroupMatchSetting $setting): self
    {
        $formData = new self();
        $formData->deadline = $setting?->deadline;

        return $formData;
    }
}
