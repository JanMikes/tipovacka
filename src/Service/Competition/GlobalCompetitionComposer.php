<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Exception\GlobalCompetitionRequiresCuratedSource;
use App\Exception\TeamNotInSource;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\CompetitionSourceRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\MembershipRepository;
use App\Repository\TeamRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use App\Service\Team\TeamResolver;
use Symfony\Component\Uid\Uuid;

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
        private CompetitionSourceRepository $competitionSourceRepository,
        private CompetitionTeamFilterRepository $teamFilterRepository,
        private TeamRepository $teamRepository,
        private TeamResolver $teamResolver,
        private RuleRegistry $ruleRegistry,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * @param array<string, array{enabled: bool, points: int}> $ruleChanges
     * @param list<Uuid>                                       $filterTeamIds only used when $selectionMode is Teams
     */
    public function compose(
        MatchSource $matchSource,
        User $admin,
        string $name,
        ?string $description,
        int $entryFeeCredits,
        CompetitionMonetization $monetization,
        array $ruleChanges,
        \DateTimeImmutable $now,
        CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        array $filterTeamIds = [],
    ): Competition {
        if (!$matchSource->isCurated) {
            throw GlobalCompetitionRequiresCuratedSource::forSource($matchSource->id);
        }

        // A global competition never hand-picks matches (Subset is a private-only
        // affordance) — it is either every source match (All) or a team filter.
        if (CompetitionMatchSelectionMode::Subset === $selectionMode) {
            $selectionMode = CompetitionMatchSelectionMode::All;
        }

        $competition = new Competition(
            id: $this->identity->next(),
            headlineSource: $matchSource,
            owner: $admin,
            name: $name,
            description: $description,
            pin: null,
            // Global competitions are joined via the entry-fee flow ONLY — never a
            // PIN or shareable link (a token would be a fee-free back door). Mirror
            // pin: null. See fix in .docs/DOMAIN.md §Global competitions.
            shareableLinkToken: null,
            createdAt: $now,
            hideOthersTipsBeforeDeadline: false,
            monetization: $monetization,
            isGlobal: true,
            entryFeeCredits: $entryFeeCredits,
        );

        $this->competitionRepository->save($competition);

        $layer = new CompetitionSource(
            id: $this->identity->next(),
            competition: $competition,
            matchSource: $matchSource,
            addedAt: $now,
            selectionMode: $selectionMode,
            includePlayoff: true,
            position: 0,
        );
        $this->competitionSourceRepository->save($layer);
        $competition->attachSource($layer);

        if (CompetitionMatchSelectionMode::Teams === $selectionMode) {
            $this->createTeamFilters($filterTeamIds, $layer, $now);
        }

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
     * One CompetitionTeamFilter row per selected team, each validated against the
     * source's resolution scope (curated source ⇒ global directory teams of its
     * sport). A foreign / cross-sport team id aborts the whole creation.
     *
     * @param list<Uuid> $filterTeamIds
     */
    private function createTeamFilters(
        array $filterTeamIds,
        CompetitionSource $layer,
        \DateTimeImmutable $now,
    ): void {
        $matchSource = $layer->matchSource;
        $seen = [];

        foreach ($filterTeamIds as $teamId) {
            $key = $teamId->toRfc4122();

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $team = $this->teamRepository->get($teamId);

            if (!$this->teamResolver->belongsToSourceScope($matchSource, $team)) {
                throw TeamNotInSource::create($teamId, $matchSource->id);
            }

            $this->teamFilterRepository->save(new CompetitionTeamFilter(
                id: $this->identity->next(),
                competition: $layer->competition,
                competitionSource: $layer,
                team: $team,
                addedAt: $now,
            ));
        }
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
