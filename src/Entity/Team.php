<?php

declare(strict_types=1);

namespace App\Entity;

use App\Value\TeamMonogram;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A team as a first-class identity — the same club across every match it plays,
 * a home for a logo, a short name, a country and a brand color.
 *
 * Scope is hybrid and DERIVED (never stored): a team with no MatchSource is a
 * GLOBAL directory team (curated sources draw from this shared, admin-curated
 * pool, one row per real club per sport); a team WITH a MatchSource is LOCAL to
 * that private from-scratch source (office-pool names never pollute the
 * directory). Two partial unique indexes enforce name-uniqueness per scope.
 *
 * A lookup/roster-anchor entity like Player/Sport: no domain events, no soft
 * delete; the home_team_id / team_id FKs (RESTRICT) guard against removal.
 * Case-insensitive name matching lives in TeamResolver/TeamRepository (the DB
 * indexes are case-sensitive, mirroring how Player names work today).
 */
#[ORM\Entity]
#[ORM\Table(name: 'teams')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_teams_global_sport_name',
    columns: ['sport_id', 'name'],
    options: ['where' => '(match_source_id IS NULL)'],
)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_teams_local_source_name',
    columns: ['match_source_id', 'name'],
    options: ['where' => '(match_source_id IS NOT NULL)'],
)]
class Team
{
    /** Shared cap for team names — column length AND every input path (forms, import, picker). */
    public const int NAME_MAX_LENGTH = 120;
    public const int SHORT_NAME_MAX_LENGTH = 40;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    public private(set) string $name;

    /** Compact label for tight surfaces (mobile cards, matrix headers), e.g. „SPA". Optional. */
    #[ORM\Column(length: self::SHORT_NAME_MAX_LENGTH, nullable: true)]
    public private(set) ?string $shortName;

    /** ISO 3166-1 alpha-2, upper-cased. Drives the country flag when present. Optional. */
    #[ORM\Column(length: 2, nullable: true)]
    public private(set) ?string $country;

    /** #RRGGBB. Monogram circle background; the readable foreground is computed. Optional. */
    #[ORM\Column(length: 7, nullable: true)]
    public private(set) ?string $brandColor;

    /** Reserved for a future uploaded logo; always null in v1 (only the monogram renders). */
    #[ORM\Column(length: 255, nullable: true)]
    public private(set) ?string $logo;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public bool $isGlobal {
        get => null === $this->matchSource;
    }

    public bool $isLocal {
        get => null !== $this->matchSource;
    }

    /** The colored-initials badge (name + optional brand color → initials, bg, contrast-safe fg). */
    public TeamMonogram $monogram {
        get => TeamMonogram::forName($this->name, $this->brandColor);
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Sport::class)]
        #[ORM\JoinColumn(name: 'sport_id', referencedColumnName: 'id', nullable: false)]
        private(set) Sport $sport,
        // null = global directory team; set = local team of a private source.
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: true)]
        private(set) ?MatchSource $matchSource,
        string $name,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        ?string $shortName = null,
        ?string $country = null,
        ?string $brandColor = null,
    ) {
        $this->name = $name;
        $this->shortName = $shortName;
        $this->country = $country;
        $this->brandColor = $brandColor;
        $this->logo = null;
        $this->updatedAt = $this->createdAt;
    }

    public function updateDetails(
        string $name,
        ?string $shortName,
        ?string $country,
        ?string $brandColor,
        \DateTimeImmutable $now,
    ): void {
        $this->name = $name;
        $this->shortName = $shortName;
        $this->country = $country;
        $this->brandColor = $brandColor;
        $this->updatedAt = $now;
    }
}
