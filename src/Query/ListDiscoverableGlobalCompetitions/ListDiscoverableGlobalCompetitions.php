<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverableGlobalCompetitions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Public discovery of global competitions (the ONLY publicly listed competitions).
 * `$viewerId` is null for anonymous visitors; when set, each item carries whether
 * the viewer is already an active member (drives the join / open CTA).
 *
 * @implements QueryMessage<list<DiscoverableGlobalCompetitionItem>>
 */
final readonly class ListDiscoverableGlobalCompetitions implements QueryMessage
{
    public function __construct(
        public ?Uuid $viewerId = null,
    ) {
    }
}
