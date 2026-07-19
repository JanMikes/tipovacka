<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

interface Rule
{
    /** Machine identifier, e.g. 'exact_score'. Must be unique across all registered rules. */
    public string $identifier { get; }

    /** Czech human-readable label, e.g. 'Přesný výsledek'. */
    public string $label { get; }

    /** Czech description shown in the competition rule-configuration UI. */
    public string $description { get; }

    /** Default awarded points. CompetitionRuleConfiguration may override. */
    public int $defaultPoints { get; }

    /**
     * Whether the rule participates when a competition has no stored configuration
     * row for it. Base rules default to true; optional rules (periods, scorers,
     * overtime) opt in per competition and return false.
     */
    public bool $enabledByDefault { get; }

    /**
     * UI grouping of the rule-configuration form (sectioned rendering) and the
     * guess-feature toggles: 'base' | 'periods' | 'scorers' | 'overtime'.
     */
    public string $category { get; }

    /**
     * Returns a multiplier ≥ 0: how many times the rule triggered for this
     * guess/match pair (binary rules return 0/1; counting rules like scorer or
     * period hits return the hit count). The evaluator awards
     * `multiplier × configured points` and stores that product; multiplier 0
     * stores nothing. Point policy lives entirely in CompetitionRuleConfiguration.
     *
     * $context carries per-match derived data (e.g. goal scorer player ids)
     * built once per match by the evaluator — rules never query repositories.
     */
    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int;
}
