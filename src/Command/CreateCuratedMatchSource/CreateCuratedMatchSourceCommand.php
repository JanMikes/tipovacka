<?php

declare(strict_types=1);

namespace App\Command\CreateCuratedMatchSource;

use App\Enum\CompetitionMonetization;
use Symfony\Component\Uid\Uuid;

final readonly class CreateCuratedMatchSourceCommand
{
    public function __construct(
        public Uuid $adminId,
        public Uuid $sportId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        /**
         * Optional „Rovnou vytvořit globální soutěž" step — when true, a global
         * competition over the new source is composed in the SAME transaction.
         */
        public bool $createGlobalCompetition = false,
        public ?string $globalCompetitionName = null,
        public int $globalCompetitionEntryFee = 0,
        public CompetitionMonetization $globalCompetitionMonetization = CompetitionMonetization::None,
    ) {
    }
}
