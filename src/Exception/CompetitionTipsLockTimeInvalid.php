<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * A scheduled „Uzamknout tipy" moment the domain refuses (B2). The browser also
 * constrains the picker (min = now, max = first kickoff), so reaching this is
 * either a stale page, a race, or a hand-crafted POST.
 */
#[WithHttpStatus(409)]
final class CompetitionTipsLockTimeInvalid extends \DomainException
{
    public static function notInFuture(): self
    {
        return new self('Čas uzamčení musí být v budoucnosti.');
    }

    public static function afterCompetitionStart(): self
    {
        return new self('Uzamčení musí nastat dřív, než soutěž začne — tedy před výkopem prvního zápasu.');
    }

    public static function alreadyLocked(): self
    {
        return new self('Tipy jsou už uzamčené. Nejdřív je odemkněte.');
    }
}
