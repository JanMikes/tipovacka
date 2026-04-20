<?php

declare(strict_types=1);

namespace App\Controller\Admin\User;

use App\Form\AdminUserSearchFormData;
use App\Form\AdminUserSearchFormType;
use App\Query\ListAdminUsers\ListAdminUsers;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/uzivatele', name: 'admin_user_list', methods: ['GET'])]
final class ListUsersController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $formData = new AdminUserSearchFormData();
        $form = $this->createForm(AdminUserSearchFormType::class, $formData);
        $form->handleRequest($request);

        $users = $this->queryBus->handle(new ListAdminUsers(
            search: null !== $formData->search && '' !== $formData->search ? $formData->search : null,
            verified: $formData->verifiedFilter(),
            active: $formData->activeFilter(),
        ));

        return $this->render('admin/user/list.html.twig', [
            'form' => $form,
            'users' => $users,
        ]);
    }
}
