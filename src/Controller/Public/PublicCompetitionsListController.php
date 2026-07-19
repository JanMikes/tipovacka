<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListDiscoverableGlobalCompetitions\ListDiscoverableGlobalCompetitions;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public discovery of global competitions — the ONLY publicly listed competitions
 * (user competitions join via PIN / link / e-mail invite). Curated-source browsing
 * was retired in S09. See .docs/DOMAIN.md §Global competitions.
 */
#[Route('/souteze', name: 'public_competitions_list', methods: ['GET'])]
final class PublicCompetitionsListController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->getUser();
        $viewerId = $user instanceof User ? $user->id : null;

        $competitions = $this->queryBus->handle(new ListDiscoverableGlobalCompetitions(viewerId: $viewerId));

        // A verified viewer needs their wallet balance so the card can show the
        // „Máte X/Y, dokoupit" state upfront when they cannot afford the fee —
        // instead of a „Připojit se" button that would bounce to the top-up page.
        $userHasCompetitions = false;
        $walletBalance = 0;
        if ($user instanceof User && $user->isVerified) {
            $userHasCompetitions = [] !== $this->queryBus->handle(new ListMyCompetitions(userId: $user->id));
            $walletBalance = $this->queryBus->handle(new GetCreditWallet($user->id))->balance;
        }

        return $this->render('public/competitions_list.html.twig', [
            'competitions' => $competitions,
            'user_has_competitions' => $userHasCompetitions,
            'wallet_balance' => $walletBalance,
        ]);
    }
}
