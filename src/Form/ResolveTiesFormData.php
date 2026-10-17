<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class ResolveTiesFormData
{
    /**
     * @var list<string>
     */
    #[Assert\Count(min: 2, minMessage: 'Pořadí se nepodařilo odeslat. Přetáhněte hráče do požadovaného pořadí (rozřazují se aspoň dva) a uložte znovu — když to nepůjde, obnovte stránku.')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Uuid(),
    ])]
    public array $orderedUserIds = [];
}
