<?php

declare(strict_types=1);

namespace App\Twig\Components\Boost;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Query\GetBoostPanel\GetBoostPanel;
use App\Query\GetBoostPanel\GetBoostPanelResult;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipChangeUnlock;
use App\Value\TipChangeUnlockOffer;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Boost commerce surface. Three shapes driven by {@see $feature}:
 * - null         → the „Získej výhody" management panel (listed all three boosts
 *                   with owned / buy states; item 35 removed its only call site —
 *                   every booster is now sold at the thing it unlocks);
 * - 'others'     → an inline LOCKED paywall shown where concrete member tips would
 *                   be, with a one-click buy. (The distribution bar has its own
 *                   surface — {@see \App\Service\Competition\TipStatsProvider} +
 *                   the `Match:TipStats` component — because it renders on every
 *                   match list and must be batch-resolved.)
 * - 'tip_change' → the „Počkejte si na sestavy" paywall, shown where „Měnit tip"
 *                   is being denied: the locked tip form of a match, and
 *                   `/souteze/{id}/moje-tipy`. It names the CONCRETE moment the
 *                   buyer would get back ({@see $tipChangeOffer}) and renders
 *                   nothing at all when the purchase would gain them nothing.
 *
 * Premium competitions get features from the manager (not per-player), so the
 * paywall becomes a „✓ PRÉMIUM" note; `none` competitions that merely hide tips
 * until a match is played show a plain lock note (nothing to buy).
 *
 * See .docs/DOMAIN.md §Monetization.
 */
#[AsTwigComponent('Boost:Panel')]
final class BoostPanel
{
    /**
     * The soutěž this panel sells for. A caller that holds only its id — a Live
     * Component whose props are scalars ({@see \App\Twig\Components\Guess\GuessSubmitForm})
     * — passes {@see $competitionId} instead; either one is enough, and
     * {@see $resolvedCompetition} is what the component itself reads.
     */
    public ?Competition $competition = null;

    /** Alternative to {@see $competition}: its UUID, resolved on demand. */
    public ?string $competitionId = null;

    /** null = management panel; 'others' / 'tip_change' = inline locked paywall. */
    public ?string $feature = null;

    /**
     * `feature="tip_change"` only: the match whose regained deadline to name.
     * Leave it null on a surface that denies „Měnit tip" competition-wide
     * (`/souteze/{id}/moje-tipy`) — the panel then names the soonest match the
     * boost would hand back, so the printed moment stays unambiguous.
     */
    public ?SportMatch $sportMatch = null;

    /**
     * How the `feature` paywall renders (B27):
     * - 'inline' → the self-contained striped panel (it IS the whole paywall on
     *              the tip matrix and the guess page);
     * - 'bare'   → just the control, in the gold `.dist-unlock` vocabulary, for a
     *              caller that already owns the card, the blurred skeleton and the
     *              lock coin (match detail's „Pořadí za zápas"). It brings no
     *              container and no colour of its own, so the two paywalls of a
     *              match detail read as ONE treatment.
     */
    public string $shape = 'inline';

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly TipChangeUnlock $tipChangeUnlock,
        private readonly CompetitionRepository $competitionRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /** The soutěž, however the caller named it. */
    public Competition $resolvedCompetition {
        get {
            if (null !== $this->competition) {
                return $this->competition;
            }

            if (null === $this->competitionId) {
                throw new \LogicException('Boost:Panel needs either a `competition` or a `competitionId`.');
            }

            return $this->competitionRepository->get(Uuid::fromString($this->competitionId));
        }
    }

    /**
     * B6 — the competition is fully over (every included match settled), so a
     * boost bought now could no longer unlock anything. Both shapes drop the
     * purchase CTA and say why instead. The command handler refuses too; this is
     * only the polite half.
     */
    public bool $competitionIsOver {
        get => $this->matchProvider->isFullyOver($this->resolvedCompetition);
    }

    public ?GetBoostPanelResult $panel {
        get {
            $user = $this->security->getUser();

            if (!$user instanceof User) {
                return null;
            }

            return $this->queryBus->handle(new GetBoostPanel(
                competitionId: $this->resolvedCompetition->id,
                userId: $user->id,
            ));
        }
    }

    /** The boost that unlocks the requested inline feature. */
    public ?BoostType $featureType {
        get => match ($this->feature) {
            'others' => BoostType::OthersTips,
            'tip_change' => BoostType::TipChange,
            default => null,
        };
    }

    /**
     * The whole `tip_change` offer, or null when there is nothing to sell here.
     * Null covers every „offer it only where it can do something" case at once:
     * a non-`boosts` competition (premium grants it, `none` has no shop), a
     * viewer who already owns it, B6 („soutěž už skončila"), an anonymous
     * viewer, and — via {@see TipChangeUnlock} — a match whose deadline the boost
     * could not extend anyway.
     */
    public ?TipChangeUnlockOffer $tipChangeOffer {
        get {
            if ('tip_change' !== $this->feature) {
                return null;
            }

            $user = $this->security->getUser();
            $panel = $this->panel;

            if (!$user instanceof User || null === $panel) {
                return null;
            }

            $competition = $this->resolvedCompetition;

            if (CompetitionMonetization::Boosts !== $competition->monetization
                || $panel->hasTipChange()
                || $this->competitionIsOver
            ) {
                return null;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());

            return null !== $this->sportMatch
                ? $this->tipChangeUnlock->forMatch($competition, $this->sportMatch, $user, $now)
                : $this->tipChangeUnlock->nextInCompetition($competition, $user, $now);
        }
    }

    /** @var list<BoostType> */
    public array $boostTypes {
        get => [BoostType::TipDistribution, BoostType::OthersTips, BoostType::TipChange];
    }
}
