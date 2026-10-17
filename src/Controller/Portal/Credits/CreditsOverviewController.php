<?php

declare(strict_types=1);

namespace App\Controller\Portal\Credits;

use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CreditPurchaseStatus;
use App\Form\BuyCreditsFormData;
use App\Form\BuyCreditsFormType;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListCreditPurchases\ListCreditPurchases;
use App\Query\ListCreditTransactions\ListCreditTransactions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kredity', name: 'credits', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class CreditsOverviewController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * `?vylepseni=<BoostType>` — the visitor came from a booster their wallet cannot
     * cover (the paywall's „Odemknout za N kr." routes here, and so does a purchase
     * refused for insufficient credits). The dobití card then names the booster and
     * the shortfall. The shortfall is computed HERE from the real balance and
     * BoostType::price(), never taken from the URL.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(BuyCreditsFormType::class, new BuyCreditsFormData(), [
            'action' => $this->generateUrl('credits_buy'),
        ]);

        $wallet = $this->queryBus->handle(new GetCreditWallet($user->id));
        $boost = BoostType::tryFrom($request->query->getString('vylepseni'));
        $boostShortfall = null !== $boost && $wallet->balance < $boost->price()
            ? ['label' => $boost->label(), 'price' => $boost->price(), 'missing' => $boost->price() - $wallet->balance]
            : null;

        return $this->render('portal/credits/overview.html.twig', [
            'form' => $form,
            'wallet' => $wallet,
            'boostShortfall' => $boostShortfall,
            'transactions' => $this->queryBus->handle(new ListCreditTransactions($user->id)),
            'pendingPurchases' => $this->queryBus->handle(new ListCreditPurchases(
                userId: $user->id,
                status: CreditPurchaseStatus::Pending,
            )),
        ]);
    }
}
