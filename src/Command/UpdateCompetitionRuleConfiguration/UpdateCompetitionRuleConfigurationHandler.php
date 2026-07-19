<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionRuleConfiguration;

use App\Entity\CompetitionRuleConfiguration;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\UserRepository;
use App\Rule\RuleRegistry;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCompetitionRuleConfigurationHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionRuleConfigurationRepository $configurationRepository,
        private RuleRegistry $ruleRegistry,
        private CompetitionGuessFeatures $guessFeatures,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionRuleConfigurationCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $editor = $this->userRepository->get($command->editorId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($command->changes as $ruleIdentifier => $change) {
            // Ignore unknown rules — defensive so form submissions with stale identifiers don't explode.
            if (!array_key_exists($ruleIdentifier, $this->ruleRegistry->all())) {
                continue;
            }

            $rule = $this->ruleRegistry->get($ruleIdentifier);
            $points = max(0, $change['points']);
            $enabled = $change['enabled'];

            $configuration = $this->configurationRepository->findOne($competition->id, $ruleIdentifier);

            if (null === $configuration) {
                // Persist missing rows on save so the stored state fully describes
                // the competition — the evaluator provisions nothing silently.
                $configuration = new CompetitionRuleConfiguration(
                    id: $this->identity->next(),
                    competition: $competition,
                    ruleIdentifier: $ruleIdentifier,
                    enabled: $enabled,
                    points: $enabled ? $points : $rule->defaultPoints,
                    now: $now,
                );
                $this->configurationRepository->save($configuration);

                continue;
            }

            if ($enabled) {
                $configuration->enable($points, $now);
            } else {
                $configuration->disable($now);
            }
        }

        $competition->recordRulesChanged($editor, $now);

        // Rule enablement drives the guess-feature toggles — drop the cached
        // resolution so the same process (form re-render, tests) sees the change.
        $this->guessFeatures->forgetCompetition($competition->id);
    }
}
