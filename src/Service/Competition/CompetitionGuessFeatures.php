<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Repository\CompetitionRuleConfigurationRepository;
use App\Rule\OvertimeExactRule;
use App\Rule\PeriodExactRule;
use App\Rule\PeriodTendencyRule;
use App\Rule\RuleRegistry;
use App\Rule\ScorerHitRule;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * THE single authority answering "which guess features does competition C tip"
 * (mirrors the {@see CompetitionMatchProvider} pattern). Used by the guess
 * submit handlers (payload rejection), the live guess form and the batch tip
 * pages — all three must agree, so none of them re-derives rule enablement.
 *
 * Resolution matches the GuessEvaluator exactly: a stored
 * CompetitionRuleConfiguration row wins; a rule without a row falls back to its
 * `enabledByDefault` (false for all optional rules).
 */
class CompetitionGuessFeatures implements ResetInterface
{
    /** @var array<string, GuessFeatures> competition UUID → resolved toggles */
    private array $cache = [];

    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly CompetitionRuleConfigurationRepository $configurationRepository,
    ) {
    }

    public function featuresFor(Uuid $competitionId): GuessFeatures
    {
        $key = $competitionId->toRfc4122();

        if (!isset($this->cache[$key])) {
            $stored = $this->configurationRepository->mapForCompetition($competitionId);

            $enabled = function (string $identifier) use ($stored): bool {
                $configuration = $stored[$identifier] ?? null;

                return null === $configuration
                    ? $this->ruleRegistry->get($identifier)->enabledByDefault
                    : $configuration->enabled;
            };

            $this->cache[$key] = new GuessFeatures(
                periodTips: $enabled(PeriodExactRule::IDENTIFIER) || $enabled(PeriodTendencyRule::IDENTIFIER),
                scorerTips: $enabled(ScorerHitRule::IDENTIFIER),
                overtimeTip: $enabled(OvertimeExactRule::IDENTIFIER),
            );
        }

        return $this->cache[$key];
    }

    public function forgetCompetition(Uuid $competitionId): void
    {
        unset($this->cache[$competitionId->toRfc4122()]);
    }

    /**
     * Kernel/worker reset (autoconfigured via {@see ResetInterface}).
     */
    public function reset(): void
    {
        $this->cache = [];
    }
}
