<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class GroupMatchDeadlineAfterKickoff extends \DomainException
{
    public static function create(): self
    {
        return new self('Uzávěrka tipů musí být nejpozději v okamžiku začátku zápasu.');
    }
}
