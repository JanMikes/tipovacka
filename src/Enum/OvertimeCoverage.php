<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How much of a soutěž's scope can go on to extra time / penalties at all —
 * the answer to „could `overtime_exact` ever score here?".
 *
 * Whether a drawn match continues is a property of the zdroj zápasů
 * ({@see \App\Entity\MatchSource::$hasOvertime}, see .docs/DOMAIN.md §Scoring),
 * while the scoring rules are configured on the soutěž. A soutěž may therefore
 * enable the rule over zdroje that never play extra time, where it can never
 * award a point. We do not hide or auto-disable the rule for that — a soutěž
 * can span several zdroje with different flags, and the flag can be switched on
 * later — we say so instead, on every surface that offers the rule.
 *
 * The hints are the ONE home of that copy, exactly as
 * {@see \App\Service\Scoring\RulePresetProvider::RULE_COPY} is the one home of
 * the rule's own name: the post-creation rules screen and the create wizard must
 * never word the same caveat differently.
 */
enum OvertimeCoverage: string
{
    /** No zdroj in the scope plays extra time — the rule is dead copy. */
    case None = 'none';

    /** Some do, some do not — the rule scores, but only on part of the scope. */
    case Partial = 'partial';

    /** Every zdroj plays extra time (also the vacuous case of an empty scope). */
    case All = 'all';

    private const string NONE_HINT = 'Žádný ze zdrojů zápasů této soutěže nehraje prodloužení, takže by toto pravidlo nikdy nebodovalo. Prodloužení zapnete v nastavení zdroje zápasů.';
    private const string PARTIAL_HINT = 'Vítěze tipují hráči jen u zápasů ze zdrojů, které prodloužení hrají.';
    private const string OWN_MATCHES_HINT = 'Vlastní zápasy mají prodloužení vypnuté, zapnete ho v kroku Zápasy soutěže.';

    /**
     * The caveat shown next to the overtime rule, or null when there is nothing
     * honest to warn about (every zdroj plays extra time).
     */
    public function hint(): ?string
    {
        return match ($this) {
            self::None => self::NONE_HINT,
            self::Partial => self::PARTIAL_HINT,
            self::All => null,
        };
    }

    /**
     * The same caveat while the soutěž is still being composed in the wizard.
     * A soutěž made only of its own matches has no zdroj settings page to send
     * the organizer to yet, so it is pointed at the step that holds the switch.
     */
    public function draftHint(bool $ownMatchesOnly): ?string
    {
        return self::None === $this && $ownMatchesOnly ? self::OWN_MATCHES_HINT : $this->hint();
    }

    /**
     * Resolves the coverage of a scope holding $total zdroje, of which
     * $withOvertime play extra time. An empty scope is {@see self::All}:
     * there is no zdroj to warn about.
     */
    public static function fromCounts(int $total, int $withOvertime): self
    {
        if (0 === $total || $withOvertime === $total) {
            return self::All;
        }

        return 0 === $withOvertime ? self::None : self::Partial;
    }
}
