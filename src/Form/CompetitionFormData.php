<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Competition;
use Symfony\Component\Validator\Constraints as Assert;

final class CompetitionFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím název soutěže.')]
    #[Assert\Length(
        max: 160,
        maxMessage: 'Název soutěže nesmí být delší než {{ limit }} znaků.',
    )]
    public string $name = '';

    public ?string $description = null;

    public bool $withPin = false;

    public ?string $matchSourceCreationPin = null;

    public bool $hideOthersTipsBeforeDeadline = false;

    public ?\DateTimeImmutable $tipsDeadline = null;

    public static function fromCompetition(Competition $competition): self
    {
        $formData = new self();
        $formData->name = $competition->name;
        $formData->description = $competition->description;
        $formData->withPin = null !== $competition->pin;
        $formData->hideOthersTipsBeforeDeadline = $competition->hideOthersTipsBeforeDeadline;
        $formData->tipsDeadline = $competition->tipsDeadline;

        return $formData;
    }
}
