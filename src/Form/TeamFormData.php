<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use App\Entity\Team;
use Symfony\Component\Validator\Constraints as Assert;

final class TeamFormData
{
    // Rendered only on create (with_sport); on edit it's prefilled from the team so
    // the NotNull still passes even though the field is absent (sport is immutable).
    #[Assert\NotNull(message: 'Vyberte prosím sport.')]
    public ?Sport $sport = null;

    #[Assert\NotBlank(message: 'Zadejte prosím název týmu.')]
    #[Assert\Length(max: Team::NAME_MAX_LENGTH, maxMessage: 'Název týmu nesmí být delší než {{ limit }} znaků.')]
    public string $name = '';

    #[Assert\Length(max: Team::SHORT_NAME_MAX_LENGTH, maxMessage: 'Zkratka nesmí být delší než {{ limit }} znaků.')]
    public ?string $shortName = null;

    #[Assert\Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Zadejte dvoupísmenný kód země, např. CZ.')]
    public ?string $country = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Zadejte barvu v HEX, např. #EE1C25.')]
    public ?string $brandColor = null;

    public static function fromTeam(Team $team): self
    {
        $formData = new self();
        $formData->sport = $team->sport;
        $formData->name = $team->name;
        $formData->shortName = $team->shortName;
        $formData->country = $team->country;
        $formData->brandColor = $team->brandColor;

        return $formData;
    }
}
