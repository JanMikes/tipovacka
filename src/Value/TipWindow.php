<?php

declare(strict_types=1);

namespace App\Value;

/**
 * The whole tip window of ONE match in ONE competition, as
 * {@see \App\Service\EffectiveTipDeadlineResolver} computed it: `[opensAt, deadline)`.
 *
 * BOTH ends are per viewer, and the same „Měnit tip" entitlement moves them: it
 * extends the deadline and LIFTS the opening, so a buyer tips a waiting match
 * straight away and keeps tipping to the end (product owner, 2026-07-30 — the
 * booster is sold on exactly that promise, „už to nevydržíte a nechcete čekat?").
 * Nothing else opens a window early: managers and admins get no free pass, and
 * on-behalf writes are gated like anyone's own.
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
