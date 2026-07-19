<?php

declare(strict_types=1);

namespace App\Query\GetBoostPanel;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * The per-viewer boost state in a competition — feeds the paywall CTAs and the
 * „Tvoje vylepšení" sidebar. See .docs/DOMAIN.md §Monetization.
 *
 * @implements QueryMessage<GetBoostPanelResult>
 */
final readonly class GetBoostPanel implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $userId,
    ) {
    }
}
