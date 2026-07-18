<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MatchSource;
use App\Entity\Sport;
use Symfony\Component\Validator\Constraints as Assert;

final class MatchSourceFormData
{
    #[Assert\NotNull(message: 'Vyberte prosím sport.')]
    public ?Sport $sport = null;

    #[Assert\NotBlank(message: 'Zadejte prosím název zdroje zápasů.')]
    #[Assert\Length(
        max: 160,
        maxMessage: 'Název zdroje zápasů nesmí být delší než {{ limit }} znaků.',
    )]
    public string $name = '';

    public ?string $description = null;

    public ?\DateTimeImmutable $startAt = null;

    public ?\DateTimeImmutable $endAt = null;

    public static function fromMatchSource(MatchSource $matchSource): self
    {
        $formData = new self();
        $formData->sport = $matchSource->sport;
        $formData->name = $matchSource->name;
        $formData->description = $matchSource->description;
        $formData->startAt = $matchSource->startAt;
        $formData->endAt = $matchSource->endAt;

        return $formData;
    }
}
