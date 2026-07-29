<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Enum\CompetitionBrowseScope;
use App\Enum\CompetitionStateFilter;
use App\Enum\CompetitionVisibilityFilter;
use App\Query\GetCompetitionsPageStats\GetCompetitionsPageStats;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions;
use App\Query\ListMyPlayingCompetitions\ListMyPlayingCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * „Soutěže" — the one place every relationship to a competition lives (item 07):
 * the ones you play in, the ones you organize, and the ones you could join.
 *
 * Deliberately PUBLIC (no `#[IsGranted]`): an anonymous visitor gets the same
 * page honestly scoped down to the discoverable list. The member-only sections
 * degrade away instead of gating the route — see
 * `tests/Integration/Security/AnonymousReachabilityTest`.
 *
 * Filters are query params, not JS state, so a filtered view survives a reload
 * and can be shared. The public list owns the short names (`sport`, `stav`,
 * `hledat`, `strana`); the organizer list prefixes its own with `moje-`.
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

        $publicSportId = $this->uuidParam($request, 'sport');
        $publicState = CompetitionStateFilter::fromRequest($request->query->getString('stav') ?: null);
        $publicSearch = trim($request->query->getString('hledat'));

        $discoverable = $this->queryBus->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            viewerId: $viewerId,
            sportId: $publicSportId,
            state: $publicState,
            search: '' !== $publicSearch ? $publicSearch : null,
            page: max(1, $request->query->getInt('strana', 1)),
        ));

        $stats = $this->queryBus->handle(new GetCompetitionsPageStats(viewerId: $viewerId));

        $playing = [];
        $organized = null;
        $organizedSportId = null;
        $organizedVisibility = CompetitionVisibilityFilter::All;
        $organizedState = CompetitionStateFilter::All;
        $organizedSearch = '';
        $walletBalance = 0;

        if ($user instanceof User) {
            $playing = $this->queryBus->handle(new ListMyPlayingCompetitions(userId: $user->id));

            $organizedSportId = $this->uuidParam($request, 'moje-sport');
            $organizedVisibility = CompetitionVisibilityFilter::fromRequest($request->query->getString('moje-viditelnost') ?: null);
            $organizedState = CompetitionStateFilter::fromRequest($request->query->getString('moje-stav') ?: null);
            $organizedSearch = trim($request->query->getString('moje-hledat'));

            $organized = $this->queryBus->handle(new ListBrowsableCompetitions(
                scope: CompetitionBrowseScope::Organized,
                viewerId: $user->id,
                sportId: $organizedSportId,
                visibility: $organizedVisibility,
                state: $organizedState,
                search: '' !== $organizedSearch ? $organizedSearch : null,
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
            'stats' => $stats,
            'playing_competitions' => $playing,
            'organized' => $organized,
            'organized_filters' => [
                'sport' => $organizedSportId,
                'visibility' => $organizedVisibility,
                'state' => $organizedState,
                'search' => $organizedSearch,
            ],
            'organized_state_options' => CompetitionStateFilter::forScope(CompetitionBrowseScope::Organized),
            'discoverable_state_options' => CompetitionStateFilter::forScope(CompetitionBrowseScope::Discoverable),
            'visibility_options' => CompetitionVisibilityFilter::cases(),
            'discoverable' => $discoverable,
            'discoverable_filters' => [
                'sport' => $publicSportId,
                'visibility' => CompetitionVisibilityFilter::All,
                'state' => $publicState,
                'search' => $publicSearch,
            ],
            'wallet_balance' => $walletBalance,
        ]);
    }

    private function uuidParam(Request $request, string $name): ?Uuid
    {
        $raw = $request->query->getString($name);

        return Uuid::isValid($raw) ? Uuid::fromString($raw) : null;
    }
}
