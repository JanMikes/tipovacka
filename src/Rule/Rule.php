<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;

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
     * row for it. Base rules default to true; future optional rules (periods,
     * scorers, overtime) opt in per competition and return false.
     */
    public bool $enabledByDefault { get; }

    /**
     * Returns 1 if the rule triggers for this guess/match pair, 0 otherwise.
     * The evaluator multiplies this by the points configured for the guess's competition.
     * Binary return keeps rule code trivial; point policy lives entirely in CompetitionRuleConfiguration.
     */
    public function evaluate(Guess $guess, SportMatch $match): int;
}
