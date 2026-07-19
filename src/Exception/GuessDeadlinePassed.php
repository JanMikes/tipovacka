<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class GuessDeadlinePassed extends \DomainException
{
    public static function at(\DateTimeImmutable $deadline): self
    {
        return new self(sprintf(
            'Uzávěrka tipů pro tento zápas už proběhla (%s), tip již nelze odeslat ani upravit.',
            $deadline->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i'),
        ));
    }
}
