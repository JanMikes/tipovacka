<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\JoinGroupByPin\JoinGroupByPinCommand;
use App\Entity\Group;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\InvalidPin;
use App\Form\JoinByPinFormData;
use App\Form\JoinByPinFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pripojit', name: 'portal_group_join_by_pin')]
final class JoinByPinController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $formData = new JoinByPinFormData();
        $form = $this->createForm(JoinByPinFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $envelope = $this->commandBus->dispatch(new JoinGroupByPinCommand(
                    userId: $user->id,
                    pin: $formData->pin,
                ));

                $group = $this->extractGroup($envelope);

                $this->addFlash('success', 'Byl(a) jsi přidán(a) do skupiny.');

                return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
            } catch (HandlerFailedException $handlerFailed) {
                $inner = $handlerFailed->getPrevious();

                if ($inner instanceof InvalidPin) {
                    $form->get('pin')->addError(new \Symfony\Component\Form\FormError('Zadaný PIN neexistuje.'));
                } elseif ($inner instanceof AlreadyMember) {
                    $form->get('pin')->addError(new \Symfony\Component\Form\FormError('Ve skupině již jsi.'));
                } elseif ($inner instanceof CannotJoinFinishedTournament) {
                    $form->get('pin')->addError(new \Symfony\Component\Form\FormError('Turnaj této skupiny je již ukončen.'));
                } else {
                    throw $handlerFailed;
                }
            }
        }

        return $this->render('portal/group/join_by_pin.html.twig', [
            'form' => $form,
        ]);
    }

    private function extractGroup(Envelope $envelope): Group
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Group) {
            throw new \LogicException('Expected Group to be returned by handler.');
        }

        return $result;
    }
}
