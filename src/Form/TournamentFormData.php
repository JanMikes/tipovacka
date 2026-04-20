<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tournament;
use Symfony\Component\Validator\Constraints as Assert;

final class TournamentFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím název turnaje.')]
    #[Assert\Length(
        max: 160,
        maxMessage: 'Název turnaje nesmí být delší než {{ limit }} znaků.',
    )]
    public string $name = '';

    public ?string $description = null;

    public ?\DateTimeImmutable $startAt = null;

    public ?\DateTimeImmutable $endAt = null;

    public static function fromTournament(Tournament $tournament): self
    {
        $formData = new self();
        $formData->name = $tournament->name;
        $formData->description = $tournament->description;
        $formData->startAt = $tournament->startAt;
        $formData->endAt = $tournament->endAt;

        return $formData;
    }
}
