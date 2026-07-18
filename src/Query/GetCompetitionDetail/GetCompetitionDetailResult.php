<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionDetail;

use Symfony\Component\Uid\Uuid;

final readonly class GetCompetitionDetailResult
{
    /**
     * @param list<CompetitionMemberListItem> $members
     */
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public bool $matchSourceIsFinished,
        public Uuid $ownerId,
        public string $ownerNickname,
        public string $name,
        public ?string $description,
        public ?string $pin,
        public ?string $shareableLinkToken,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public array $members,
    ) {
    }
}
