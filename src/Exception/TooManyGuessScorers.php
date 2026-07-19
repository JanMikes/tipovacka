<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\GuessScorer;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class TooManyGuessScorers extends \DomainException
{
    public static function create(): self
    {
        return new self(sprintf('Můžete tipnout nejvýše %d střelců.', GuessScorer::MAX_PER_GUESS));
    }
}
