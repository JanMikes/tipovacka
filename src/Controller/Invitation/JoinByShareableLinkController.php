<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\InvalidShareableLink;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\Invitation\InvitationContextStatus;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/skupiny/pozvanka/{token}',
    name: 'group_join_by_link',
    requirements: ['token' => '[a-f0-9]{48}'],
    methods: ['GET'],
)]
final class JoinByShareableLinkController extends AbstractController
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
            $context = $this->contextResolver->resolve(InvitationKind::ShareableLink, $token, $now);
        } catch (InvalidShareableLink) {
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
