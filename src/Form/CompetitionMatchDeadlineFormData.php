<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompetitionMatchSetting;
use Symfony\Component\Validator\Constraints as Assert;

final class CompetitionMatchDeadlineFormData
{
    public ?\DateTimeImmutable $deadline = null;

    /** Admin-only — the field is built only when the form gets `with_opening`. */
    public ?\DateTimeImmutable $opensAt = null;

    #[Assert\Length(max: CompetitionMatchSetting::OPENING_NOTE_MAX_LENGTH)]
    public ?string $openingNote = null;

    public static function fromSetting(?CompetitionMatchSetting $setting): self
    {
        $formData = new self();
        $formData->deadline = $setting?->deadline;
        $formData->opensAt = $setting?->opensAt;
        $formData->openingNote = $setting?->openingNote;

        return $formData;
    }
}
