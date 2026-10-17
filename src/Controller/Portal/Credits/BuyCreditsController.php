<?php

declare(strict_types=1);

namespace App\Controller\Portal\Credits;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\Entity\User;
use App\Enum\CreditPurchaseStatus;
use App\Exception\CreditPurchaseRequiresEmail;
use App\Exception\InvalidCreditAmount;
use App\Form\BuyCreditsFormData;
use App\Form\BuyCreditsFormType;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListCreditPurchases\ListCreditPurchases;
use App\Query\ListCreditTransactions\ListCreditTransactions;
use App\Query\QueryBus;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kredity/koupit', name: 'credits_buy', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class BuyCreditsController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly QueryBus $queryBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $formData = new BuyCreditsFormData();
        $form = $this->createForm(BuyCreditsFormType::class, $formData, [
            'action' => $this->generateUrl('credits_buy'),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('credits');
        }

        if ($form->isValid()) {
            // {CHECKOUT_SESSION_ID} is a Stripe template placeholder — appended
            // raw because generateUrl() would url-encode the braces.
            $successUrl = $this->generateUrl('credits_return', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $this->generateUrl('credits_return', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?cancelled=1';

            try {
                $envelope = $this->commandBus->dispatch(new InitiateCreditPurchaseCommand(
                    userId: $user->id,
                    credits: $formData->credits ?? 0,
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                ));

                return $this->redirect($this->extractCheckout($envelope)->checkoutUrl, Response::HTTP_SEE_OTHER);
            } catch (HandlerFailedException $e) {
                // Every way the checkout can fail to OPEN ends on this same page, with the
                // reason inside the form (the render below answers 422) — never a 500 page
                // that leaves the buyer wondering whether money moved. None did: the
                // purchase row is written only after Stripe hands back a session.
                $inner = $e->getPrevious();

                if ($inner instanceof InvalidCreditAmount) {
                    $form->get('credits')->addError(new FormError($inner->getMessage()));
                } elseif ($inner instanceof CreditPurchaseRequiresEmail) {
                    $form->addError(new FormError('Kredity si může dobít jen účet s e-mailem — fakturu za platbu posíláme e-mailem. Tento účet e-mail nemá, proto platbu nelze spustit.'));
                } else {
                    $this->logger->error('Stripe checkout for a credit purchase could not be opened.', ['exception' => $e]);
                    $form->addError(new FormError('Platební bránu se teď nepodařilo otevřít. Nic jsme vám nestrhli — zkuste to prosím za chvíli znovu.'));
                }
            }
        }

        // A form re-rendered with an error answers 422 — render() does it for a form that
        // failed validation; an error added after a VALID submit (the checkout could not be
        // opened) is invisible to that check, so the status is set explicitly.
        return $this->render('portal/credits/overview.html.twig', [
            'form' => $form,
            'wallet' => $this->queryBus->handle(new GetCreditWallet($user->id)),
            'transactions' => $this->queryBus->handle(new ListCreditTransactions($user->id)),
            'pendingPurchases' => $this->queryBus->handle(new ListCreditPurchases(
                userId: $user->id,
                status: CreditPurchaseStatus::Pending,
            )),
            'boostShortfall' => null,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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
