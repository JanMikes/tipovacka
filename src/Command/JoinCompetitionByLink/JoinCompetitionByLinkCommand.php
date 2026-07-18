<?php

declare(strict_types=1);

namespace App\Command\JoinCompetitionByLink;

use Symfony\Component\Uid\Uuid;

final readonly class JoinCompetitionByLinkCommand
{
    public function __construct(
        public Uuid $userId,
        public string $token,
    ) {
    }
}
