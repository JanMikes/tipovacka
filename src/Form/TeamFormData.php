<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use App\Entity\Team;
use App\Value\Country;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    /** ISO 3166-1 alpha-2 picked from the Country directory; the picker only ever offers those. */
    #[Assert\Choice(callback: [Country::class, 'codes'], message: 'Vyberte prosím zemi ze seznamu.')]
    public ?string $country = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Zadejte barvu v HEX, např. #EE1C25.')]
    public ?string $brandColor = null;

    /**
     * A newly uploaded logo, normalised to WebP by TeamLogoStorage. Never holds the
     * current logo — an edit that leaves this empty keeps whatever the team has.
     */
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
        maxSizeMessage: 'Logo je příliš velké ({{ size }} {{ suffix }}). Maximum je {{ limit }} {{ suffix }}.',
        mimeTypesMessage: 'Nahrajte prosím obrázek ve formátu PNG, JPG, WebP nebo GIF.',
    )]
    public ?UploadedFile $logoFile = null;

    /** „Odebrat logo" — clears the stored logo (and deletes the file) on save. */
    public bool $removeLogo = false;

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
