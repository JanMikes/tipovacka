<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(403)]
final class NotAMember extends \DomainException
{
    public static function of(Uuid $competitionId): self
    {
        return new self(sprintf('Nejsi členem soutěže "%s".', $competitionId->toRfc4122()));
    }

    /** The player's own tip into a soutěž they are not (or no longer) a member of. */
    public static function toTip(): self
    {
        return new self('Nejste členem této soutěže, a tak do ní nemůžete tipovat. Nejdřív se k soutěži připojte.');
    }
}
