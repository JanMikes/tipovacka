<?php

declare(strict_types=1);

namespace App\Twig\Components\Scoring;

use App\Rule\RuleRegistry;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Scoring rule fields (DS „Vyber pravidla" step). Class-based so the preset
 * values rendered into `data-*` attributes come straight from the PHP rules'
 * `defaultPoints` — the single source of truth (no duplicated map in JS).
 */
#[AsTwigComponent(name: 'Scoring:RuleFields')]
final class RuleFields
{
    public FormView $form;

    public bool $presetable = true;

    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
    ) {
    }

    /**
     * Rule identifier → default points, for the „Standardní" preset button.
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
}
