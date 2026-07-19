<?php

declare(strict_types=1);

namespace App\Twig\Components\Scoring;

use App\Rule\RuleRegistry;
use App\Rule\ScorerHitRule;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Scoring rule fields (DS „Vyber pravidla" step). Class-based so the preset
 * values rendered into `data-*` attributes come straight from the PHP rules'
 * `defaultPoints` and categories — the single source of truth (no duplicated
 * map in JS).
 */
#[AsTwigComponent(name: 'Scoring:RuleFields')]
final class RuleFields
{
    /** Rendering order + Czech section headings per Rule::$category. */
    private const array SECTION_HEADINGS = [
        'base' => 'Základní bodování',
        'periods' => 'Části zápasu',
        'scorers' => 'Střelci',
        'overtime' => 'Prodloužení',
    ];

    public FormView $form;

    public bool $presetable = true;

    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
    ) {
    }

    /**
     * Rule identifier → default points, for the preset buttons.
     *
     * @var array<string, int>
     */
    public array $defaultPoints {
        get {
            $defaults = [];

            foreach ($this->ruleRegistry->all() as $identifier => $rule) {
                $defaults[$identifier] = $rule->defaultPoints;
            }

            return $defaults;
        }
    }

    /**
     * Preset name → identifiers ENABLED by the preset (every other rule is
     * disabled). „Standardní" = base rules; „Standard + střelec" = base rules
     * + scorer_hit. Points always come from $defaultPoints.
     *
     * @var array<string, list<string>>
     */
    public array $presets {
        get {
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
    }

    /**
     * Registered rules grouped for sectioned rendering, in fixed category order.
     *
     * @var list<array{category: string, heading: string, identifiers: list<string>}>
     */
    public array $sections {
        get {
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
    }
}
