<?php

declare(strict_types=1);

namespace App\Command\UpdateTournamentRuleConfiguration;

use App\Entity\TournamentRuleConfiguration;
use App\Repository\TournamentRepository;
use App\Repository\TournamentRuleConfigurationRepository;
use App\Repository\UserRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTournamentRuleConfigurationHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private UserRepository $userRepository,
        private TournamentRuleConfigurationRepository $configurationRepository,
        private RuleRegistry $ruleRegistry,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateTournamentRuleConfigurationCommand $command): void
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);
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

            $configuration = $this->configurationRepository->findOne($tournament->id, $ruleIdentifier);

            if (null === $configuration) {
                $configuration = new TournamentRuleConfiguration(
                    id: $this->identity->next(),
                    tournament: $tournament,
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

        $tournament->recordRulesChanged($editor, $now);
    }
}
