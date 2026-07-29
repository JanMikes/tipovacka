<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * The three hero numbers of the „Soutěže" page (item 07) — AKTIVNÍ SOUTĚŽE /
 * HRÁČŮ CELKEM / SLEDOVANÝCH ZÁPASŮ — plus the sub-label figures under them.
 *
 * Every value is measured, never decorative: the hero summarises exactly the
 * competitions the page is about. For a signed-in visitor that is their own
 * world (competitions they play in or organize); for an anonymous one it is the
 * public/global list, which is the whole page they get.
 *
 * @implements QueryMessage<CompetitionsPageStatsResult>
 */
final readonly class GetCompetitionsPageStats implements QueryMessage
{
    public function __construct(
        public ?Uuid $viewerId = null,
    ) {
    }
}
