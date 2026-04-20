<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}',
    name: 'portal_sport_match_detail',
    requirements: ['id' => Requirement::UUID],
)]
final class SportMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        return $this->render('portal/sport_match/detail.html.twig', [
            'sport_match' => $sportMatch,
        ]);
    }
}
