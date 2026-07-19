<?php

declare(strict_types=1);

namespace App\Twig\Components\Boost;

use App\Entity\Competition;
use App\Entity\User;
use App\Enum\BoostType;
use App\Query\GetBoostPanel\GetBoostPanel;
use App\Query\GetBoostPanel\GetBoostPanelResult;
use App\Query\QueryBus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Boost commerce surface. Two shapes driven by {@see $feature}:
 * - null       → the „Tvoje vylepšení" management panel (competition sidebar),
 *                 listing all three boosts with owned / buy states;
 * - 'distribution' / 'others' → an inline LOCKED paywall shown where the
 *                 distribution bar / concrete tips would be, with a one-click buy.
 *
 * Premium competitions get features from the manager (not per-player), so the
 * paywall becomes a „✓ PRÉMIUM" note; `none` competitions that merely hide tips
 * before the deadline show a plain lock note (nothing to buy).
 *
 * See .docs/DOMAIN.md §Monetization.
 */
#[AsTwigComponent('Boost:Panel')]
final class BoostPanel
{
    public Competition $competition;

    /** null = management panel; 'distribution' | 'others' = inline locked paywall. */
    public ?string $feature = null;

    /** Player count for the „Uvidíš, jak tipuje X hráčů" copy (distribution paywall). */
    public int $playerCount = 0;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
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
        get => match ($this->feature) {
            'distribution' => BoostType::TipDistribution,
            'others' => BoostType::OthersTips,
            default => null,
        };
    }

    /** @var list<BoostType> */
    public array $boostTypes {
        get => [BoostType::TipDistribution, BoostType::OthersTips, BoostType::TipChange];
    }
}
