<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Rule\CorrectAwayGoalsRule;
use App\Rule\CorrectHomeGoalsRule;
use App\Rule\CorrectOutcomeRule;
use App\Rule\ExactScoreRule;
use App\Rule\OvertimeExactRule;
use App\Rule\PeriodAwayGoalsRule;
use App\Rule\PeriodExactRule;
use App\Rule\PeriodHomeGoalsRule;
use App\Rule\PeriodTendencyRule;
use App\Rule\RuleRegistry;
use App\Rule\ScorerHitRule;

/**
 * Single source of truth for the scoring-rule UI metadata used by BOTH the
 * standalone „Vyber pravidla" step ({@see \App\Twig\Components\Scoring\RuleFields})
 * and the create-competition wizard: default points, presets, category
 * sections and the per-rule DS copy all live here — no duplicated map.
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

    /**
     * Per-rule DS copy shown by every rule-configuration surface (the wizard's
     * „Pravidla" step and the post-creation rules screen). It intentionally
     * overrides the PHP `Rule::$label`/`$description`, which stay technical for
     * the admin read-only table and the leaderboard breakdown.
     *
     * The key ORDER is the rendering order within a category — see
     * {@see orderIdentifiers()}. Registration order (alphabetical by class
     * name) would otherwise scatter the period rules.
     *
     * @var array<string, array{label: string, sub: string}>
     */
    public const array RULE_COPY = [
        CorrectAwayGoalsRule::IDENTIFIER => ['label' => 'Tip hosté', 'sub' => 'Správný tip hostujícího týmu'],
        CorrectHomeGoalsRule::IDENTIFIER => ['label' => 'Tip domácí', 'sub' => 'Správný tip domácího týmu'],
        CorrectOutcomeRule::IDENTIFIER => ['label' => 'Dobrý tip výsledku', 'sub' => 'Výhra / remíza / prohra'],
        ExactScoreRule::IDENTIFIER => ['label' => 'Přesný tip výsledku', 'sub' => 'bonus za obě uhodnutá skóre'],
        PeriodExactRule::IDENTIFIER => ['label' => 'Přesný tip části zápasu', 'sub' => 'Trefené přesné skóre poločasu či třetiny'],
        PeriodAwayGoalsRule::IDENTIFIER => ['label' => 'Tip hosté v části zápasu', 'sub' => 'Trefený počet gólů hostů v poločase či třetině'],
        PeriodHomeGoalsRule::IDENTIFIER => ['label' => 'Tip domácí v části zápasu', 'sub' => 'Trefený počet gólů domácích v poločase či třetině'],
        PeriodTendencyRule::IDENTIFIER => ['label' => 'Tendence části zápasu', 'sub' => 'Správný vítěz nebo remíza části (bez přesného skóre)'],
        ScorerHitRule::IDENTIFIER => ['label' => 'Trefený střelec', 'sub' => 'Body za každého správně tipnutého střelce'],
        OvertimeExactRule::IDENTIFIER => ['label' => 'Celkové skóre po prodloužení / penaltách', 'sub' => 'Trefený konečný stav po prodloužení či penaltách, když zápas skončil v základní hrací době remízou'],
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
     * Per-rule DS copy, keyed by rule identifier.
     *
     * @return array<string, array{label: string, sub: string}>
     */
    public function copy(): array
    {
        return self::RULE_COPY;
    }

    /**
     * Preset name → identifiers ENABLED by the preset (every other rule is
     * disabled). „standard" = base rules; „scorer" = base rules + scorer_hit;
     * „maxi" = base rules + the whole per-period trio + the after-overtime score.
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

        $base = $this->orderIdentifiers($base);

        return [
            'standard' => $base,
            'scorer' => [...$base, ScorerHitRule::IDENTIFIER],
            'maxi' => [
                ...$base,
                PeriodExactRule::IDENTIFIER,
                PeriodAwayGoalsRule::IDENTIFIER,
                PeriodHomeGoalsRule::IDENTIFIER,
                OvertimeExactRule::IDENTIFIER,
            ],
        ];
    }

    /**
     * Registered rules grouped for sectioned rendering, in fixed category order.
     *
     * @return list<array{category: string, heading: string, identifiers: list<string>}>
     */
    public function sections(): array
    {
        $byCategory = $this->identifiersByCategory();
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
     * ['periods' => ['period_exact', 'period_away_goals', …], 'overtime' => [...]].
     *
     * @return array<string, list<string>>
     */
    public function identifiersByCategory(): array
    {
        $byCategory = [];

        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $byCategory[$rule->category][] = $identifier;
        }

        foreach ($byCategory as $category => $identifiers) {
            $byCategory[$category] = $this->orderIdentifiers($identifiers);
        }

        return $byCategory;
    }

    /**
     * Puts identifiers into the curated {@see RULE_COPY} order; anything without
     * curated copy keeps its registration order and renders last.
     *
     * @param list<string> $identifiers
     *
     * @return list<string>
     */
    private function orderIdentifiers(array $identifiers): array
    {
        $order = array_flip(array_keys(self::RULE_COPY));
        $last = count($order);

        usort(
            $identifiers,
            static fn (string $a, string $b): int => ($order[$a] ?? $last) <=> ($order[$b] ?? $last),
        );

        return $identifiers;
    }
}
