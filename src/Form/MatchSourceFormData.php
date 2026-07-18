<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MatchSource;
use Symfony\Component\Validator\Constraints as Assert;

final class MatchSourceFormData
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

    public static function fromMatchSource(MatchSource $matchSource): self
    {
        $formData = new self();
        $formData->name = $matchSource->name;
        $formData->description = $matchSource->description;
        $formData->startAt = $matchSource->startAt;
        $formData->endAt = $matchSource->endAt;
        $formData->creationPin = $matchSource->creationPin;

        return $formData;
    }
}
