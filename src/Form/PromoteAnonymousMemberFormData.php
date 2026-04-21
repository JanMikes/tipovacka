<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\UniqueUserEmail;
use Symfony\Component\Validator\Constraints as Assert;

final class PromoteAnonymousMemberFormData
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím e-mailovou adresu.'),
        new Assert\Email(message: 'Zadejte prosím platnou e-mailovou adresu.'),
        new Assert\Length(max: 180, maxMessage: 'E-mailová adresa je příliš dlouhá.'),
        new UniqueUserEmail(),
    ])]
    public string $email = '';
}
