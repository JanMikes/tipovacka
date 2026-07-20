<?php

declare(strict_types=1);

namespace App\Controller\Admin\Competition;

use App\Command\CreateGlobalCompetition\CreateGlobalCompetitionCommand;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Form\GlobalCompetitionFormData;
use App\Form\GlobalCompetitionFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/souteze/globalni/vytvorit', name: 'admin_global_competition_create')]
final class CreateGlobalCompetitionController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $formData = new GlobalCompetitionFormData();

        // „+ Globální soutěž" quick action from the sources list prefills the source.
        $sourceParam = $request->query->get('source');
        if (is_string($sourceParam) && Uuid::isValid($sourceParam)) {
            $source = $this->entityManager->find(MatchSource::class, Uuid::fromString($sourceParam));
            if ($source instanceof MatchSource && $source->isCurated && $source->isActive) {
                $formData->matchSource = $source;
            }
        }

        $form = $this->createForm(GlobalCompetitionFormType::class, $formData, ['with_source' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->matchSource);

            $envelope = $this->commandBus->dispatch(new CreateGlobalCompetitionCommand(
                adminId: $admin->id,
                matchSourceId: $formData->matchSource->id,
                name: $formData->name,
                entryFeeCredits: $formData->entryFeeCredits,
                monetization: $formData->monetization,
            ));

            $competition = $this->extractCompetition($envelope);

            $this->addFlash('success', 'Globální soutěž byla vytvořena.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('admin/competition/create_global.html.twig', [
            'form' => $form,
        ]);
    }

    private function extractCompetition(Envelope $envelope): Competition
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Competition) {
            throw new \LogicException('Expected Competition to be returned by handler.');
        }

        return $result;
    }
}
