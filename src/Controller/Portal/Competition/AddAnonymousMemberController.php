<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\CreateAnonymousMember\CreateAnonymousMemberCommand;
use App\Entity\User;
use App\Form\AnonymousMemberFormData;
use App\Form\AnonymousMemberFormType;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/clenove/bez-emailu',
    name: 'portal_competition_add_anonymous_member',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class AddAnonymousMemberController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $formData = new AnonymousMemberFormData();
        $form = $this->createForm(AnonymousMemberFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new CreateAnonymousMemberCommand(
                competitionId: $competition->id,
                actorId: $user->id,
                firstName: trim($formData->firstName),
                lastName: trim($formData->lastName),
                nickname: $formData->nickname,
            ));

            $this->addFlash('success', 'Tipující byl přidán do soutěže.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/add_anonymous_member.html.twig', [
            'competition' => $competition,
            'form' => $form,
        ]);
    }
}
