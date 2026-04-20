<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class InvitationAlreadyRegistered extends \DomainException
{
    public static function create(): self
    {
        return new self('Účet už má nastavené heslo. Přihlas se a přijmi pozvánku v aplikaci.');
    }
}
