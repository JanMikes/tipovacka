<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class PostponeSportMatchFormData
{
    #[Assert\NotNull(message: 'Zadejte prosím nový termín zápasu.')]
    public ?\DateTimeImmutable $newKickoffAt = null;
}
