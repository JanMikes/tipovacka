<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class NicknameAlreadyTaken extends \DomainException
{
    public static function withNickname(string $nickname): self
    {
        return new self(sprintf('Přezdívka "%s" je již obsazena.', $nickname));
    }
}
