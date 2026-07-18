<?php

declare(strict_types=1);

namespace App\Command\SetCompetitionMatchDeadline;

use Symfony\Component\Uid\Uuid;

final readonly class SetCompetitionMatchDeadlineCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public Uuid $sportMatchId,
        public ?\DateTimeImmutable $deadline,
    ) {
    }
}
