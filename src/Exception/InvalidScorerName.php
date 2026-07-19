<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Player;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A scorer tip carried an unusable player name (empty or over the shared
 * {@see Player::NAME_MAX_LENGTH} cap). Guards every input path — live form,
 * batch pages, on-behalf forms — so an over-long name can never reach the
 * database driver.
 */
#[WithHttpStatus(422)]
final class InvalidScorerName extends \DomainException
{
    public static function blank(): self
    {
        return new self('Zadejte prosím jméno hráče.');
    }

    public static function tooLong(): self
    {
        return new self(sprintf('Jméno hráče nesmí být delší než %d znaků.', Player::NAME_MAX_LENGTH));
    }
}
