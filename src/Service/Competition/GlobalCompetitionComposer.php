<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Exception\GlobalCompetitionRequiresCuratedSource;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\MembershipRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;

/**
 * Shared composition of a global competition aggregate — the competition
 * (isGlobal, mode All, owner = admin), the admin's owner membership and the
 * per-rule configuration (defaults overlaid by the admin's changes). Used both
 * by the standalone create-global flow and by "create curated source + global
 * competition in one go", so both run inside a single transaction.
 */
final readonly class GlobalCompetitionComposer
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionRuleConfigurationRepository $ruleConfigurationRepository,
        private RuleRegistry $ruleRegistry,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * @param array<string, array{enabled: bool, points: int}> $ruleChanges
     */
    public function compose(
        MatchSource $matchSource,
        User $admin,
        string $name,
        int $entryFeeCredits,
        CompetitionMonetization $monetization,
        array $ruleChanges,
        \DateTimeImmutable $now,
    ): Competition {
        if (!$matchSource->isCurated) {
            throw GlobalCompetitionRequiresCuratedSource::forSource($matchSource->id);
        }

        $competition = new Competition(
            id: $this->identity->next(),
            matchSource: $matchSource,
            owner: $admin,
            name: $name,
            description: null,
            pin: null,
            // Global competitions are joined via the entry-fee flow ONLY — never a
            // PIN or shareable link (a token would be a fee-free back door). Mirror
            // pin: null. See fix in .docs/DOMAIN.md §Global competitions.
            shareableLinkToken: null,
            createdAt: $now,
            selectionMode: CompetitionMatchSelectionMode::All,
            includePlayoff: true,
            hideOthersTipsBeforeDeadline: false,
            monetization: $monetization,
            isGlobal: true,
            entryFeeCredits: $entryFeeCredits,
        );

        $this->competitionRepository->save($competition);

        $this->membershipRepository->save(new Membership(
            id: $this->identity->next(),
            competition: $competition,
            user: $admin,
            joinedAt: $now,
        ));

        $this->provisionRules($ruleChanges, $competition, $now);

        return $competition;
    }

    /**
     * One CompetitionRuleConfiguration row per registered rule: rule defaults,
     * overlaid by the admin's changes (mirrors CreateCompetitionHandler).
     *
     * @param array<string, array{enabled: bool, points: int}> $ruleChanges
     */
    private function provisionRules(array $ruleChanges, Competition $competition, \DateTimeImmutable $now): void
    {
        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $change = $ruleChanges[$identifier] ?? null;

            if (null === $change) {
                $enabled = $rule->enabledByDefault;
                $points = $rule->defaultPoints;
            } else {
                $enabled = $change['enabled'];
                $points = $enabled ? max(0, $change['points']) : $rule->defaultPoints;
            }

            $this->ruleConfigurationRepository->save(new CompetitionRuleConfiguration(
                id: $this->identity->next(),
                competition: $competition,
                ruleIdentifier: $identifier,
                enabled: $enabled,
                points: $points,
                now: $now,
            ));
        }
    }
}
