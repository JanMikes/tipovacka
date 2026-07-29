<?php

declare(strict_types=1);

namespace App\Controller\Portal\Credits;

use App\Entity\User;
use App\Enum\CreditPurchaseStatus;
use App\Form\BuyCreditsFormData;
use App\Form\BuyCreditsFormType;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListCreditPurchases\ListCreditPurchases;
use App\Query\ListCreditTransactions\ListCreditTransactions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(BuyCreditsFormType::class, new BuyCreditsFormData(), [
            'action' => $this->generateUrl('credits_buy'),
        ]);

        return $this->render('portal/credits/overview.html.twig', [
            'form' => $form,
            'wallet' => $this->queryBus->handle(new GetCreditWallet($user->id)),
            'transactions' => $this->queryBus->handle(new ListCreditTransactions($user->id)),
            'pendingPurchases' => $this->queryBus->handle(new ListCreditPurchases(
                userId: $user->id,
                status: CreditPurchaseStatus::Pending,
            )),
        ]);
    }
}
