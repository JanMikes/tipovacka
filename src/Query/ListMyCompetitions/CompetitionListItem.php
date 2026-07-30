<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionListItem
{
    public function __construct(
        public Uuid $competitionId,
        public string $competitionName,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public bool $matchSourceIsCompleted,
        public string $ownerNickname,
        public bool $isOwner,
        public \DateTimeImmutable $joinedAt,
        public ?\DateTimeImmutable $matchSourceStartAt = null,
        public ?\DateTimeImmutable $matchSourceEndAt = null,
        /**
         * Matches the viewer has not tipped and still can — the „Chybí natipovat N zápasů"
         * badge on the „Moje soutěže" cards. From
         * {@see \App\Service\Competition\MissingTipCounter}, the same service
         * `ListMyPlayingCompetitions` uses, so both card surfaces show one number per
         * soutěž. Defaults to 0 so the switcher's hand-built options stay valid.
         */
        public int $missingTipCount = 0,
    ) {
    }
}
