<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\Entity\User;
use App\Enum\BoostType;
use App\Exception\BoostNotAvailable;
use App\Exception\InsufficientCredits;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * A player buys a per-competition boost from a paywall / the „Tvoje vylepšení"
 * sidebar. Insufficient credits redirect to the top-up page with a friendly
 * message; other domain guards flash an error and return to the origin page.
 */
#[Route(
    '/portal/souteze/{id}/vylepseni/koupit',
    name: 'portal_competition_boost_purchase',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class PurchaseBoostController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);

        $backTo = $this->resolveRedirect($request, $competition->id);

        if (!$this->isCsrfTokenValid('boost_purchase_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirect($backTo);
        }

        $type = BoostType::tryFrom((string) $request->request->get('type', ''));

        if (null === $type) {
            $this->addFlash('error', 'Neznámý typ vylepšení.');

            return $this->redirect($backTo);
        }

        try {
            $this->commandBus->dispatch(new PurchaseBoostCommand(
                userId: $user->id,
                competitionId: $competition->id,
                type: $type,
            ));
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof InsufficientCredits) {
                $this->addFlash('error', $inner->getMessage().' Dokupte si prosím kredity.');

                return $this->redirectToRoute('portal_credits');
            }

            if ($inner instanceof BoostNotAvailable || $inner instanceof NotAMember) {
                $this->addFlash('error', $inner->getMessage());

                return $this->redirect($backTo);
            }

            if ($inner instanceof UniqueConstraintViolationException) {
                $this->addFlash('error', 'Toto vylepšení už máte.');

                return $this->redirect($backTo);
            }

            throw $handlerFailed;
        } catch (UniqueConstraintViolationException) {
            // A double-click races two identical purchases; the partial unique
            // index rejects the duplicate at flush time (after the handler
            // returned, so it arrives UNWRAPPED, not inside HandlerFailedException).
            // Each transaction rolls back its own debit, so this is money-safe —
            // surface a friendly message instead of a 500.
            $this->addFlash('error', 'Toto vylepšení už máte.');

            return $this->redirect($backTo);
        }

        $this->addFlash('success', sprintf('Vylepšení „%s“ je aktivní.', $type->label()));

        return $this->redirect($backTo);
    }

    /**
     * Return to the origin page after buying. Only SAME-SITE absolute paths are
     * accepted (no open redirect): a leading `//` or `/\` is how browsers read a
     * protocol-relative URL to another host, so both are rejected. Anything else
     * falls back to the competition detail. The paywall lives on pages outside
     * `/portal/` too (`/zapasy`, `/nastenka`), hence the path-wide rule.
     */
    private function resolveRedirect(Request $request, Uuid $competitionId): string
    {
        $redirectTo = (string) $request->request->get('_redirect', '');

        if (str_starts_with($redirectTo, '/')
            && !str_starts_with($redirectTo, '//')
            && !str_starts_with($redirectTo, '/\\')
        ) {
            return $redirectTo;
        }

        return $this->generateUrl('portal_competition_detail', ['id' => $competitionId->toRfc4122()]);
    }
}
