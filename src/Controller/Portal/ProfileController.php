<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil', name: 'profile_edit', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $user);

        return $this->render('portal/profile/edit.html.twig', [
            'user' => $user,
        ]);
    }
}
