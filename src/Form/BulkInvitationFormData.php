<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class BulkInvitationFormData
{
    #[Assert\NotBlank(message: 'Vložte alespoň jednu e-mailovou adresu — každou na nový řádek, nebo oddělené čárkou.')]
    #[Assert\Length(max: 8000, maxMessage: 'Seznam je moc dlouhý (nejvýš {{ limit }} znaků). Rozdělte ho prosím a pošlete pozvánky na dvakrát.')]
    public string $emails = '';
}
