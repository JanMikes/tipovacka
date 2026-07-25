<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\Team;
use Symfony\Component\Uid\Uuid;

/**
 * Read-model projection of a Team for query results and templates. Carries just
 * the display fields plus the computed monogram, so query DTOs never leak the
 * entity and every match surface renders a team the same way (via <twig:TeamFlag>).
 *
 * Not `readonly` because the `monogram` get-hook cannot live on a readonly class.
 */
final class TeamView
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?string $shortName,
        public ?string $country,
        public ?string $brandColor,
        public ?string $logo,
    ) {
    }

    /** The colored-initials badge — identical logic whether fed a Team or a TeamView. */
    public TeamMonogram $monogram {
        get => TeamMonogram::forName($this->name, $this->brandColor);
    }

    public static function fromTeam(Team $team): self
    {
        return new self(
            id: $team->id,
            name: $team->name,
            shortName: $team->shortName,
            country: $team->country,
            brandColor: $team->brandColor,
            logo: $team->logo,
        );
    }
}
