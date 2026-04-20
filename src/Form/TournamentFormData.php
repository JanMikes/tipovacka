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

    #[Assert\Length(
        min: 4,
        max: 8,
        minMessage: 'PIN musí mít alespoň {{ limit }} znaky.',
        maxMessage: 'PIN nesmí být delší než {{ limit }} znaků.',
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9]+$/',
        message: 'PIN smí obsahovat jen písmena a číslice.',
    )]
    public ?string $creationPin = null;

    public static function fromTournament(Tournament $tournament): self
    {
        $formData = new self();
        $formData->name = $tournament->name;
        $formData->description = $tournament->description;
        $formData->startAt = $tournament->startAt;
        $formData->endAt = $tournament->endAt;
        $formData->creationPin = $tournament->creationPin;

        return $formData;
    }
}
