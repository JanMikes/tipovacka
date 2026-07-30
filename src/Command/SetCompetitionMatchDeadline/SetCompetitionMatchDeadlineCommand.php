<?php

declare(strict_types=1);

namespace App\Command\SetCompetitionMatchDeadline;

use Symfony\Component\Uid\Uuid;

/**
 * Writes ONE match's tip window inside ONE competition. The deadline end is the
 * competition manager's; the opening end („tipování otevřeno od" plus the note
 * shown while the match waits) is ADMIN-only.
 *
 * `$changeOpening` is what keeps those two authorities from overwriting each
 * other: a manager's form does not carry the opening fields at all, so their
 * save arrives with `false` and the stored opening is left exactly as it was.
 * `true` means the opening is part of this write — and only an admin may send
 * it (the handler re-checks; the form merely hides the fields).
 */
final readonly class SetCompetitionMatchDeadlineCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public Uuid $sportMatchId,
        public ?\DateTimeImmutable $deadline,
        public bool $changeOpening = false,
        public ?\DateTimeImmutable $opensAt = null,
        public ?string $openingNote = null,
    ) {
    }
}
