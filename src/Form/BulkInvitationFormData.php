<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class BulkInvitationFormData
{
    #[Assert\NotBlank(message: 'Zadejte alespoň jednu e-mailovou adresu.')]
    #[Assert\Length(max: 8000, maxMessage: 'Seznam e-mailů je příliš dlouhý (max {{ limit }} znaků).')]
    public string $emails = '';
}
