<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

use App\Query\QueryMessage;

/**
 * ⚠ **NO CALL SITE. This is not dead code — do not delete it.** Item 24 removed the
 * three `StatCard`s from `/souteze` („we might use them somewhere else later so keep
 * logic documented but remove them from here" — product owner, 2026-07-30), which was
 * this query's only caller. It is kept, handled and tested on purpose, so the next
 * surface that wants these figures inherits measured ones instead of re-deriving them —
 * the same treatment item 15 gave `Competition:FilterBar`, which also survives with no
 * caller.
 *
 * **What it computes** — platform-wide aggregates, in one `CompetitionsPageStatsResult`:
 * how many competitions are active (their zdroj zápasů still running), how many of
 * those have a match in play right now, how many matches kick off on today's Prague
 * calendar day, how many distinct people hold an active membership anywhere, how many
 * of them joined in the last seven days, how many distinct matches those competitions
 * include, and across how many zdroje zápasů („turnaje") those matches are spread.
 *
 * **Viewer-independent by design.** The query takes no viewer, so there is nothing to
 * scope: everybody gets the same numbers. Item 07 originally scoped them to the
 * viewer's own world; the product owner reversed that on 2026-07-30 (item 15) because
 * two visitors were being shown two different „totals". Private competitions are
 * counted — only aggregates leave here, never a name, owner or id.
 *
 * **Every value is measured, never decorative.** `/souteze` rendered them as
 * AKTIVNÍ SOUTĚŽE / HRÁČŮ CELKEM / SLEDOVANÝCH ZÁPASŮ with a sub-label under each
 * („N živě teď" / „N zápasů dnes", „+N tento týden", „Ve N turnajích"), a sub-label
 * shown only when it had something real to say. Nothing was padded or rounded up, and
 * small numbers on a young product were accepted as correct. Keep that rule if these
 * figures come back somewhere else.
 *
 * @implements QueryMessage<CompetitionsPageStatsResult>
 */
final readonly class GetCompetitionsPageStats implements QueryMessage
{
}
