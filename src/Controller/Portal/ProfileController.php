<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/profil', name: 'portal_profile_edit', methods: ['GET'])]
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
