<?php

declare(strict_types=1);

namespace App\Query\ListAdminMatchSources;

use App\Enum\MatchSourceKind;
use Symfony\Component\Uid\Uuid;

final readonly class AdminMatchSourceItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public MatchSourceKind $kind,
        public string $sportCode,
        public string $ownerNickname,
        public bool $isFinished,
        public bool $isDeleted,
        public int $competitionCount,
    ) {
    }
}
