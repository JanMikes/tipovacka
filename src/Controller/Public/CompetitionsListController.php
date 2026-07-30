<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Enum\CompetitionBrowseScope;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions;
use App\Query\ListMyPlayingCompetitions\ListMyPlayingCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Soutěže" — the one place every relationship to a competition lives (item 07):
 * the ones you play in, the ones you organize, and the ones you could join.
 *
 * Deliberately PUBLIC (no `#[IsGranted]`): an anonymous visitor gets the same
 * page honestly scoped down to the discoverable list. The member-only sections
 * degrade away instead of gating the route — see
 * `tests/Integration/Security/AnonymousReachabilityTest`.
 *
 * **No filters since item 15.** The filter/search cards of both grids are gone,
 * and with them `sport` · `stav` · `hledat` and every `moje-*` key. What survives
 * is **pagination** — `strana` for the public grid, `moje-strana` for the
 * organizer one — because „Zobrazit další" is not a filter and the product owner
 * only asked for the filter card to go.
 *
 * **No hero stat cards since item 24.** The three platform-wide figures (Aktivní
 * soutěže / Hráčů celkem / Sledovaných zápasů) were removed from the page, so this
 * controller no longer calls `GetCompetitionsPageStats`. That query is deliberately
 * kept for a future surface — see its docblock — and this page is no longer one of
 * its callers.
 */
#[Route('/souteze', name: 'competitions_list', methods: ['GET'])]
final class CompetitionsListController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        $viewerId = $user instanceof User ? $user->id : null;

        $discoverable = $this->queryBus->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            viewerId: $viewerId,
            page: max(1, $request->query->getInt('strana', 1)),
        ));

        $playing = [];
        $organized = null;
        $walletBalance = 0;

        if ($user instanceof User) {
            $playing = $this->queryBus->handle(new ListMyPlayingCompetitions(userId: $user->id));

            $organized = $this->queryBus->handle(new ListBrowsableCompetitions(
                scope: CompetitionBrowseScope::Organized,
                viewerId: $user->id,
                page: max(1, $request->query->getInt('moje-strana', 1)),
            ));

            // A verified viewer needs their wallet balance so the card can show the
            // „Máte X/Y, dokoupit" state upfront when they cannot afford the fee —
            // instead of a „Připojit se" button that would bounce to the top-up page.
            if ($user->isVerified) {
                $walletBalance = $this->queryBus->handle(new GetCreditWallet($user->id))->balance;
            }
        }

        return $this->render('public/competitions_list.html.twig', [
            'playing_competitions' => $playing,
            'organized' => $organized,
            'discoverable' => $discoverable,
            'wallet_balance' => $walletBalance,
        ]);
    }
}
