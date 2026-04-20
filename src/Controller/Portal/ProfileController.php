<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Command\UpdateUserProfile\UpdateUserProfileCommand;
use App\Entity\User;
use App\Form\ProfileFormData;
use App\Form\ProfileFormType;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/profil', name: 'portal_profile_edit')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $user);

        $formData = ProfileFormData::fromUser($user);
        $form = $this->createForm(ProfileFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateUserProfileCommand(
                userId: $user->id,
                firstName: $formData->firstName ?: null,
                lastName: $formData->lastName ?: null,
                phone: $formData->phone,
            ));

            $this->addFlash('success', 'Profil byl úspěšně uložen.');

            return $this->redirectToRoute('portal_profile_edit');
        }

        return $this->render('portal/profile/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
