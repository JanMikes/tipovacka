<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Uid\Uuid;

/** The source cannot be fed automatically (not curated / no feed binding). */
final class FeedSyncUnavailable extends \DomainException
{
    public static function notCurated(Uuid $matchSourceId): self
    {
        return new self(sprintf('Match source "%s" is not curated — feeds only write to curated sources.', $matchSourceId->toRfc4122()));
    }

    public static function notBound(Uuid $matchSourceId): self
    {
        return new self(sprintf('Match source "%s" has no feed provider/ref configured.', $matchSourceId->toRfc4122()));
    }
}
