<?php

declare(strict_types=1);

namespace App\Service\Feed;

/**
 * The outcome of pairing a source's existing matches with a provider's feed, so
 * they can adopt the provider's ids. Everything ambiguous is REPORTED rather
 * than guessed: a wrong externalId is worse than a missing one, because the next
 * sync would then write one match's result onto another.
 */
final class ExternalIdAdoption
{
    /** @var list<array{sportMatchId: string, externalId: string, label: string}> */
    public private(set) array $adopted = [];

    /** Already carried this exact externalId — nothing to do. */
    public private(set) int $alreadyLinked = 0;

    /** @var list<string> matches with an externalId from a DIFFERENT namespace */
    public private(set) array $conflicting = [];

    /** @var list<string> feed rows with no counterpart in the database */
    public private(set) array $unmatchedSnapshots = [];

    /** @var list<string> stored matches no feed row could be paired with */
    public private(set) array $unmatchedMatches = [];

    /** @var list<string> pairings refused because more than one candidate fit */
    public private(set) array $ambiguous = [];

    /** @var list<string> feed team spellings the directory does not know */
    public private(set) array $unresolvedTeams = [];

    public function __construct(
        public readonly bool $dryRun,
    ) {
    }

    public function addAdopted(string $sportMatchId, string $externalId, string $label): void
    {
        $this->adopted[] = ['sportMatchId' => $sportMatchId, 'externalId' => $externalId, 'label' => $label];
    }

    public function addAlreadyLinked(): void
    {
        ++$this->alreadyLinked;
    }

    public function addConflicting(string $label): void
    {
        $this->conflicting[] = $label;
    }

    public function addUnmatchedSnapshot(string $label): void
    {
        $this->unmatchedSnapshots[] = $label;
    }

    public function addUnmatchedMatch(string $label): void
    {
        $this->unmatchedMatches[] = $label;
    }

    public function addAmbiguous(string $label): void
    {
        $this->ambiguous[] = $label;
    }

    public function addUnresolvedTeam(string $teamName): void
    {
        if (!in_array($teamName, $this->unresolvedTeams, true)) {
            $this->unresolvedTeams[] = $teamName;
        }
    }
}
