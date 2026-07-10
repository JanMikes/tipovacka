<?php

declare(strict_types=1);

namespace App\Controller\Portal\Credits;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\Entity\User;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/portal/kredity/koupit', name: 'portal_credits_buy', methods: ['POST'])]
final class BuyCreditsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $formData = new BuyCreditsFormData();
        $form = $this->createForm(BuyCreditsFormType::class, $formData, [
            'action' => $this->generateUrl('portal_credits_buy'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // {CHECKOUT_SESSION_ID} is a Stripe template placeholder — appended
            // raw because generateUrl() would url-encode the braces.
            $successUrl = $this->generateUrl('portal_credits_return', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $this->generateUrl('portal_credits_return', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?cancelled=1';

            $envelope = $this->commandBus->dispatch(new InitiateCreditPurchaseCommand(
                userId: $user->id,
                credits: $formData->credits ?? 0,
                successUrl: $successUrl,
                cancelUrl: $cancelUrl,
            ));

            return $this->redirect($this->extractCheckout($envelope)->checkoutUrl, Response::HTTP_SEE_OTHER);
        }

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

    private function extractCheckout(Envelope $envelope): InitiatedCreditCheckout
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof InitiatedCreditCheckout) {
            throw new \LogicException('Expected InitiatedCreditCheckout to be returned by handler.');
        }

        return $result;
    }
}
