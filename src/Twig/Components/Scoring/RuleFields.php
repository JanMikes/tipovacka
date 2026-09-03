<?php

declare(strict_types=1);

namespace App\Twig\Components\Scoring;

use App\Enum\OvertimeCoverage;
use App\Rule\OvertimeExactRule;
use App\Service\Scoring\RulePresetProvider;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Scoring rule fields (DS „Vyber pravidla" step). Class-based so the preset
 * values rendered into `data-*` attributes come straight from the PHP rules'
 * `defaultPoints` and categories — the single source of truth (no duplicated
 * map in JS). All metadata is delegated to {@see RulePresetProvider}, shared
 * with the create-competition wizard.
 */
#[AsTwigComponent(name: 'Scoring:RuleFields')]
final class RuleFields
{
    public FormView $form;

    public bool $presetable = true;

    /**
     * How much of the soutěž's scope can go on to extra time, from
     * {@see \App\Query\GetCompetitionRuleConfiguration\CompetitionRuleConfigurationResult}.
     * Null on a surface that has no soutěž to ask (nothing is claimed then).
     */
    public ?OvertimeCoverage $overtimeCoverage = null;

    public function __construct(
        private readonly RulePresetProvider $rulePresetProvider,
    ) {
    }

    /**
     * Rule identifier → default points, for the preset buttons.
     *
     * @var array<string, int>
     */
    public array $defaultPoints {
        get => $this->rulePresetProvider->defaultPoints();
    }

    /**
     * Preset name → identifiers ENABLED by the preset.
     *
     * @var array<string, list<string>>
     */
    public array $presets {
        get => $this->rulePresetProvider->presets();
    }

    /**
     * Registered rules grouped for sectioned rendering, in fixed category order.
     *
     * @var list<array{category: string, heading: string, identifiers: list<string>}>
     */
    public array $sections {
        get => $this->rulePresetProvider->sections();
    }

    /**
     * Per-rule DS copy — shared with the create-competition wizard so both
     * surfaces name a rule identically.
     *
     * @var array<string, array{label: string, sub: string}>
     */
    public array $ruleCopy {
        get => $this->rulePresetProvider->copy();
    }

    /**
     * Rule identifier → the caveat rendered under its copy. Only the overtime
     * rule has one today, and its wording lives on {@see OvertimeCoverage} —
     * shared with the create wizard, which renders the same map.
     *
     * @var array<string, string>
     */
    public array $ruleHints {
        get {
            $hint = $this->overtimeCoverage?->hint();

            return null === $hint ? [] : [OvertimeExactRule::IDENTIFIER => $hint];
        }
    }
}
