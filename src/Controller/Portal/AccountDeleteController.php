<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Command\SoftDeleteUser\SoftDeleteUserCommand;
use App\Entity\User;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/portal/ucet/smazat', name: 'portal_account_delete')]
final class AccountDeleteController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted(ProfileVoter::DELETE, $user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

                return $this->redirectToRoute('portal_account_delete');
            }

            $this->commandBus->dispatch(new SoftDeleteUserCommand(userId: $user->id));

            // Invalidate session and clear token
            $this->tokenStorage->setToken(null);

            if ($request->hasSession()) {
                $request->getSession()->invalidate();
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('portal/profile/delete_confirm.html.twig', [
            'user' => $user,
        ]);
    }
}
