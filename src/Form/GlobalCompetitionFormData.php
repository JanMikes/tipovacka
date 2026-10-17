<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Enum\CompetitionMonetization;
use Symfony\Component\Validator\Constraints as Assert;

final class GlobalCompetitionFormData
{
    public const int NAME_MAX_LENGTH = 160;

    /** Only used on create — the source is fixed once the competition exists. */
    #[Assert\NotNull(message: 'Vyberte zdroj zápasů, nad kterým soutěž poběží — později už ho změnit nejde.', groups: ['create'])]
    public ?MatchSource $matchSource = null;

    #[Assert\NotBlank(message: 'Zadejte název soutěže — uvidí ho hráči v přehledu soutěží.', groups: ['create'])]
    #[Assert\Length(max: self::NAME_MAX_LENGTH, maxMessage: 'Název soutěže může mít nejvýše {{ limit }} znaků — zkraťte ho prosím.')]
    public string $name = '';

    /** Only used on create — like the name, the description is edited on `competition_edit` afterwards. */
    #[Assert\Length(
        max: Competition::DESCRIPTION_MAX_LENGTH,
        maxMessage: 'Popis soutěže může mít nejvýše {{ limit }} znaků — zkraťte ho prosím.',
    )]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Zadejte vstupné v kreditech — 0 znamená zdarma.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Vstupné nemůže být záporné. Zadejte 0 (zdarma) nebo kladný počet kreditů.')]
    public int $entryFeeCredits = 0;

    public CompetitionMonetization $monetization = CompetitionMonetization::None;

    public static function fromCompetition(Competition $competition): self
    {
        $formData = new self();
        $formData->matchSource = $competition->headlineSource;
        $formData->name = $competition->name;
        $formData->description = $competition->description;
        $formData->entryFeeCredits = $competition->entryFeeCredits;
        $formData->monetization = $competition->monetization;

        return $formData;
    }
}
