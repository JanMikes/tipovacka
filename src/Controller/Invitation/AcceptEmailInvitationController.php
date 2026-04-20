<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Service\Invitation\InvitationLandingProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/pozvanka/{token}',
    name: 'group_accept_invitation',
    requirements: ['token' => '[a-f0-9]{64}'],
    methods: ['GET', 'POST'],
)]
final class AcceptEmailInvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationLandingProcessor $processor,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $user = $this->getUser();

        return $this->processor->handle(
            request: $request,
            kind: InvitationKind::Email,
            token: $token,
            currentUser: $user instanceof User ? $user : null,
        );
    }
}
