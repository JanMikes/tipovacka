<?php

declare(strict_types=1);

namespace App\Command\UpdateMatchSourceRuleConfiguration;

use App\Entity\MatchSourceRuleConfiguration;
use App\Repository\MatchSourceRepository;
use App\Repository\MatchSourceRuleConfigurationRepository;
use App\Repository\UserRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateMatchSourceRuleConfigurationHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private UserRepository $userRepository,
        private MatchSourceRuleConfigurationRepository $configurationRepository,
        private RuleRegistry $ruleRegistry,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateMatchSourceRuleConfigurationCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
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

            $configuration = $this->configurationRepository->findOne($matchSource->id, $ruleIdentifier);

            if (null === $configuration) {
                $configuration = new MatchSourceRuleConfiguration(
                    id: $this->identity->next(),
                    matchSource: $matchSource,
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

        $matchSource->recordRulesChanged($editor, $now);
    }
}
