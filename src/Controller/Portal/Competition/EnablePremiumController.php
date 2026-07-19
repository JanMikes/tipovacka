<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\EnablePremium\EnablePremiumCommand;
use App\Entity\User;
use App\Exception\InsufficientCredits;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/premium/zapnout',
    name: 'portal_competition_premium_enable',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class EnablePremiumController extends AbstractController
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
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        if (!$this->isCsrfTokenValid('competition_premium_enable_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        try {
            $this->commandBus->dispatch(new EnablePremiumCommand(
                editorId: $user->id,
                competitionId: $competition->id,
            ));
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof InsufficientCredits) {
                $this->addFlash('error', $inner->getMessage());

                return $this->redirectToRoute('portal_credits');
            }

            throw $handlerFailed;
        }

        $this->addFlash('success', 'Prémium bylo zapnuto — za členy platíte vy.');

        return $this->redirectToRoute('portal_competition_premium', ['id' => $competition->id->toRfc4122()]);
    }
}
