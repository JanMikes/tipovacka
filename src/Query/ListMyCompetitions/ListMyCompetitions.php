<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<CompetitionListItem>>
 */
final readonly class ListMyCompetitions implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        /**
         * Fill {@see CompetitionListItem::$missingTipCount} (it stays 0 otherwise).
         *
         * Opt-in because this list has three consumers and only ONE renders the
         * „Chybí N tipů" badge: the Nástěnka's „Moje soutěže" grid.
         * `/zebricek` and `<twig:SoutezSwitcher>` only need names and dates, and
         * resolving the count costs the per-competition effective-deadline batch
         * ({@see \App\Service\EffectiveTipDeadlineResolver}) — measured at +14
         * statements on `/zebricek` for a badge that page never draws.
         */
        public bool $withMissingTipCounts = false,
    ) {
    }
}
