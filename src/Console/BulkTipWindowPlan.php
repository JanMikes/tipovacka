<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\SportMatch;
use Symfony\Component\Uid\Uuid;

/**
 * What one {@see BulkSetTipOpeningCommand} run intends to do to every match it
 * walks — parsed once from the console input, then read per match.
 *
 * It exists to keep the deadline decision in ONE place: three flags
 * (`--deadline-own-kickoff`, `--deadline-before-kickoff`,
 * `--only-missing-deadline`) combine into a single question — „what deadline
 * should this match end up with, given what is stored today" — and
 * {@see deadlineFor} answers it.
 */
final readonly class BulkTipWindowPlan
{
    /**
     * True when the run touches the deadline end at all (as opposed to a pure
     * „tipování otevřeno od" pass, which must leave stored uzávěrky alone).
     */
    public bool $changesDeadline;

    /**
     * @param array<string, true> $except sport match UUID (RFC4122) → true
     * @param array<string, true> $only   sport match UUID (RFC4122) → true
     */
    public function __construct(
        public ?\DateTimeImmutable $opensAtUtc,
        public string $note,
        public Uuid $editorId,
        public array $except,
        public array $only,
        public bool $apply,
        public bool $ownKickoffDeadline,
        public ?int $beforeKickoffMinutes,
        public bool $onlyMissingDeadline,
    ) {
        $this->changesDeadline = $ownKickoffDeadline || null !== $beforeKickoffMinutes;
    }

    /**
     * The deadline this match should end up with. `$stored` is the per-match
     * override currently persisted (null = the match follows the competition's
     * default rule, „tipy se zamykají startem soutěže").
     *
     * Returning `$stored` unchanged is the safe default: a bulk opening must
     * never erase an organizer's uzávěrka.
     */
    public function deadlineFor(SportMatch $sportMatch, ?\DateTimeImmutable $stored): ?\DateTimeImmutable
    {
        if (!$this->changesDeadline) {
            return $stored;
        }

        // --only-missing-deadline: a match that already carries an explicit
        // uzávěrka keeps it, whatever this run would otherwise compute.
        if ($this->onlyMissingDeadline && null !== $stored) {
            return $stored;
        }

        if ($this->ownKickoffDeadline) {
            return $sportMatch->kickoffAt;
        }

        \assert(null !== $this->beforeKickoffMinutes); // changesDeadline implies one of the two.

        return $sportMatch->kickoffAt->modify(sprintf('-%d minutes', $this->beforeKickoffMinutes));
    }

    /**
     * Whether {@see deadlineFor} would leave this match's deadline exactly as it
     * is — used to report „nothing to do here" instead of counting a rewrite.
     */
    public function leavesDeadlineUnchanged(SportMatch $sportMatch, ?\DateTimeImmutable $stored): bool
    {
        return $this->deadlineFor($sportMatch, $stored) == $stored;
    }
}
