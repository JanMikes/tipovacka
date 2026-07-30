<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

use App\Query\QueryMessage;

/**
 * The three hero numbers of the „Soutěže" page (item 07) — AKTIVNÍ SOUTĚŽE /
 * HRÁČŮ CELKEM / SLEDOVANÝCH ZÁPASŮ — plus the sub-label figures under them.
 *
 * Every value is measured, never decorative, and since item 15 every value is a
 * **platform-wide total**: the hero says the same thing to everybody, logged in
 * or out. (Item 07 scoped it to the viewer's own world; the product owner reversed
 * that on 2026-07-30 because two visitors were being shown two different „totals".)
 * The query therefore takes no viewer — there is nothing to scope.
 *
 * @implements QueryMessage<CompetitionsPageStatsResult>
 */
final readonly class GetCompetitionsPageStats implements QueryMessage
{
}
