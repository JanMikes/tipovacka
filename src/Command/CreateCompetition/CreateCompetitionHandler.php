<?php

declare(strict_types=1);

namespace App\Command\CreateCompetition;

use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\MatchSourceRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\SportRepository;
use App\Repository\UserRepository;
use App\Rule\RuleRegistry;
use App\Service\Competition\PinGenerator;
use App\Service\Competition\ShareableLinkTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\Invitation\CompetitionInviter;
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
        private CompetitionRuleConfigurationRepository $ruleConfigurationRepository,
        private UserRepository $userRepository,
        private RuleRegistry $ruleRegistry,
        private CompetitionInviter $inviter,
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
            ? $this->createPrivateMatchSource($command, $now)
            : $this->matchSourceRepository->get($this->requireSourceId($command));

        // From-scratch sources hold no matches yet — a subset selection is
        // meaningless, so such competitions are always mode All.
        $selectionMode = $command->fromScratch ? CompetitionMatchSelectionMode::All : $command->selectionMode;

        $competition = new Competition(
            id: $this->identity->next(),
            matchSource: $matchSource,
            owner: $owner,
            name: $command->name,
            description: $command->description,
            pin: $command->withPin ? $this->resolvePin($command->pin) : null,
            shareableLinkToken: $command->shareableLinkToken ?? $this->linkTokenGenerator->generate(),
            createdAt: $now,
            selectionMode: $selectionMode,
            includePlayoff: CompetitionMatchSelectionMode::All === $selectionMode ? $command->includePlayoff : true,
            hideOthersTipsBeforeDeadline: $command->hideOthersTipsBeforeDeadline,
            monetization: $command->monetization,
        );

        $this->competitionRepository->save($competition);

        if (CompetitionMatchSelectionMode::Subset === $selectionMode) {
            $this->createSelections($command, $competition, $matchSource, $now);
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

    private function createPrivateMatchSource(CreateCompetitionCommand $command, \DateTimeImmutable $now): MatchSource
    {
        if (null === $command->sportId) {
            throw new \InvalidArgumentException('A from-scratch competition requires a sport.');
        }

        $matchSource = new MatchSource(
            id: $this->identity->next(),
            sport: $this->sportRepository->get($command->sportId),
            owner: $this->userRepository->get($command->ownerId),
            kind: MatchSourceKind::Private,
            name: $command->name,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );

        $this->matchSourceRepository->save($matchSource);

        return $matchSource;
    }

    private function createSelections(
        CreateCompetitionCommand $command,
        Competition $competition,
        MatchSource $matchSource,
        \DateTimeImmutable $now,
    ): void {
        foreach ($command->selectedMatchIds as $sportMatchId) {
            $sportMatch = $this->sportMatchRepository->get($sportMatchId);

            // Defensive: only matches of the chosen source can be selected.
            if (!$sportMatch->matchSource->id->equals($matchSource->id) || null !== $sportMatch->deletedAt) {
                continue;
            }

            $this->selectionRepository->save(new CompetitionMatchSelection(
                id: $this->identity->next(),
                competition: $competition,
                sportMatch: $sportMatch,
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
