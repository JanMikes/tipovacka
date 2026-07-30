<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * The manager/admin override of ONE match's tip window inside ONE competition —
 * both of its ends, each optional:
 *
 * - `deadline` — until when tips are taken here (manager-editable, never after
 *   kickoff). Null = the competition's default rule applies.
 * - `opensAt` — from when tips are taken at all (ADMIN-only). Null = open from
 *   the start, the behavior every competition had before 2026-07-30. Before
 *   this moment the match is visible but untippable for EVERYONE, on-behalf
 *   tipping included.
 * - `openingNote` — the optional Czech line shown while the match waits
 *   („Tipy otevřeme po losu skupin"). Meaningless without `opensAt`, so the
 *   write side rejects that combination.
 *
 * A row with all three null carries no information and is deleted instead
 * ({@see \App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineHandler}).
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_match_settings')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_match_settings_competition_match', columns: ['competition_id', 'sport_match_id'])]
class CompetitionMatchSetting
{
    public const int OPENING_NOTE_MAX_LENGTH = 500;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $deadline;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $opensAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $openingNote;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: SportMatch::class)]
        #[ORM\JoinColumn(name: 'sport_match_id', referencedColumnName: 'id', nullable: false)]
        private(set) SportMatch $sportMatch,
        ?\DateTimeImmutable $deadline,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $opensAt = null,
        ?string $openingNote = null,
    ) {
        $this->deadline = $deadline;
        $this->opensAt = $opensAt;
        $this->openingNote = self::normalizeNote($openingNote);
        $this->updatedAt = $this->createdAt;
    }

    /**
     * Full replace of the window — every end becomes exactly what the caller
     * passes, so clearing one is the same call as setting the other.
     */
    public function updateWindow(
        ?\DateTimeImmutable $deadline,
        ?\DateTimeImmutable $opensAt,
        ?string $openingNote,
        \DateTimeImmutable $now,
    ): void {
        $this->deadline = $deadline;
        $this->opensAt = $opensAt;
        $this->openingNote = self::normalizeNote($openingNote);
        $this->updatedAt = $now;
    }

    /**
     * Nothing left to remember — the row may be dropped.
     */
    public bool $isEmpty {
        get => null === $this->deadline && null === $this->opensAt && null === $this->openingNote;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if (null === $note) {
            return null;
        }

        $trimmed = trim($note);

        return '' === $trimmed ? null : $trimmed;
    }
}
