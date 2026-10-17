<?php

declare(strict_types=1);

namespace App\Form;

use App\Query\GetCompetitionRuleConfiguration\CompetitionRuleConfigurationResult;
use Symfony\Component\Validator\Constraints as Assert;

final class CompetitionRuleConfigurationFormData
{
    /**
     * `Valid` is what makes the per-entry constraints (points 0–1000) run at all:
     * the entries sit in an array, and the validator does not descend into one
     * on its own — without it any number was saved.
     *
     * @var array<string, RuleConfigurationEntryFormData>
     */
    #[Assert\Valid]
    public array $rules = [];

    public static function fromResult(CompetitionRuleConfigurationResult $result): self
    {
        $data = new self();

        foreach ($result->items as $item) {
            $entry = new RuleConfigurationEntryFormData();
            $entry->enabled = $item->enabled;
            $entry->points = $item->points;
            $data->rules[$item->identifier] = $entry;
        }

        return $data;
    }
}
