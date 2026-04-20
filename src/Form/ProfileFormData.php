<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileFormData
{
    #[Assert\Length(max: 100, maxMessage: 'Jméno může mít maximálně {{ limit }} znaků.')]
    public ?string $firstName = null;

    #[Assert\Length(max: 100, maxMessage: 'Příjmení může mít maximálně {{ limit }} znaků.')]
    public ?string $lastName = null;

    #[Assert\Length(max: 20, maxMessage: 'Telefon může mít maximálně {{ limit }} znaků.')]
    public ?string $phone = null;

    public static function fromUser(User $user): self
    {
        $formData = new self();
        $formData->firstName = $user->firstName;
        $formData->lastName = $user->lastName;
        $formData->phone = $user->phone;

        return $formData;
    }
}
