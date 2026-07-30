<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\TipChangeUnlockOffer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * „What would „Počkejte si na sestavy" actually buy me, and until when?" — the
 * single answer behind the `tip_change` paywall (item 35).
 *
 * Both ends come from {@see EffectiveTipDeadlineResolver}: the viewer's CURRENT
 * deadline from the real one, and the deadline they would get back from a second
 * instance whose entitlement source always says yes
 * ({@see TipChangeGrantedEntitlements}). Nothing here recomputes „kickoff minus
 * offset" — that formula stays where it belongs, and this service keeps following
 * it for free.
 *
 * An offer is returned ONLY when the purchase would move something: the match
 * still takes tips, the regained deadline is in the future, and EITHER it is
 * strictly later than what the viewer already has OR the match is still waiting
 * for its „tipování otevřeno od" (the boost lifts that too, so „cannot tip at
 * all" → „can tip now" is a gain even with an unchanged deadline).
 *
 * „No offer" is therefore still the correct answer for an already-open match
 * whose deadline is its own kickoff (a late-added match, or the competition's
 * very first one) — the boost has nothing to move there, so it must not be sold
 * as if it had.
 */
final readonly class TipChangeUnlock
{
    public function __construct(
        private EffectiveTipDeadlineResolver $resolver,
        #[Autowire(service: 'app.tip_deadline_resolver.tip_change_granted')]
        private EffectiveTipDeadlineResolver $resolverAsIfEntitled,
        private CompetitionMatchProvider $matchProvider,
    ) {
    }

    /** What the boost would buy for ONE named match (the match page). */
    public function forMatch(
        Competition $competition,
        SportMatch $sportMatch,
        User $user,
        \DateTimeImmutable $now,
    ): ?TipChangeUnlockOffer {
        if (!$sportMatch->isOpenForGuesses) {
            return null;
        }

        $unlockedWindow = $this->resolverAsIfEntitled->windowFor($competition, $sportMatch, $user);
        $unlocked = $unlockedWindow->deadline;

        if ($unlocked <= $now) {
            return null;
        }

        $current = $this->resolver->windowFor($competition, $sportMatch, $user);

        // The boost buys either end. Extending the deadline is the classic gain;
        // lifting a „tipování otevřeno od" that has not arrived yet is the other,
        // and it is a gain even when the deadline does not move an inch — the
        // viewer goes from „cannot tip at all" to „can tip now".
        if ($unlocked <= $current->deadline && !$current->isWaiting($now)) {
            return null;
        }

        return new TipChangeUnlockOffer($sportMatch, $unlocked);
    }

    /**
     * The SOONEST match the boost would hand back in this competition — for a
     * surface that denies „Měnit tip" competition-wide rather than per match
     * (`/souteze/{id}/moje-tipy`). Naming that match is what keeps the printed
     * moment unambiguous there.
     */
    public function nextInCompetition(
        Competition $competition,
        User $user,
        \DateTimeImmutable $now,
    ): ?TipChangeUnlockOffer {
        $matches = array_values(array_filter(
            $this->matchProvider->matchesFor($competition),
            static fn (SportMatch $match): bool => $match->isOpenForGuesses,
        ));

        if ([] === $matches) {
            return null;
        }

        // Batched on purpose: one per-match override query for the whole
        // competition instead of one per row.
        $unlockedWindows = $this->resolverAsIfEntitled->windowsFor($competition, $matches, $user);
        $currentWindows = $this->resolver->windowsFor($competition, $matches, $user);

        $best = null;

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $unlocked = $unlockedWindows[$key]->deadline;
            $current = $currentWindows[$key];

            if ($unlocked <= $now) {
                continue;
            }

            // Either end counts — see forMatch().
            if ($unlocked <= $current->deadline && !$current->isWaiting($now)) {
                continue;
            }

            if (null === $best || $unlocked < $best->deadline) {
                $best = new TipChangeUnlockOffer($match, $unlocked);
            }
        }

        return $best;
    }
}
