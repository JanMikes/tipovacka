<?php

declare(strict_types=1);

namespace App\Controller\Admin\MatchSource;

use App\Command\CreateCuratedMatchSource\CreateCuratedMatchSourceCommand;
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

#[Route('/admin/turnaje/vytvorit', name: 'admin_match_source_create')]
final class CreateCuratedMatchSourceController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $formData = new MatchSourceFormData();
        $form = $this->createForm(MatchSourceFormType::class, $formData, [
            'with_sport' => true,
            'with_global_option' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->sport);

            $envelope = $this->commandBus->dispatch(new CreateCuratedMatchSourceCommand(
                adminId: $admin->id,
                sportId: $formData->sport->id,
                name: $formData->name,
                description: $formData->description ?: null,
                startAt: $formData->startAt,
                endAt: $formData->endAt,
                createGlobalCompetition: $formData->createGlobalCompetition,
                globalCompetitionName: $formData->globalCompetitionName ?: null,
                globalCompetitionEntryFee: $formData->globalCompetitionEntryFee,
                globalCompetitionMonetization: $formData->globalCompetitionMonetization,
            ));

            $matchSource = $this->extractMatchSource($envelope);

            $this->addFlash('success', $formData->createGlobalCompetition
                ? 'Zdroj zápasů i globální soutěž byly vytvořeny.'
                : 'Zdroj zápasů byl vytvořen.');

            return $this->redirectToRoute('portal_match_source_detail', ['id' => $matchSource->id->toRfc4122()]);
        }

        return $this->render('admin/match_source/create_curated.html.twig', [
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
