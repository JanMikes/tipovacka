<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Competition;
use App\Enum\CompetitionMatchSelectionMode;
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

    public bool $hideOthersTipsBeforeDeadline = false;

    public ?\DateTimeImmutable $tipsDeadline = null;

    /** Selected match source UUID (create form only). */
    public ?string $matchSourceId = null;

    public CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All;

    public bool $includePlayoff = true;

    /** @var list<string> selected sport match UUIDs (Subset mode, create form only) */
    public array $selectedMatchIds = [];

    public static function fromCompetition(Competition $competition): self
    {
        $formData = new self();
        $formData->name = $competition->name;
        $formData->description = $competition->description;
        $formData->withPin = null !== $competition->pin;
        $formData->hideOthersTipsBeforeDeadline = $competition->hideOthersTipsBeforeDeadline;
        $formData->tipsDeadline = $competition->tipsDeadline;
        $formData->matchSourceId = $competition->matchSource->id->toRfc4122();
        $formData->selectionMode = $competition->selectionMode;
        $formData->includePlayoff = $competition->includePlayoff;

        return $formData;
    }
}
