<?php

declare(strict_types=1);

namespace App\Command\JoinGroupByLink;

use Symfony\Component\Uid\Uuid;

final readonly class JoinGroupByLinkCommand
{
    public function __construct(
        public Uuid $userId,
        public string $token,
    ) {
    }
}
