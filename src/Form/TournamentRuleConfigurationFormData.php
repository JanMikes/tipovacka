<?php

declare(strict_types=1);

namespace App\Form;

use App\Query\GetTournamentRuleConfiguration\TournamentRuleConfigurationResult;

final class TournamentRuleConfigurationFormData
{
    /**
     * @var array<string, RuleConfigurationEntryFormData>
     */
    public array $rules = [];

    public static function fromResult(TournamentRuleConfigurationResult $result): self
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
