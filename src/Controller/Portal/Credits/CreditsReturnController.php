<?php

declare(strict_types=1);

namespace App\Controller\Portal\Credits;

use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Entity\CreditPurchase;
use App\Entity\User;
use App\Service\Competition\GlobalJoinReturnIntentSession;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Landing page after Stripe Checkout. Fulfillment normally arrives via
 * webhook; dispatching it here too closes the gap when the user is faster
 * than the webhook — the command is idempotent, whoever comes second no-ops.
 */
#[Route('/portal/kredity/navrat', name: 'portal_credits_return', methods: ['GET'])]
final class CreditsReturnController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
        private readonly GlobalJoinReturnIntentSession $returnIntent,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->query->getBoolean('cancelled')) {
            $this->addFlash('info', 'Platba byla zrušena. Žádné kredity nebyly připsány.');

            return $this->redirectToRoute('portal_credits');
        }

        $sessionId = $request->query->getString('session_id');

        if ('' === $sessionId) {
            return $this->redirectToRoute('portal_credits');
        }

        try {
            $envelope = $this->commandBus->dispatch(new FulfillCreditPurchaseCommand($sessionId));
            $purchase = $envelope->last(HandledStamp::class)?->getResult();
        } catch (\Throwable $e) {
            $this->logger->error('Ověření platby po návratu ze Stripe selhalo.', ['exception' => $e]);
            $this->addFlash('warning', 'Stav platby se nepodařilo ověřit. Jakmile ji Stripe potvrdí, kredity připíšeme automaticky.');

            return $this->redirectToRoute('portal_credits');
        }

        if ($purchase instanceof CreditPurchase && $purchase->user->id->equals($user->id)) {
            if ($purchase->isCompleted) {
                $this->addFlash('success', sprintf('Platba proběhla úspěšně — %d kreditů bylo připsáno. Děkujeme!', $purchase->credits));

                // Came here from a paid global-competition join? Send the user
                // back to the public discovery list, anchored on that competition
                // (they click „Připojit se" again — we never auto-join). We land on
                // /souteze, NOT the competition detail: the detail page's VIEW voter
                // forbids non-members, so a not-yet-joined user would hit a 403.
                $returnTo = $this->returnIntent->consume();

                if (null !== $returnTo && Uuid::isValid($returnTo)) {
                    return $this->redirect(
                        $this->generateUrl('public_competitions_list').'#soutez-'.$returnTo,
                    );
                }
            } elseif ($purchase->isPending) {
                $this->addFlash('info', 'Platba se zpracovává. Kredity připíšeme, jakmile ji Stripe potvrdí.');
            } else {
                $this->addFlash('warning', 'Platba nebyla dokončena. Zkuste to prosím znovu.');
            }
        }

        return $this->redirectToRoute('portal_credits');
    }
}
