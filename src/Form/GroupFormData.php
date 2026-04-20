<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Group;
use Symfony\Component\Validator\Constraints as Assert;

final class GroupFormData
{
    #[Assert\NotBlank(message: 'Zadejte prosím název skupiny.')]
    #[Assert\Length(
        max: 160,
        maxMessage: 'Název skupiny nesmí být delší než {{ limit }} znaků.',
    )]
    public string $name = '';

    public ?string $description = null;

    public bool $withPin = false;

    public ?string $tournamentCreationPin = null;

    public static function fromGroup(Group $group): self
    {
        $formData = new self();
        $formData->name = $group->name;
        $formData->description = $group->description;
        $formData->withPin = null !== $group->pin;

        return $formData;
    }
}
