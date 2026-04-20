<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class ResolveTiesFormData
{
    /**
     * @var list<string>
     */
    #[Assert\Count(min: 2, minMessage: 'Pro rozřazení vyberte alespoň dva uživatele.')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Uuid(),
    ])]
    public array $orderedUserIds = [];
}
