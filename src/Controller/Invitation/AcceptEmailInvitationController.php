<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\InvalidInvitationToken;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\Invitation\InvitationContextStatus;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/pozvanka/{token}',
    name: 'group_accept_invitation',
    requirements: ['token' => '[a-f0-9]{64}'],
    methods: ['GET'],
)]
final class AcceptEmailInvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationContextResolver $contextResolver,
        private readonly InvitationAcceptanceService $acceptanceService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $token): Response
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        try {
            $context = $this->contextResolver->resolve(InvitationKind::Email, $token, $now);
        } catch (InvalidInvitationToken) {
            return new Response(
                $this->renderView('invitation/landing.html.twig', ['step' => 'invalid', 'context' => null]),
                Response::HTTP_NOT_FOUND,
            );
        }

        if (InvitationContextStatus::Active !== $context->status) {
            return $this->acceptanceService->renderStatus($context);
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            return $this->acceptanceService->handleAuthenticated($context, $user);
        }

        return $this->render('invitation/landing.html.twig', [
            'step' => 'form',
            'context' => $context,
        ]);
    }
}
