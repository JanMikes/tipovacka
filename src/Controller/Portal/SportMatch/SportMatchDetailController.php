<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Entity\User;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
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
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $user = $this->getUser();
        $myCompetitionsForMatchSource = [];

        if ($user instanceof User) {
            foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
                if ($membership->competition->matchSource->id->equals($sportMatch->matchSource->id)) {
                    $guess = $this->guessRepository->findActiveByUserMatchCompetition(
                        $user->id,
                        $sportMatch->id,
                        $membership->competition->id,
                    );
                    $myCompetitionsForMatchSource[] = [
                        'id' => $membership->competition->id,
                        'name' => $membership->competition->name,
                        'hasGuess' => null !== $guess,
                    ];
                }
            }
        }

        return $this->render('portal/sport_match/detail.html.twig', [
            'sport_match' => $sportMatch,
            'my_competitions_for_match_source' => $myCompetitionsForMatchSource,
        ]);
    }
}
