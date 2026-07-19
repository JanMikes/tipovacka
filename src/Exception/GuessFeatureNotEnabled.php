<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A guess payload carried a tip part (periods / overtime / scorers) whose
 * corresponding rule is disabled for the competition. Feature toggles ARE the
 * rule enablement (see DOMAIN.md §Scoring) — disabled rule ⇒ the part is
 * rejected, never silently dropped.
 */
#[WithHttpStatus(422)]
final class GuessFeatureNotEnabled extends \DomainException
{
    public static function periods(): self
    {
        return new self('Tato soutěž netipuje části zápasu.');
    }

    public static function overtime(): self
    {
        return new self('Tato soutěž netipuje prodloužení.');
    }

    public static function scorers(): self
    {
        return new self('Tato soutěž netipuje střelce.');
    }
}
