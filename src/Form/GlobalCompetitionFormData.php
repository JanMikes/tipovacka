<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Enum\CompetitionMonetization;
use Symfony\Component\Validator\Constraints as Assert;

final class GlobalCompetitionFormData
{
    /** Only used on create — the source is fixed once the competition exists. */
    #[Assert\NotNull(message: 'Vyberte prosím zdroj zápasů.', groups: ['create'])]
    public ?MatchSource $matchSource = null;

    #[Assert\NotBlank(message: 'Zadejte prosím název soutěže.', groups: ['create'])]
    #[Assert\Length(max: 160, maxMessage: 'Název soutěže nesmí být delší než {{ limit }} znaků.')]
    public string $name = '';

    #[Assert\NotNull(message: 'Zadejte prosím vstupné.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Vstupné nesmí být záporné.')]
    public int $entryFeeCredits = 0;

    public CompetitionMonetization $monetization = CompetitionMonetization::None;

    public static function fromCompetition(Competition $competition): self
    {
        $formData = new self();
        $formData->matchSource = $competition->matchSource;
        $formData->name = $competition->name;
        $formData->entryFeeCredits = $competition->entryFeeCredits;
        $formData->monetization = $competition->monetization;

        return $formData;
    }
}
