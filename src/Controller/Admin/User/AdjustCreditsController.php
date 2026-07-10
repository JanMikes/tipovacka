<?php

declare(strict_types=1);

namespace App\Controller\Admin\User;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Entity\User;
use App\Exception\InsufficientCredits;
use App\Form\AdjustCreditsFormData;
use App\Form\AdjustCreditsFormType;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\ListCreditTransactions\ListCreditTransactions;
use App\Query\QueryBus;
use App\Repository\UserRepository;
use App\Voter\AdminUserManagementVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/admin/uzivatele/{id}/kredity',
    name: 'admin_user_credits',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class AdjustCreditsController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $user = $this->userRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(AdminUserManagementVoter::ADJUST_CREDITS, $user);

        /** @var User $admin */
        $admin = $this->getUser();

        $formData = new AdjustCreditsFormData();
        $form = $this->createForm(AdjustCreditsFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->commandBus->dispatch(new AdjustUserCreditsCommand(
                    userId: $user->id,
                    amount: $formData->amount ?? 0,
                    note: $formData->note ?? '',
                    adjustedById: $admin->id,
                ));

                $this->addFlash('success', sprintf(
                    '%s %d kreditů uživateli %s.',
                    ($formData->amount ?? 0) > 0 ? 'Přidáno' : 'Odebráno',
                    abs($formData->amount ?? 0),
                    $user->displayName,
                ));

                return $this->redirectToRoute('admin_user_credits', ['id' => $user->id->toRfc4122()]);
            } catch (HandlerFailedException $e) {
                $insufficient = null;

                foreach ($e->getWrappedExceptions() as $wrapped) {
                    if ($wrapped instanceof InsufficientCredits) {
                        $insufficient = $wrapped;

                        break;
                    }
                }

                if (null === $insufficient) {
                    throw $e;
                }

                $form->get('amount')->addError(new FormError($insufficient->getMessage()));
            }
        }

        return $this->render('admin/user/credits.html.twig', [
            'user' => $user,
            'form' => $form,
            'wallet' => $this->queryBus->handle(new GetCreditWallet($user->id)),
            'transactions' => $this->queryBus->handle(new ListCreditTransactions($user->id)),
        ]);
    }
}
