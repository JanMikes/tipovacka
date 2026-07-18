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

    /** Czech description shown in match source rule-configuration UI. */
    public string $description { get; }

    /** Default awarded points. MatchSource config may override. */
    public int $defaultPoints { get; }

    /**
     * Returns 1 if the rule triggers for this guess/match pair, 0 otherwise.
     * The evaluator (Stage 7) multiplies this by the configured points for the match source.
     * Binary return keeps rule code trivial; point policy lives entirely in MatchSourceRuleConfiguration.
     */
    public function evaluate(Guess $guess, SportMatch $match): int;
}
