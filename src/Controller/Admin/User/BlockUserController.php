<?php

declare(strict_types=1);

namespace App\Controller\Admin\User;

use App\Command\BlockUser\BlockUserCommand;
use App\Repository\UserRepository;
use App\Voter\AdminUserManagementVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/uzivatele/{id}/zablokovat', name: 'admin_user_block', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class BlockUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $user = $this->userRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(AdminUserManagementVoter::BLOCK, $user);

        if (!$this->isCsrfTokenValid('admin_user_block_'.$user->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('admin_user_list');
        }

        $this->commandBus->dispatch(new BlockUserCommand(userId: $user->id));

        $this->addFlash('success', 'Uživatel byl zablokován.');

        return $this->redirectToRoute('admin_user_list');
    }
}
