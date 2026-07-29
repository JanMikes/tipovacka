<?php

declare(strict_types=1);

namespace App\Command\LockCompetitionTips;

use Symfony\Component\Uid\Uuid;

final readonly class LockCompetitionTipsCommand
{
    /**
     * @param ?\DateTimeImmutable $lockAt UTC moment the lock takes effect;
     *                                    null = „Ihned" (lock now)
     */
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public ?\DateTimeImmutable $lockAt = null,
    ) {
    }
}
