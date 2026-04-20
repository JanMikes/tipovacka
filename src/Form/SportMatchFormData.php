<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SportMatch;
use Symfony\Component\Validator\Constraints as Assert;

final class SportMatchFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím domácí tým.')]
    #[Assert\Length(
        max: 120,
        maxMessage: 'Název domácího týmu nesmí být delší než {{ limit }} znaků.',
    )]
    public string $homeTeam = '';

    #[Assert\NotBlank(message: 'Zadejte prosím hostující tým.')]
    #[Assert\Length(
        max: 120,
        maxMessage: 'Název hostujícího týmu nesmí být delší než {{ limit }} znaků.',
    )]
    public string $awayTeam = '';

    #[Assert\NotNull(message: 'Zadejte prosím začátek zápasu.')]
    public ?\DateTimeImmutable $kickoffAt = null;

    #[Assert\Length(
        max: 160,
        maxMessage: 'Místo nesmí být delší než {{ limit }} znaků.',
    )]
    public ?string $venue = null;

    public static function fromSportMatch(SportMatch $sportMatch): self
    {
        $formData = new self();
        $formData->homeTeam = $sportMatch->homeTeam;
        $formData->awayTeam = $sportMatch->awayTeam;
        $formData->kickoffAt = $sportMatch->kickoffAt;
        $formData->venue = $sportMatch->venue;

        return $formData;
    }
}
