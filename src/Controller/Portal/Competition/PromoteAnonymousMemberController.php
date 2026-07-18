<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\PromoteAnonymousMember\PromoteAnonymousMemberCommand;
use App\Entity\User;
use App\Form\PromoteAnonymousMemberFormData;
use App\Form\PromoteAnonymousMemberFormType;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/clenove/{userId}/pridat-email',
    name: 'portal_competition_promote_anonymous_member',
    requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class PromoteAnonymousMemberController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id, string $userId): Response
    {
        /** @var User $actor */
        $actor = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $target = $this->userRepository->get(Uuid::fromString($userId));

        $formData = new PromoteAnonymousMemberFormData();
        $form = $this->createForm(PromoteAnonymousMemberFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new PromoteAnonymousMemberCommand(
                userId: $target->id,
                competitionId: $competition->id,
                actorId: $actor->id,
                email: $formData->email,
            ));

            $this->addFlash('success', 'Pozvánka byla odeslána.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/promote_anonymous_member.html.twig', [
            'competition' => $competition,
            'target' => $target,
            'form' => $form,
        ]);
    }
}
