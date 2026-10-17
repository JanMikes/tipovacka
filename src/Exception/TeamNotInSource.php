<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(422)]
final class TeamNotInSource extends \DomainException
{
    public static function create(Uuid $teamId, Uuid $matchSourceId): self
    {
        // Shown to the organizer as is (wizard, „Zápasy soutěže", „Týmy soutěže"),
        // so it says what to do, not which UUIDs disagreed. A team picked from the
        // picker can only miss the zdroj when its rozpis changed in the meantime;
        // the ids stay in the signature for the callers that know them.
        return new self('Jeden z vybraných týmů v tomto zdroji zápasů nehraje — rozpis se mezitím nejspíš změnil. Vyberte prosím týmy znovu.');
    }
}
