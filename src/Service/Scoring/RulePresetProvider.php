<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Rule\RuleRegistry;
use App\Rule\ScorerHitRule;

/**
 * Single source of truth for the scoring-rule UI metadata used by BOTH the
 * standalone „Vyber pravidla" step ({@see \App\Twig\Components\Scoring\RuleFields})
 * and the create-competition wizard: default points, presets and category
 * sections all derive from the registered PHP rules — no duplicated map.
 */
final readonly class RulePresetProvider
{
    /** Rendering order + Czech section headings per Rule::$category. */
    public const array SECTION_HEADINGS = [
        'base' => 'Základní bodování',
        'periods' => 'Části zápasu',
        'scorers' => 'Střelci',
        'overtime' => 'Prodloužení',
    ];

    public function __construct(
        private RuleRegistry $ruleRegistry,
    ) {
    }

    /**
     * Rule identifier → default points.
     *
     * @return array<string, int>
     */
    public function defaultPoints(): array
    {
        $defaults = [];

        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $defaults[$identifier] = $rule->defaultPoints;
        }

        return $defaults;
    }

    /**
     * Preset name → identifiers ENABLED by the preset (every other rule is
     * disabled). „standard" = base rules; „scorer" = base rules + scorer_hit.
     * Points always come from {@see defaultPoints()}.
     *
     * @return array<string, list<string>>
     */
    public function presets(): array
    {
        $base = [];

        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            if ('base' === $rule->category) {
                $base[] = $identifier;
            }
        }

        return [
            'standard' => $base,
            'scorer' => [...$base, ScorerHitRule::IDENTIFIER],
        ];
    }

    /**
     * Registered rules grouped for sectioned rendering, in fixed category order.
     *
     * @return list<array{category: string, heading: string, identifiers: list<string>}>
     */
    public function sections(): array
    {
        $byCategory = [];

        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $byCategory[$rule->category][] = $identifier;
        }

        $sections = [];

        foreach (self::SECTION_HEADINGS as $category => $heading) {
            if (!isset($byCategory[$category])) {
                continue;
            }

            $sections[] = [
                'category' => $category,
                'heading' => $heading,
                'identifiers' => $byCategory[$category],
            ];
            unset($byCategory[$category]);
        }

        // Future categories without a curated heading render last, unstyled label.
        foreach ($byCategory as $category => $identifiers) {
            $sections[] = [
                'category' => $category,
                'heading' => ucfirst($category),
                'identifiers' => $identifiers,
            ];
        }

        return $sections;
    }

    /**
     * Identifiers grouped by category for the wizard's friendly toggles, e.g.
     * ['periods' => ['period_exact', 'period_tendency'], 'overtime' => [...]].
     *
     * @return array<string, list<string>>
     */
    public function identifiersByCategory(): array
    {
        $byCategory = [];

        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $byCategory[$rule->category][] = $identifier;
        }

        return $byCategory;
    }
}
