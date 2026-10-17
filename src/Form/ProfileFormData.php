<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileFormData
{
    public const int NAME_MAX_LENGTH = 100;
    public const int PHONE_MAX_LENGTH = 20;

    #[Assert\Length(max: self::NAME_MAX_LENGTH, maxMessage: 'Jméno může mít nejvýše {{ limit }} znaků — zkraťte ho prosím.')]
    public ?string $firstName = null;

    #[Assert\Length(max: self::NAME_MAX_LENGTH, maxMessage: 'Příjmení může mít nejvýše {{ limit }} znaků — zkraťte ho prosím.')]
    public ?string $lastName = null;

    /*
     * Deliberately loose: a leading +, then digits with the usual separators. It only
     * catches what can never be a number (letters, an e-mail typed into the wrong box).
     */
    #[Assert\Length(max: self::PHONE_MAX_LENGTH, maxMessage: 'Telefon může mít nejvýše {{ limit }} znaků — zadejte ho bez poznámek, např. +420 123 456 789.')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9][0-9 ()\/.-]{5,}$/',
        message: 'Telefon zadejte jen číslicemi, případně s předvolbou a mezerami, např. +420 123 456 789.',
    )]
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
