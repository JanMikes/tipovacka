<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class AdjustCreditsFormData
{
    /** Shared by the constraint and the input's min/max, so the browser and the server agree. */
    public const int MAX_ADJUSTMENT = 100000;

    #[Assert\NotNull(message: 'Zadejte počet kreditů — kladné číslo kredity přidá, záporné odebere.')]
    #[Assert\NotEqualTo(value: 0, message: 'Úprava o 0 kreditů nic nezmění. Zadejte kladné číslo pro přidání, nebo záporné pro odebrání.')]
    #[Assert\Range(
        min: -self::MAX_ADJUSTMENT,
        max: self::MAX_ADJUSTMENT,
        notInRangeMessage: 'Jedna úprava může být nejvýše {{ max }} kreditů oběma směry (od {{ min }} do {{ max }}). Větší částku rozdělte do více úprav.',
    )]
    public ?int $amount = null;

    #[Assert\NotBlank(message: 'Napište důvod úpravy — uživatel ho uvidí v historii transakcí.')]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Poznámka může mít nejvýše {{ limit }} znaků — zkraťte ji prosím.',
    )]
    public ?string $note = null;
}
