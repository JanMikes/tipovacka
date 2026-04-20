<?php

declare(strict_types=1);

namespace App\Controller\Group;

use App\Command\AcceptGroupInvitation\AcceptGroupInvitationCommand;
use App\Entity\GroupInvitation;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Query\GetInvitationByToken\GetInvitationByToken;
use App\Query\QueryBus;
use App\Repository\GroupInvitationRepository;
use App\Repository\UserRepository;
use App\Service\Security\InvitationIntentSession;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pozvanka/{token}', name: 'group_accept_invitation', requirements: ['token' => '[a-f0-9]{64}'])]
final class AcceptInvitationController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly InvitationIntentSession $intent,
        private readonly ClockInterface $clock,
        private readonly GroupInvitationRepository $invitationRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(string $token): Response
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        try {
            $result = $this->queryBus->handle(new GetInvitationByToken(
                token: $token,
                now: $now,
            ));
        } catch (InvalidInvitationToken) {
            return $this->render('group/accept_invitation_landing.html.twig', [
                'state' => 'invalid',
                'invitation' => null,
            ], new Response(status: 404));
        } catch (HandlerFailedException $handlerFailed) {
            if ($handlerFailed->getPrevious() instanceof InvalidInvitationToken) {
                return $this->render('group/accept_invitation_landing.html.twig', [
                    'state' => 'invalid',
                    'invitation' => null,
                ], new Response(status: 404));
            }

            throw $handlerFailed;
        }

        if ($result->isRevoked) {
            return $this->render('group/accept_invitation_landing.html.twig', [
                'state' => 'revoked',
                'invitation' => $result,
            ]);
        }

        if ($result->isExpired) {
            return $this->render('group/accept_invitation_landing.html.twig', [
                'state' => 'expired',
                'invitation' => $result,
            ]);
        }

        if ($result->isAccepted) {
            return $this->render('group/accept_invitation_landing.html.twig', [
                'state' => 'accepted',
                'invitation' => $result,
            ]);
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            // If a stub account (passwordless) was pre-provisioned for this invitee
            // (bulk-invite flow), send them to set a password rather than to login.
            $invitation = $this->invitationRepository->getByToken($token);
            $invitedUser = $this->userRepository->findByEmail($invitation->email);

            if (null !== $invitedUser && !$invitedUser->hasPassword) {
                return $this->redirectToRoute('invitation_complete_registration', ['token' => $token]);
            }

            $this->intent->store($token);
            $this->addFlash('info', 'Pro přijetí pozvánky se prosím přihlaste.');

            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified) {
            $this->intent->store($token);
            $this->addFlash('warning', 'Nejprve si ověř svou e-mailovou adresu.');

            return $this->redirectToRoute('app_verify_email_pending');
        }

        try {
            $envelope = $this->commandBus->dispatch(new AcceptGroupInvitationCommand(
                userId: $user->id,
                token: $token,
            ));

            $invitation = $this->extractInvitation($envelope);

            $this->addFlash('success', 'Byl(a) jsi přidán(a) do skupiny přes pozvánku.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $invitation->group->id->toRfc4122()]);
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof GroupInvitationExpired) {
                return $this->render('group/accept_invitation_landing.html.twig', [
                    'state' => 'expired',
                    'invitation' => $result,
                ]);
            }

            if ($inner instanceof GroupInvitationAlreadyAccepted) {
                return $this->render('group/accept_invitation_landing.html.twig', [
                    'state' => 'accepted',
                    'invitation' => $result,
                ]);
            }

            if ($inner instanceof GroupInvitationAlreadyRevoked) {
                return $this->render('group/accept_invitation_landing.html.twig', [
                    'state' => 'revoked',
                    'invitation' => $result,
                ]);
            }

            if ($inner instanceof AlreadyMember) {
                $this->addFlash('info', 'Ve skupině již jsi.');

                return $this->redirectToRoute('portal_dashboard');
            }

            throw $handlerFailed;
        }
    }

    private function extractInvitation(Envelope $envelope): GroupInvitation
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $invitation = $stamp->getResult();

        if (!$invitation instanceof GroupInvitation) {
            throw new \LogicException('Expected GroupInvitation from handler.');
        }

        return $invitation;
    }
}
