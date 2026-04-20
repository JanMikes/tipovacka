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
    '/skupiny/pozvanka/{token}',
    name: 'group_join_by_link',
    requirements: ['token' => '[a-f0-9]{48}'],
    methods: ['GET', 'POST'],
)]
final class JoinByShareableLinkController extends AbstractController
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
            kind: InvitationKind::ShareableLink,
            token: $token,
            currentUser: $user instanceof User ? $user : null,
        );
    }
}
