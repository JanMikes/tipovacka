<?php

declare(strict_types=1);

namespace App\Controller\Group;

use App\Command\CompleteInvitationRegistration\CompleteInvitationRegistrationCommand;
use App\Entity\GroupInvitation;
use App\Entity\User;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvitationAlreadyRegistered;
use App\Form\CompleteInvitationRegistrationFormData;
use App\Form\CompleteInvitationRegistrationFormType;
use App\Repository\GroupInvitationRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/pozvanka/{token}/dokoncit-registraci',
    name: 'invitation_complete_registration',
    requirements: ['token' => '[a-f0-9]{64}'],
)]
final class CompleteInvitationRegistrationController extends AbstractController
{
    public function __construct(
        private readonly GroupInvitationRepository $invitationRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('group_accept_invitation', ['token' => $token]);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        try {
            $invitation = $this->invitationRepository->getByToken($token);
        } catch (InvalidInvitationToken) {
            return $this->render('group/complete_invitation_registration.html.twig', [
                'state' => 'invalid',
                'invitation' => null,
                'form' => null,
            ], new Response(status: 404));
        }

        if ($invitation->isRevoked) {
            return $this->render('group/complete_invitation_registration.html.twig', [
                'state' => 'revoked', 'invitation' => $invitation, 'form' => null,
            ]);
        }
        if ($invitation->isAccepted) {
            return $this->render('group/complete_invitation_registration.html.twig', [
                'state' => 'accepted', 'invitation' => $invitation, 'form' => null,
            ]);
        }
        if ($invitation->isExpiredAt($now)) {
            return $this->render('group/complete_invitation_registration.html.twig', [
                'state' => 'expired', 'invitation' => $invitation, 'form' => null,
            ]);
        }

        $user = $this->userRepository->findByEmail($invitation->email);

        // No stub account (the invitee has never been provisioned) or the account is already
        // password-protected: redirect to the regular accept flow, which will funnel them through login.
        if (null === $user || $user->hasPassword) {
            return $this->redirectToRoute('group_accept_invitation', ['token' => $token]);
        }

        $form = $this->createForm(
            CompleteInvitationRegistrationFormType::class,
            new CompleteInvitationRegistrationFormData(),
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $envelope = $this->commandBus->dispatch(new CompleteInvitationRegistrationCommand(
                    token: $token,
                    plainPassword: $form->getData()->password,
                ));

                $acceptedInvitation = $this->extractInvitation($envelope);
                $this->security->login($user);

                $this->addFlash('success', 'Registrace dokončena, vítej ve skupině!');

                return $this->redirectToRoute('portal_group_detail', ['id' => $acceptedInvitation->group->id->toRfc4122()]);
            } catch (HandlerFailedException $handlerFailed) {
                $inner = $handlerFailed->getPrevious();

                if ($inner instanceof InvitationAlreadyRegistered) {
                    return $this->redirectToRoute('app_login');
                }

                if ($inner instanceof GroupInvitationExpired
                    || $inner instanceof GroupInvitationAlreadyRevoked
                    || $inner instanceof GroupInvitationAlreadyAccepted
                ) {
                    return $this->redirectToRoute('group_accept_invitation', ['token' => $token]);
                }

                throw $handlerFailed;
            }
        }

        return $this->render('group/complete_invitation_registration.html.twig', [
            'state' => 'form',
            'invitation' => $invitation,
            'form' => $form,
        ]);
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
