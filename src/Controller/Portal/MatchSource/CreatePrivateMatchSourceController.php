<?php

declare(strict_types=1);

namespace App\Controller\Portal\MatchSource;

use App\Command\CreatePrivateMatchSource\CreatePrivateMatchSourceCommand;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Form\MatchSourceFormData;
use App\Form\MatchSourceFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/turnaje/vytvorit', name: 'portal_match_source_create')]
final class CreatePrivateMatchSourceController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $formData = new MatchSourceFormData();
        $form = $this->createForm(MatchSourceFormType::class, $formData, [
            'with_creation_pin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $envelope = $this->commandBus->dispatch(new CreatePrivateMatchSourceCommand(
                ownerId: $user->id,
                name: $formData->name,
                description: $formData->description ?: null,
                startAt: $formData->startAt,
                endAt: $formData->endAt,
                creationPin: $formData->creationPin ?: null,
            ));

            $matchSource = $this->extractMatchSource($envelope);

            $this->addFlash('success', 'Turnaj byl vytvořen.');

            return $this->redirectToRoute('portal_match_source_detail', ['id' => $matchSource->id->toRfc4122()]);
        }

        return $this->render('portal/match_source/create_private.html.twig', [
            'form' => $form,
        ]);
    }

    private function extractMatchSource(Envelope $envelope): MatchSource
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof MatchSource) {
            throw new \LogicException('Expected MatchSource to be returned by handler.');
        }

        return $result;
    }
}
