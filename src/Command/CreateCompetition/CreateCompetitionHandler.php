<?php

declare(strict_types=1);

namespace App\Command\CreateCompetition;

use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\CompetitionSourcesSportMismatch;
use App\Exception\TeamNotInSource;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\CompetitionSourceRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\MatchSourceRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\SportRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Rule\RuleRegistry;
use App\Service\Competition\OwnMatchesSource;
use App\Service\Competition\PinGenerator;
use App\Service\Competition\ShareableLinkTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\Invitation\CompetitionInviter;
use App\Service\Team\TeamResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Composes the whole competition aggregate in ONE transaction (the command
 * bus's `doctrine_transaction` middleware flushes on success, rolls back on any
 * exception). Every building block runs inline without an intermediate flush,
 * so a failure anywhere — including strict invitation validation — leaves no
 * orphan source/competition behind.
 */
#[AsMessageHandler]
final readonly class CreateCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private MatchSourceRepository $matchSourceRepository,
        private SportRepository $sportRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionMatchSelectionRepository $selectionRepository,
        private CompetitionTeamFilterRepository $teamFilterRepository,
        private CompetitionRuleConfigurationRepository $ruleConfigurationRepository,
        private CompetitionSourceRepository $competitionSourceRepository,
        private TeamRepository $teamRepository,
        private TeamResolver $teamResolver,
        private UserRepository $userRepository,
        private RuleRegistry $ruleRegistry,
        private CompetitionInviter $inviter,
        private OwnMatchesSource $ownMatchesSource,
        private ProvideIdentity $identity,
        private PinGenerator $pinGenerator,
        private ShareableLinkTokenGenerator $linkTokenGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateCompetitionCommand $command): Competition
    {
        $owner = $this->userRepository->get($command->ownerId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matchSource = $command->fromScratch
            ? $this->createPrivateMatchSource($command, null, $now)
            : $this->matchSourceRepository->get($this->requireSourceId($command));

        // From-scratch sources hold no matches yet — a subset selection is
        // meaningless, so such competitions are always mode All.
        $selectionMode = $command->fromScratch ? CompetitionMatchSelectionMode::All : $command->selectionMode;

        $competition = new Competition(
            id: $this->identity->next(),
            headlineSource: $matchSource,
            owner: $owner,
            name: $command->name,
            description: $command->description,
            pin: $command->withPin ? $this->resolvePin($command->pin) : null,
            shareableLinkToken: $command->shareableLinkToken ?? $this->linkTokenGenerator->generate(),
            createdAt: $now,
            hideOthersTipsBeforeDeadline: $command->hideOthersTipsBeforeDeadline,
            monetization: $command->monetization,
        );

        $this->competitionRepository->save($competition);

        // The competition's scope is the UNION of its layers. The command's own
        // source fields describe the first one; `additionalSources` the rest.
        $this->attachLayer(
            competition: $competition,
            matchSource: $matchSource,
            selectionMode: $selectionMode,
            includePlayoff: $command->includePlayoff,
            selectedMatchIds: $command->selectedMatchIds,
            filterTeamIds: $command->filterTeamIds,
            position: 0,
            now: $now,
        );

        $position = 0;
        // „Moje zápasy" is ONE private zdroj however many specs ask for it, and
        // it is the same one a from-scratch competition already made.
        $ownMatchesSource = $command->fromScratch ? $matchSource : null;

        foreach ($command->additionalSources as $spec) {
            $additionalSource = null === $spec->matchSourceId
                ? $ownMatchesSource ??= $this->createPrivateMatchSource($command, $matchSource->sport, $now)
                : $this->matchSourceRepository->get($spec->matchSourceId);

            // One sport per soutěž: the rules are configured once, in the
            // sport's own vocabulary, so a mixed scope has no coherent ruleset.
            if (!$additionalSource->sport->id->equals($matchSource->sport->id)) {
                throw CompetitionSourcesSportMismatch::between($matchSource->sport->name, $additionalSource->sport->name);
            }

            // Two layers over one zdroj would double every match; the union is
            // already what „add it again" would mean, so the later spec is the
            // one that counts.
            if (null !== $competition->sourceFor($additionalSource->id)) {
                continue;
            }

            $this->attachLayer(
                competition: $competition,
                matchSource: $additionalSource,
                selectionMode: $spec->selectionMode,
                includePlayoff: $spec->includePlayoff,
                selectedMatchIds: $spec->selectedMatchIds,
                filterTeamIds: $spec->filterTeamIds,
                position: ++$position,
                now: $now,
            );
        }

        $this->membershipRepository->save(new Membership(
            id: $this->identity->next(),
            competition: $competition,
            user: $owner,
            joinedAt: $now,
        ));

        $this->provisionRules($command, $competition, $now);

        // Strict: a malformed address throws InvalidInvitationEmails → rollback.
        // Emails themselves are sent by the post-commit CompetitionInvitationSent
        // handler, never inside this transaction.
        $this->inviter->invite(
            competition: $competition,
            inviter: $owner,
            rawEntries: $command->inviteEmails,
            now: $now,
            strict: true,
        );

        return $competition;
    }

    /**
     * The competition's own private zdroj („Moje zápasy"). Its sport comes from
     * the command when the soutěž is from-scratch, and otherwise from the zdroj
     * it is being added ALONGSIDE — an own-matches layer added to an existing
     * zdroj carries no sport of its own, and all layers share one anyway.
     */
    private function createPrivateMatchSource(CreateCompetitionCommand $command, ?Sport $inheritedSport, \DateTimeImmutable $now): MatchSource
    {
        $sport = null !== $command->sportId ? $this->sportRepository->get($command->sportId) : $inheritedSport;

        if (null === $sport) {
            throw new \InvalidArgumentException('A from-scratch competition requires a sport.');
        }

        return $this->ownMatchesSource->create(
            $sport,
            $this->userRepository->get($command->ownerId),
            $command->name,
            $now,
            $command->ownMatchesHaveOvertime,
        );
    }

    /**
     * Persists one scope layer plus whatever its mode needs (explicit match
     * selections or team filters). The single place a layer is born, so every
     * zdroj a soutěž draws from is validated the same way.
     *
     * @param list<Uuid> $selectedMatchIds
     * @param list<Uuid> $filterTeamIds
     */
    private function attachLayer(
        Competition $competition,
        MatchSource $matchSource,
        CompetitionMatchSelectionMode $selectionMode,
        bool $includePlayoff,
        array $selectedMatchIds,
        array $filterTeamIds,
        int $position,
        \DateTimeImmutable $now,
    ): void {
        $layer = new CompetitionSource(
            id: $this->identity->next(),
            competition: $competition,
            matchSource: $matchSource,
            addedAt: $now,
            selectionMode: $selectionMode,
            includePlayoff: $includePlayoff,
            position: $position,
        );
        $this->competitionSourceRepository->save($layer);
        $competition->attachSource($layer);

        if (CompetitionMatchSelectionMode::Subset === $selectionMode) {
            $this->createSelections($selectedMatchIds, $layer, $now);
        }

        if (CompetitionMatchSelectionMode::Teams === $selectionMode) {
            $this->createTeamFilters($filterTeamIds, $layer, $now);
        }
    }

    /**
     * @param list<Uuid> $selectedMatchIds
     */
    private function createSelections(
        array $selectedMatchIds,
        CompetitionSource $layer,
        \DateTimeImmutable $now,
    ): void {
        foreach ($selectedMatchIds as $sportMatchId) {
            $sportMatch = $this->sportMatchRepository->get($sportMatchId);

            // Defensive: only matches of this layer's source can be selected.
            if (!$sportMatch->matchSource->id->equals($layer->matchSource->id) || null !== $sportMatch->deletedAt) {
                continue;
            }

            $this->selectionRepository->save(new CompetitionMatchSelection(
                id: $this->identity->next(),
                competition: $layer->competition,
                competitionSource: $layer,
                sportMatch: $sportMatch,
                addedAt: $now,
            ));
        }
    }

    /**
     * One CompetitionTeamFilter row per selected team. Each team must belong to
     * the source's resolution scope (same hybrid rule as team resolution) —
     * a foreign / cross-sport team id aborts the whole creation (TeamNotInSource).
     * Duplicate ids collapse to a single row.
     */
    /**
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
     * Creates one CompetitionRuleConfiguration row per registered rule with its
     * final state: rule defaults, overlaid by the wizard's changes. The
     * post-commit {@see \App\Event\CompetitionCreatedRuleProvisionerHandler} then
     * finds every row already present and is a harmless no-op.
     */
    private function provisionRules(CreateCompetitionCommand $command, Competition $competition, \DateTimeImmutable $now): void
    {
        foreach ($this->ruleRegistry->all() as $identifier => $rule) {
            $change = $command->ruleChanges[$identifier] ?? null;

            if (null === $change) {
                $enabled = $rule->enabledByDefault;
                $points = $rule->defaultPoints;
            } else {
                $enabled = $change['enabled'];
                // Keep the rule's default points for a disabled rule so re-enabling
                // later starts from a sane value.
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

    private function requireSourceId(CreateCompetitionCommand $command): Uuid
    {
        return $command->matchSourceId
            ?? throw new \InvalidArgumentException('A curated/existing-source competition requires a match source id.');
    }

    /**
     * Honours the wizard's WYSIWYG PIN preview but self-heals a (vanishingly
     * rare) collision by generating a fresh unique PIN instead of failing.
     */
    private function resolvePin(?string $preview): string
    {
        if (null !== $preview && !$this->competitionRepository->pinExists($preview)) {
            return $preview;
        }

        return $this->pinGenerator->generate();
    }
}
