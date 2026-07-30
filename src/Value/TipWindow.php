<?php

declare(strict_types=1);

namespace App\Value;

/**
 * The whole tip window of ONE match in ONE competition, as
 * {@see \App\Service\EffectiveTipDeadlineResolver} computed it: `[opensAt, deadline)`.
 *
 * The END is per viewer (a „Měnit tip" entitlement extends it). The START is not —
 * an opening moment is a property of the match in the competition, identical for
 * every member, and no entitlement moves it. Managers and admins get no free pass
 * either: before `opensAt` nobody tips, on-behalf writes included.
 *
 * `opensAt` null (the default, and what every competition had before 2026-07-30)
 * means „open from the start" — the window is then exactly what it always was.
 *
 * State machine ({@see isClosed} / {@see isWaiting} / {@see isOpen}, evaluated in
 * that order):
 *
 * | now                              | state   |
 * |----------------------------------|---------|
 * | now >= deadline                  | closed  |
 * | opensAt !== null && now < opensAt| waiting |
 * | otherwise                        | open    |
 *
 * Closed deliberately wins: a degenerate window (`opensAt >= deadline`, which the
 * write side rejects but a kickoff moved EARLIER can still produce) must read as
 * „už nejde", never as „ještě chvíli" forever.
 */
final readonly class TipWindow
{
    public function __construct(
        public \DateTimeImmutable $deadline,
        public ?\DateTimeImmutable $opensAt = null,
        public ?string $openingNote = null,
    ) {
    }

    /**
     * The deadline has passed — tips are done here, whatever the opening says.
     */
    public function isClosed(\DateTimeImmutable $now): bool
    {
        return $now >= $this->deadline;
    }

    /**
     * Tipping has not started yet: the match is visible and listed, but no tip
     * may be written for it (by anyone) until {@see $opensAt}.
     */
    public function isWaiting(\DateTimeImmutable $now): bool
    {
        if ($this->isClosed($now)) {
            return false;
        }

        return null !== $this->opensAt && $now < $this->opensAt;
    }

    public function isOpen(\DateTimeImmutable $now): bool
    {
        return !$this->isClosed($now) && !$this->isWaiting($now);
    }
}
