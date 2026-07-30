<?php

declare(strict_types=1);

namespace App\Twig\Components\Boost;

use App\Entity\Competition;
use App\Entity\User;
use App\Enum\BoostType;
use App\Query\GetBoostPanel\GetBoostPanel;
use App\Query\GetBoostPanel\GetBoostPanelResult;
use App\Query\QueryBus;
use App\Service\Competition\CompetitionMatchProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Boost commerce surface. Two shapes driven by {@see $feature}:
 * - null      → the „Získej výhody" management panel (competition sidebar),
 *                listing all three boosts with owned / buy states;
 * - 'others'  → an inline LOCKED paywall shown where concrete member tips would
 *                be, with a one-click buy. (The distribution bar has its own
 *                surface — {@see \App\Service\Competition\TipStatsProvider} +
 *                the `Match:TipStats` component — because it renders on every
 *                match list and must be batch-resolved.)
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
    public Competition $competition;

    /** null = management panel; 'others' = inline locked paywall. */
    public ?string $feature = null;

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
    ) {
    }

    /**
     * B6 — the competition is fully over (every included match settled), so a
     * boost bought now could no longer unlock anything. Both shapes drop the
     * purchase CTA and say why instead. The command handler refuses too; this is
     * only the polite half.
     */
    public bool $competitionIsOver {
        get => $this->matchProvider->isFullyOver($this->competition);
    }

    public ?GetBoostPanelResult $panel {
        get {
            $user = $this->security->getUser();

            if (!$user instanceof User) {
                return null;
            }

            return $this->queryBus->handle(new GetBoostPanel(
                competitionId: $this->competition->id,
                userId: $user->id,
            ));
        }
    }

    /** The boost that unlocks the requested inline feature. */
    public ?BoostType $featureType {
        get => 'others' === $this->feature ? BoostType::OthersTips : null;
    }

    /** @var list<BoostType> */
    public array $boostTypes {
        get => [BoostType::TipDistribution, BoostType::OthersTips, BoostType::TipChange];
    }
}
