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
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * „Kamarád mě sem pozval" for a GLOBAL competition — the fourth landing, and the only one
 * carrying no secret at all: the id in the URL is the competition's own, and the same
 * competition is listed on the public „Soutěže" page anyway.
 *
 * It exists for the one thing the public list cannot do: name THIS competition to somebody
 * who has no account yet, and carry that intent through sign-up and the verification mail
 * round trip (B15). Everything after that is the ordinary paid join —
 * {@see \App\Command\JoinGlobalCompetition\JoinGlobalCompetitionHandler} charges the entry
 * fee, and a wallet too thin lands on the top-up page with the shortfall named. Being
 * invited buys no discount.
 */
#[Route(
    '/souteze/{id}/pozvanka',
    name: 'competition_global_invitation',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
final class JoinGlobalCompetitionInviteController extends AbstractController
{
    public function __construct(
        private readonly InvitationContextResolver $contextResolver,
        private readonly InvitationAcceptanceService $acceptanceService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        try {
            $context = $this->contextResolver->resolve(InvitationKind::GlobalCompetition, $id, $now);
        } catch (InvalidInvitationToken) {
            // Also the answer for a PRIVATE competition's id — deliberately
            // indistinguishable, so this page cannot confirm that one exists.
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
